# LeadFlow Form Partners worker

Picks queued rows from `form_submissions`, opens each partner's form in headless Chromium (Playwright),
runs the partner's steps with the lead's data, and stores `submitted` / `failed` back in the same table.
Requests go out from this server's own IP (no proxy).

## Flow
1. Lead hits `POST /api/v1/leads` → LeadFlow queues one row per **active** partner (`form_partners`).
2. This worker claims rows (`FOR UPDATE SKIP LOCKED`, safe with multiple instances), in partner order.
3. Failure → retried after `WORKER_RETRY_DELAY_SEC` up to the partner's `max_retries`, then `failed`.
4. Admin: `/form-partners` (partners, add/edit/pause/order) and `/form-partners/log` (per-lead status, retry). No sidebar link; super_admin/admin only.

## Deploy on Render
- New → **Background Worker**, same repo, Root Directory `worker`, Runtime **Docker**.
- Env vars: copy `.env.example` and use the same DB values as the LeadFlow web service (`DB_SSL=true` for managed MySQL).
- Run LeadFlow once so migration `005_form_partners.sql` creates the tables.

## Run locally
```bash
cd worker
npm install
npx playwright install --with-deps chromium
DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=leadflow DB_PASSWORD=leadflow_secret npm start
```
