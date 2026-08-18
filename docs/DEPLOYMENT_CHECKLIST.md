# Deployment Checklist

Consolidated pre-deploy checklist from Stage 3's security/production-readiness audit and
implementation. Each item links back to the doc with the full explanation. **Nothing on this list
has been deployed or verified against a real production environment** — this repository has no
production environment; every item below is either an implemented-and-CI-verified repository
change, or a manual action only an operator with real infrastructure can complete.

## Before you deploy at all

- [ ] **Environment variables set for real** (not `.env.example`'s dev/CI defaults):
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
  - `MAIL_MAILER` set to a real transport — **the app refuses to boot in production with
    `MAIL_MAILER=log`**, see [SECURITY_BASELINE.md #2](SECURITY_BASELINE.md#2-password-reset-tokens-and-sensitive-data-in-logs)
  - `DB_*` pointed at your real MySQL (never the Sail dev credentials)
  - `CACHE_STORE=redis` with real `REDIS_HOST`/`REDIS_PASSWORD` — **do not leave this on
    `database`** (breaks tenant cache tagging — empirically confirmed, see
    [SECURITY_BASELINE.md #8](SECURITY_BASELINE.md#8-cache--the-cache_store-finding)) **or
    `array`** (non-persistent, wrong for multi-process production)
  - `CORS_ALLOWED_ORIGINS` set to your real Web frontend origin(s) — empty by default, nothing
    works from a browser until this is set, see
    [SECURITY_BASELINE.md #3](SECURITY_BASELINE.md#3-cors-for-the-web-frontend)
  - `TRUSTED_PROXIES` set to your real load balancer/reverse proxy IP(s) — empty by default, see
    [SECURITY_BASELINE.md #5](SECURITY_BASELINE.md#5-trusted-proxies--trusted-hosts)
  - `SESSION_SECURE_COOKIE=true` (only once HTTPS is actually terminated correctly in front of
    the app)
  - `MYSQL_ATTR_SSL_CA` set if your MySQL provider requires/supports TLS

- [ ] **Never run `LandlordSeeder` or `TenantMasterSeeder` against production** — both seed
  predictable/fake credentials, meant for local dev and the Docs Sandbox Tenant only. See
  [SECURITY_BASELINE.md #1](SECURITY_BASELINE.md#1-secrets-and-environment-configuration).

- [ ] **TLS termination** in front of the app (load balancer/ingress/CDN) — this repo's
  `docker/production/nginx.conf` serves plain HTTP internally on purpose, matching that pattern.
  See [SECURITY_BASELINE.md #10](SECURITY_BASELINE.md#10-https).

## Infrastructure

- [ ] Real MySQL provisioned (managed service recommended — RDS/Cloud SQL/etc. — over
  self-hosting), sized for expected tenant count/traffic. See
  [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md#mysql).
- [ ] Real Redis provisioned, password-protected. Required — see the `CACHE_STORE` item above.
- [ ] Queue worker(s) running and covering **all 4 queues** (`grading`, `psychometrics`,
  `penalties`, `analytics`) — fixed in `compose.prod.yaml`/`compose.yaml` this stage, but confirm
  whatever actually deploys them (your orchestrator config) uses the corrected command. See
  [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md#queue-workers).
- [ ] Scheduler running (`schedule:work` process or a cron `schedule:run` every minute) — without
  this, `queue:prune-failed` (and anything scheduled in the future) silently never runs. See
  [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md#scheduler).
- [ ] Backups configured and **tested with an actual restore** — none exist by default. See
  [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md) for exact commands and the safe restore-test
  procedure (run against the Docs Sandbox Tenant, never a real tenant).
- [ ] Monitoring/alerting wired to at least: application errors, failed queue jobs, DB/Redis
  health (`GET /api/v1/system/status`), HTTP 5xx rate, rate-limit spikes. No vendor is chosen in
  this repo — see [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md#monitoring-and-alerting)
  for the integration points that already exist to wire up.

## Verify after deploying (against a real environment, not this repo)

- [ ] `GET /api/v1/system/status` on a real tenant subdomain returns `status: "ok"`,
  `database: "connected"`, `redis: "connected"`.
- [ ] A browser-based request from your actual Web frontend origin succeeds (CORS headers
  present) and a request from a different origin does not receive them.
- [ ] Response headers include `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  and (since you're now on real HTTPS) `Strict-Transport-Security`.
- [ ] Submitting a password-reset request does **not** write a plaintext token to any log file
  (confirms `MAIL_MAILER` is really a real transport, not silently still `log`).
- [ ] A test job on each of the 4 queues actually gets processed (not just `grading`).
- [ ] A restore test has been performed at least once against non-production data (see
  [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md)) — a backup you've never restored from is not a
  verified backup.

## Explicitly deferred (not blockers, but real gaps — tracked here, not silently dropped)

- `TrustHosts` middleware — not enabled (guessing the wrong host pattern could reject legitimate
  traffic); documented how to enable it correctly once you know your real production host
  pattern. See [SECURITY_BASELINE.md #5](SECURITY_BASELINE.md#5-trusted-proxies--trusted-hosts).
- `GET /api/ping` reachable from tenant subdomains too (leaks no data) — kept unchanged this
  stage per explicit instruction.
- Tenant-scoped `queue:prune-failed` (currently landlord-only) — see
  [PRODUCTION_OPERATIONS.md](PRODUCTION_OPERATIONS.md#failed-jobs).
- No WAF/reverse-proxy rate-limit backstop below the application layer — see
  [SECURITY_BASELINE.md #7](SECURITY_BASELINE.md#7-rate-limiting).
- No monitoring/APM vendor selected.
- No backup automation actually running (documentation + commands only).

## Explicitly out of scope for this repository (Web/Mobile team owns these)

Web UI, Mobile UI, Web SDK, Mobile SDK — this repository owns the API contract only, per every
prior stage's handoff docs (see [API_HANDOFF.md](API_HANDOFF.md)).
