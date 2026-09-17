'use strict';
/**
 * LeadFlow Form Partners worker.
 * Polls form_submissions (MySQL queue), opens the partner's form in headless Chromium,
 * runs the partner's configured steps with the lead's data and records the result.
 * Traffic goes out from this server's own IP (no proxy).
 */
const mysql = require('mysql2/promise');
const { chromium } = require('playwright');

const env = (k, d) => (process.env[k] !== undefined && process.env[k] !== '' ? process.env[k] : d);
const CONCURRENCY = Math.max(1, parseInt(env('WORKER_CONCURRENCY', '2'), 10));
const POLL_MS = parseInt(env('WORKER_POLL_MS', '3000'), 10);
const RETRY_DELAY_SEC = parseInt(env('WORKER_RETRY_DELAY_SEC', '300'), 10);
const STALE_LOCK_MIN = parseInt(env('WORKER_STALE_LOCK_MIN', '10'), 10);
const HEADLESS = env('WORKER_HEADLESS', 'true') !== 'false';
const STEP_TIMEOUT_MS = 15000;

const pool = mysql.createPool({
  host: env('DB_HOST', '127.0.0.1'),
  port: parseInt(env('DB_PORT', '3306'), 10),
  database: env('DB_DATABASE', 'leadflow'),
  user: env('DB_USERNAME', 'root'),
  password: env('DB_PASSWORD', ''),
  charset: 'utf8mb4',
  connectionLimit: CONCURRENCY + 2,
  dateStrings: true,
  ssl: ['1', 'true'].includes(String(env('DB_SSL', 'false')).toLowerCase()) ? { rejectUnauthorized: false } : undefined,
});

const log = (level, msg, extra = {}) =>
  console.log(JSON.stringify({ ts: new Date().toISOString(), level, msg, ...extra }));

let browser = null;
let stopping = false;

async function getBrowser() {
  if (browser && browser.isConnected()) return browser;
  browser = await chromium.launch({ headless: HEADLESS });
  return browser;
}

/** Atomically claim the next due job. */
async function claimJob() {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [rows] = await conn.query(
      `SELECT s.id FROM form_submissions s JOIN form_partners p ON p.id = s.partner_id
       WHERE s.status = 'queued' AND s.next_attempt_at <= NOW()
       ORDER BY s.lead_id ASC, p.sort_order ASC, p.id ASC
       LIMIT 1 FOR UPDATE OF s SKIP LOCKED`
    );
    if (!rows.length) { await conn.commit(); return null; }
    const id = rows[0].id;
    await conn.query(
      `UPDATE form_submissions SET status = 'running', attempts = attempts + 1, locked_at = NOW(), updated_at = NOW() WHERE id = ?`,
      [id]
    );
    await conn.commit();
    const [[job]] = await pool.query('SELECT * FROM form_submissions WHERE id = ?', [id]);
    return job;
  } catch (e) {
    await conn.rollback().catch(() => {});
    throw e;
  } finally {
    conn.release();
  }
}

async function recoverStaleLocks() {
  const [r] = await pool.query(
    `UPDATE form_submissions SET status = 'queued', locked_at = NULL, updated_at = NOW()
     WHERE status = 'running' AND locked_at < (NOW() - INTERVAL ? MINUTE)`,
    [STALE_LOCK_MIN]
  );
  if (r.affectedRows) log('warn', 'recovered stale jobs', { count: r.affectedRows });
}

async function loadLeadData(leadRowId) {
  const [[lead]] = await pool.query('SELECT * FROM leads WHERE id = ?', [leadRowId]);
  if (!lead) return null;
  const [custom] = await pool.query('SELECT field_key, field_value FROM lead_custom_fields WHERE lead_id = ?', [leadRowId]);
  const data = {};
  for (const c of custom) data[c.field_key] = c.field_value ?? '';
  const core = [
    'lead_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'zip', 'date_of_birth',
    'employment_status', 'monthly_income', 'pay_frequency', 'loan_amount', 'sub_id',
  ];
  for (const k of core) data[k] = lead[k] === null || lead[k] === undefined ? '' : String(lead[k]);
  const digits = (data.phone || '').replace(/\D/g, '').slice(-10);
  data.phone_dashed = digits.length === 10 ? `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}` : data.phone;
  return data;
}

const render = (tpl, data) =>
  String(tpl ?? '').replace(/\{([a-zA-Z0-9_]+)\}/g, (_, k) => (data[k] !== undefined ? String(data[k]) : ''));

const parseJson = (v) => {
  if (v === null || v === undefined || v === '') return null;
  if (typeof v === 'object') return v;
  try { return JSON.parse(v); } catch { return null; }
};

async function runSteps(page, steps, data) {
  for (let i = 0; i < steps.length; i++) {
    const s = steps[i];
    const t = s.optional ? 3000 : STEP_TIMEOUT_MS;
    const label = `step ${i + 1} (${s.action}${s.selector ? ' ' + s.selector : ''})`;
    try {
      switch (s.action) {
        case 'goto':
          await page.goto(render(s.url, data), { waitUntil: 'domcontentloaded' });
          break;
        case 'fill':
          await page.locator(s.selector).first().fill(render(s.value, data), { timeout: t });
          break;
        case 'select': {
          const value = render(s.value, data);
          const loc = page.locator(s.selector).first();
          await loc.selectOption(value, { timeout: t })
            .catch(() => loc.selectOption({ label: value }, { timeout: t }));
          break;
        }
        case 'check':
          await page.locator(s.selector).first().check({ timeout: t });
          break;
        case 'click':
          await page.locator(s.selector).first().click({ timeout: t });
          break;
        case 'wait':
          await page.waitForTimeout(Math.min(60000, parseInt(s.ms, 10) || 1000));
          break;
        case 'wait_for':
          await page.locator(s.selector).first().waitFor({ state: 'visible', timeout: t });
          break;
        default:
          throw new Error('unknown action');
      }
    } catch (e) {
      if (s.optional) continue;
      throw new Error(`${label}: ${e.message.split('\n')[0]}`);
    }
  }
}

async function checkSuccess(page, rule) {
  if (!rule) return;
  if (rule.url_contains) {
    await page.waitForURL((u) => u.toString().includes(rule.url_contains), { timeout: STEP_TIMEOUT_MS })
      .catch(() => { throw new Error(`success check: URL does not contain "${rule.url_contains}" (got ${page.url()})`); });
  } else if (rule.text_contains) {
    await page.getByText(rule.text_contains, { exact: false }).first().waitFor({ state: 'visible', timeout: STEP_TIMEOUT_MS })
      .catch(() => { throw new Error(`success check: text "${rule.text_contains}" not found`); });
  } else if (rule.selector) {
    await page.locator(rule.selector).first().waitFor({ state: 'visible', timeout: STEP_TIMEOUT_MS })
      .catch(() => { throw new Error(`success check: selector "${rule.selector}" not found`); });
  }
}

async function finish(job, partner, ok, info) {
  const maxAttempts = (partner ? parseInt(partner.max_retries, 10) : 0) + 1;
  if (ok) {
    await pool.query(
      `UPDATE form_submissions SET status = 'submitted', locked_at = NULL, final_url = ?, error_message = NULL, duration_ms = ?, updated_at = NOW() WHERE id = ?`,
      [String(info.finalUrl || '').slice(0, 1000), info.durationMs, job.id]
    );
  } else if (job.attempts < maxAttempts && !info.noRetry) {
    await pool.query(
      `UPDATE form_submissions SET status = 'queued', locked_at = NULL, next_attempt_at = (NOW() + INTERVAL ? SECOND),
       final_url = ?, error_message = ?, duration_ms = ?, updated_at = NOW() WHERE id = ?`,
      [RETRY_DELAY_SEC, String(info.finalUrl || '').slice(0, 1000) || null, String(info.error).slice(0, 1000), info.durationMs, job.id]
    );
  } else {
    await pool.query(
      `UPDATE form_submissions SET status = 'failed', locked_at = NULL, final_url = ?, error_message = ?, duration_ms = ?, updated_at = NOW() WHERE id = ?`,
      [String(info.finalUrl || '').slice(0, 1000) || null, String(info.error).slice(0, 1000), info.durationMs, job.id]
    );
  }
}

async function processJob(job) {
  const started = Date.now();
  const [[partner]] = await pool.query('SELECT * FROM form_partners WHERE id = ?', [job.partner_id]);
  if (!partner || !partner.active) {
    return finish(job, partner, false, { error: 'partner inactive or deleted', durationMs: 0, noRetry: true });
  }
  const data = await loadLeadData(job.lead_id);
  const steps = parseJson(partner.steps_json);
  if (!data || !Array.isArray(steps)) {
    return finish(job, partner, false, { error: !data ? 'lead not found' : 'invalid steps_json', durationMs: 0, noRetry: true });
  }

  const b = await getBrowser();
  const context = await b.newContext({ locale: 'en-US' });
  const page = await context.newPage();
  const timeoutMs = Math.max(5000, parseInt(partner.timeout_ms, 10) || 45000);
  let timer;
  try {
    const work = (async () => {
      await page.goto(partner.form_url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
      await runSteps(page, steps, data);
      await checkSuccess(page, parseJson(partner.success_json));
    })();
    const timeout = new Promise((_, rej) => { timer = setTimeout(() => rej(new Error(`timeout after ${timeoutMs}ms`)), timeoutMs); });
    await Promise.race([work, timeout]);
    await finish(job, partner, true, { finalUrl: page.url(), durationMs: Date.now() - started });
    log('info', 'submitted', { submission_id: job.id, lead: data.lead_id, partner: partner.name, ms: Date.now() - started });
  } catch (e) {
    const error = e.message.split('\n')[0];
    await finish(job, partner, false, { error, finalUrl: page.url(), durationMs: Date.now() - started });
    log('warn', 'submission failed', { submission_id: job.id, lead: data.lead_id, partner: partner.name, attempt: job.attempts, error });
  } finally {
    clearTimeout(timer);
    await context.close().catch(() => {});
  }
}

async function slot(n) {
  while (!stopping) {
    let job = null;
    try {
      job = await claimJob();
    } catch (e) {
      log('error', 'claim failed', { slot: n, error: e.message });
    }
    if (!job) { await new Promise((r) => setTimeout(r, POLL_MS)); continue; }
    try {
      await processJob(job);
    } catch (e) {
      log('error', 'job crashed', { slot: n, submission_id: job.id, error: e.message });
      await pool.query(`UPDATE form_submissions SET status = 'queued', locked_at = NULL, next_attempt_at = (NOW() + INTERVAL ? SECOND), error_message = ?, updated_at = NOW() WHERE id = ?`,
        [RETRY_DELAY_SEC, String(e.message).slice(0, 1000), job.id]).catch(() => {});
    }
  }
}

async function main() {
  log('info', 'worker starting', { concurrency: CONCURRENCY, headless: HEADLESS });
  await pool.query('SELECT 1');
  await recoverStaleLocks();
  const staleTimer = setInterval(() => recoverStaleLocks().catch((e) => log('error', 'stale recovery failed', { error: e.message })), 60000);
  const shutdown = async () => {
    if (stopping) return;
    stopping = true;
    log('info', 'shutting down');
    clearInterval(staleTimer);
  };
  process.on('SIGTERM', shutdown);
  process.on('SIGINT', shutdown);
  await Promise.all(Array.from({ length: CONCURRENCY }, (_, i) => slot(i + 1)));
  if (browser) await browser.close().catch(() => {});
  await pool.end();
  log('info', 'worker stopped');
}

main().catch((e) => { log('error', 'fatal', { error: e.message }); process.exit(1); });
