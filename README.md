# LeadFlow — Ping/Post Lead Distribution Platform

A high-performance, self-hosted Lead Management + Ping-Tree + Ping/Post platform for US consumer loan / payday-style verticals.

Built with PHP 8.3, MySQL 8, Redis, Nginx — Docker-first, no framework, PSR-4.

## Features (MVP shipped)

- Lead Capture REST API with validation, normalization, custom fields
- Consent evidence storage (TrustedForm, Jornaya, versioned policies, IP, UA)
- Buyer management (CRUD, credentials, headers, request format JSON/form/XML, timeouts)
- Buyer eligibility rule engine (AND/OR/nested, `in`, `>=`, `between`, `contains`, …)
- Buyer schedules (timezone-aware) and caps (hourly/daily/monthly/total)
- Field mapping + transformations (phone/date format, upper/lower, default, concat, bool, number_format)
- Ping Tree management (routing modes: `highest_bid`, `priority`, `weighted`, `round_robin`, `waterfall`)
- Concurrent Ping Engine (Guzzle async, per-buyer timeouts, response-time capture)
- Configurable Response Parser (JSON dot-path + XML + form; accepted/rejected values, bid path, txn path)
- Auction Engine (highest bid + tie-break by response time / priority + configurable min bid)
- POST Engine (idempotency key per buyer+lead, Redis distributed lock, waterfall fallback)
- Duplicate detection (email OR phone within N days, default 30)
- Complete lead journey / status history (11 statuses) with an auditable log
- Web admin dashboard (dashboard, buyers, leads, ping-trees, reports, CSV export)
- Session auth for admin + JWT for machine APIs, RBAC (super_admin / admin / manager / analyst)
- Rate limiting on lead intake (Redis-backed)
- Structured JSON logging with sensitive-field masking
- Docker Compose (php-fpm 8.3 + nginx + MySQL 8 + Redis 7)

## Requirements

- Docker + Docker Compose (recommended), OR
- PHP 8.3+ (with pdo_mysql, redis), Composer 2, MySQL 8, Redis 7, Nginx

## Quick start (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install --optimize-autoloader
docker compose exec app php database/migrate.php
docker compose exec app php database/seed.php   # prints the seeded source API key
```

Then open http://localhost:8080 and log in with `admin@leadflow.local / admin1234` (change the password immediately).

## Environment variables

See `.env.example`. Key items:

| Var | Purpose |
|-----|---------|
| `APP_KEY`, `JWT_SECRET` | secrets — set to long random strings |
| `DB_*`, `REDIS_*` | database/redis connection |
| `PING_DEFAULT_TIMEOUT_MS` | default per-ping timeout |
| `PING_MAX_CONCURRENCY` | reserved for future queue tuning |
| `POST_DEFAULT_TIMEOUT_MS` | default POST timeout |
| `AUCTION_MIN_BID` | global minimum bid floor |
| `AUCTION_TIE_BREAK` | `response_time` or `priority` |
| `RATE_LIMIT_PER_MINUTE` | intake rate limit per IP |

## Folder structure

```
app/
  Core/          Application, Container (auto-wire), Router, Request, Response, Config, DB, Redis, Logger, View
  Controllers/   Api/*, Web/*
  Services/      AuthService, LeadOrchestrator, PingTreeService, ReportService, RuleEngine,
                 FieldMappingService, ResponseParserService, BuyerEligibilityService
  Repositories/  LeadRepository, BuyerRepository, PingRepository
  Middleware/    ApiAuthMiddleware, WebAuthMiddleware, LeadIntakeAuthMiddleware, RateLimitMiddleware
  Validators/    LeadValidator
  Support/       Normalizer, Uuid, JsonPath
  PingEngine/    PingEngine (concurrent), AuctionEngine
  PostEngine/    PostEngine (idempotent, locking, waterfall)
config/          app, database, redis, pingpost
database/        migrate.php, seed.php, migrations/, seeds/
docker/          nginx/, php/
public/          index.php (front controller)
resources/views/ layouts, auth, dashboard, buyers, leads, reports
routes/          api.php, web.php
storage/logs/    JSON structured logs
```

## API Reference

### Lead intake (public, requires source API key)

```bash
curl -X POST http://localhost:8080/api/v1/leads \
  -H "X-API-Key: $SOURCE_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name":"Jane","last_name":"Doe",
    "email":"jane@example.com","phone":"5125551234",
    "address":"1 Main St","city":"Austin","state":"TX","zip":"78701",
    "date_of_birth":"1990-05-14","employment_status":"employed",
    "monthly_income":3500,"pay_frequency":"biweekly","loan_amount":500,
    "campaign_key":"payday-a","sub_id":"aff-42",
    "utm_source":"facebook","utm_campaign":"payday-a","utm_content":"variant-b",
    "fb_campaign_id":"123","fb_adset_id":"456","fb_ad_id":"789",
    "trustedform_cert":"https://cert.trustedform.com/xxx",
    "jornaya_lead_id":"0000-1111-2222-3333",
    "consent_version":"1.0","disclosure_version":"1.0","landing_url":"https://landing.example.com/apply"
  }'
```

Response includes the lead ID and the full ping/post outcome inline (since we process synchronously to keep latency low).

### Admin API (JWT)

```
POST /api/v1/auth/login          → { token, user }
GET  /api/v1/leads                Authorization: Bearer <token>
GET  /api/v1/leads/{id}
GET  /api/v1/leads/{id}/journey
POST /api/v1/buyers
PUT  /api/v1/buyers/{id}
POST /api/v1/buyers/{id}/rules
POST /api/v1/buyers/{id}/test-ping
GET  /api/v1/ping-trees
POST /api/v1/ping-trees
PUT  /api/v1/ping-trees/{id}
GET  /api/v1/reports/revenue
```

## How to add a buyer

1. In the admin UI go to **Buyers → + New Buyer**.
2. Fill in name, ping URL, post URL, HTTP method, format (JSON / form / XML), timeout.
3. Paste the credentials JSON, e.g.
   `{"type":"bearer","token":"xyz"}` or `{"type":"basic","username":"u","password":"p"}` or `{"type":"api_key","key":"...","header":"X-API-Key"}`.
4. Paste the **field mapping** as `{ "buyer_field": "internal_field" }`.
5. Paste **transformations** as `[{"field":"phone","op":"phone_format","format":"e164"}]`.
6. Paste **response rules** telling the parser which field indicates acceptance and where the bid lives:
   ```json
   {"accepted_path":"status","accepted_values":["accepted","ok"],
    "rejected_values":["rejected"],"bid_path":"bid","transaction_id_path":"transaction_id"}
   ```
7. Paste **schedule**: `{"timezone":"America/Chicago","days":["mon","tue","wed","thu","fri"],"start":"09:00","end":"18:00"}`.
8. Paste **eligibility rules**:
   ```json
   {"op":"AND","rules":[
     {"field":"state","operator":"in","value":["TX","FL","CA"]},
     {"field":"monthly_income","operator":">=","value":2000},
     {"op":"OR","rules":[
       {"field":"employment_status","operator":"=","value":"employed"},
       {"field":"employment_status","operator":"=","value":"self_employed"}
     ]}
   ]}
   ```
9. Save. Assign the buyer to one or more **Ping Trees** to start receiving traffic.

## How to configure Ping/Post

Each buyer stores its own request shape (method, format, headers, credentials, mapping, transformations, response rules).

The Ping Engine sends the mapped payload to `ping_url` with `{"ping_mode":"ping"}` merged in, and reads the buyer's response through the configured parser. On winning the auction, the Post Engine sends the full lead to `post_url` with an `Idempotency-Key` header derived from `lead_id + buyer_id` and holds a Redis lock while posting to prevent double sales.

## Ping Tree routing modes

- `highest_bid` — winner is the highest bid ≥ min_bid; ties broken by response time or priority (see `.env`).
- `priority` — buyers sorted by `priority` first, then bid.
- `weighted` — winner chosen by `bid × weight`.
- `round_robin` — rotates deterministically across minutes.
- `waterfall` — fallback to next-best on POST failure (any mode can allow fallback via `allow_fallback`).

## Duplicate detection

Default: any lead whose `email` OR `phone` matches an existing lead within 30 days is returned with `{"status":"duplicate"}` and never processed. Adjust the window in `LeadRepository::findDuplicate` (make it a config value in production).

## Security notes

- Bcrypt password hashing (cost 12 default), session auth for web + JWT (HS256) for API.
- Rate limiting on lead intake (`RATE_LIMIT_PER_MINUTE`).
- All log lines mask `password`, `api_key`, `email`, `phone`, `date_of_birth`, `ssn`.
- Buyer credentials are stored in JSON columns — for production, wrap `credentials_json` with app-side encryption using `APP_KEY` and a KMS provider.
- Consent records are append-only in `lead_consents` and status changes are append-only in `lead_status_history`.

## Compliance disclaimer

Storing TrustedForm certificates, Jornaya LeadIDs, and consent evidence via this platform does **not** by itself guarantee compliance with TCPA, FCRA, GLBA, state UDAP statutes, or partner contracts. You are responsible for your consumer consent flow, disclosures, buyer contracts, retention policy, and audit process. This platform is the plumbing.

## Scaling to 200k+ leads/month

- The intake path is synchronous by design (< ~1s p95 with 5–15 buyers) so the auction stays hot.
- Analytics, webhooks, and any non-critical work belong on a background worker (skeleton not wired — add a Redis-backed queue consumer under `app/Jobs/`).
- Add read replicas for reporting queries. Partition `ping_transactions` and `ping_responses` by month if volume exceeds ~100M rows.
- Consider swapping Guzzle for a curl_multi implementation with keep-alive pools if the buyer roster grows past ~50 per lead.

## License

Private / internal use.
