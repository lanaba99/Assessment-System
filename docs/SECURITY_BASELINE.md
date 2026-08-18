# Security Baseline

Findings and fixes from the Stage 3 security audit. Every item below is labeled with its status:

- **Implemented** — a real code/config change in this repository, verified locally and/or in CI.
- **CI-verified** — implemented, and a CI job actually exercises it (not just present in code).
- **Local dev config** — how this behaves in Sail/local development specifically.
- **Manual production action** — nothing in this repo can do this for you; an operator/deployment step.
- **Deferred** — a real gap, intentionally not addressed in this stage, with the reason why.

## 1. Secrets and environment configuration

**Implemented.** Audited the full `.env.example`, every `config/*.php` file, `.gitignore`, and the
entire git history (87 commits at time of audit). Findings:
- No secret-shaped variable in `.env.example` carries a real value — every credential field is
  blank/placeholder/`null`.
- No hardcoded secret exists in any `config/*.php` file — every credential-bearing key routes
  through `env(...)`.
- `.env`, `.env.backup`, `.env.production`, `storage/*.key`, and `auth.json` are all in
  `.gitignore`. A real `.env` file has never existed in this repository's history.
- All seeded passwords (`database/seeders/*.php`) are `Hash::make()`'d and scoped to
  `*.example`/`*.test` domains — not real credentials.

**Manual production action.** `database/seeders/LandlordSeeder.php` seeds
`superadmin@alpha-engine.example` / `ChangeMe123!` — a predictable credential. Nothing in code
stops that seeder from running against a real production landlord database. **Never run
`LandlordSeeder` against production.** If a production landlord needs an initial super-admin,
create it manually with a generated password, not via this seeder. `sandbox:setup`/
`sandbox:reset` (Stage 1) already refuse to run in production — see
[ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md#the-docs-sandbox-tenant) — but `LandlordSeeder` itself
has no such guard and was out of scope to modify here (it's core landlord bootstrapping, not a
Stage-3-owned file).

## 2. Password-reset tokens and sensitive data in logs

**Implemented.** `app/Providers/AppServiceProvider.php`'s `guardAgainstLogMailerOutsideLocalDevelopment()`
throws a `RuntimeException` at boot if `MAIL_MAILER=log` (or any mailer whose `config('mail.default')`
resolves to `log`) is active outside `local`/`testing` environments. This directly closes the
audit's critical finding: `AuthenticationServiceImpl::requestPasswordReset()` renders a plaintext,
usable reset token into the mail body (`PasswordResetRequested` notification) — with the `log`
mailer, that token would land in `storage/logs/laravel.log`. The guard makes it impossible to boot
the app with that combination anywhere except local dev/CI.

**CI-verified.** `tests/Feature/Security/MailMailerProductionGuardTest.php` — 4 tests: refuses in
`production` with `log`, boots fine in `local`/`testing` with `log`, boots fine in `production`
with a real transport (`smtp`).

**Local dev config.** `.env.example` still ships `MAIL_MAILER=log` — this is correct and
intentional for local/Sail/CI (`APP_ENV=local`/`testing` there), with a comment directly above it
explaining the guard.

**Manual production action.** Set `MAIL_MAILER` to a real transport (`smtp`, `ses`, `postmark`,
`resend`, ...) and its credentials before deploying with `APP_ENV=production` — the app will
refuse to boot otherwise, by design.

## 3. CORS for the Web frontend

**Implemented.** `config/cors.php` was missing entirely — Laravel's `HandleCors` middleware
resolved `cors.paths` to an empty array and never added any `Access-Control-Allow-*` header at
all, silently blocking every browser-based cross-origin client. The new file:
- `allowed_origins` comes from `CORS_ALLOWED_ORIGINS` (comma-separated), **empty by default** —
  nothing is allowed until explicitly configured. Never a wildcard.
- `supports_credentials: false` — this API is bearer-token authenticated (`Authorization` header),
  not cookie/session-based, so credentialed CORS is unnecessary and was deliberately not enabled.
- `allowed_headers` includes `Authorization`, `Idempotency-Key`, `X-Tenant-ID` — the real
  non-standard headers this API actually uses.
- `exposed_headers` includes `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
  `X-Idempotent-Replay` so frontend JS can read them (browsers hide non-exposed headers from JS
  by default even when present on the response).

**CI-verified.** `tests/Feature/Security/CorsConfigurationTest.php` — confirms an allowed origin
gets the header, a disallowed origin (among 2+ configured) does not, the default is zero allowed
origins, `*` is never used, and credentials mode stays off.

**Manual production action.** Set `CORS_ALLOWED_ORIGINS=https://your-web-app.example.com` (comma-
separate if staging + production both need access) in the real environment.

## 4. Security response headers

**Implemented.** `app/Http/Middleware/SecurityHeaders.php`, registered globally in
`bootstrap/app.php` (`$middleware->append(...)`). Adds on every response:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` — **only when the request
  itself arrived over HTTPS** (`$request->secure()`). Never sent on plain-HTTP local/Sail
  requests — sending HSTS there would tell browsers to force HTTPS for that host going forward,
  breaking the next `http://*.localhost` visit.

Purely additive — no endpoint's payload, status code, or business logic changed.

**CI-verified.** `tests/Feature/Security/SecurityHeadersTest.php` — confirms all 3 unconditional
headers are present, HSTS is absent over plain HTTP, and HSTS is present when the request is
secure.

## 5. Trusted proxies / trusted hosts

**Implemented (safe default, not a guess).** `bootstrap/app.php` registers
`$middleware->trustProxies(at: ...)` reading a new `TRUSTED_PROXIES` env var (comma-separated
IPs/CIDRs). **Empty by default** — trusts no proxy, which is functionally identical to not having
this middleware at all (`$request->ip()` reflects the real TCP peer, exactly today's behavior).
Zero regression risk.

**Deferred — deliberately not code.** `TrustHosts` was considered and **not** enabled. This app's
tenancy resolution depends on accepting many different subdomains
(`*.localhost` locally, arbitrary customer subdomains in production) — guessing the wrong host
pattern could silently reject legitimate traffic (including load-balancer health checks) in ways
that are hard to catch without a real deployment to test against. This is exactly the kind of
"don't guess production IP ranges / host patterns" case you flagged — documented as a manual
action instead.

**Manual production action.**
- Set `TRUSTED_PROXIES` to your real load balancer/reverse proxy's IP(s)/CIDR(s) so
  `$request->ip()` (used for rate-limit keys and audit trails) and scheme detection reflect the
  real client, not the proxy.
- If you want `TrustHosts` enabled, add it explicitly in `bootstrap/app.php` once you know the
  real production host pattern (e.g. `$middleware->trustHosts(at: fn () => ['^(.+\.)?yourdomain\.com$'])`)
  — test it against a staging environment with the exact tenant-subdomain shape you'll use in
  production before enabling in prod.

## 6. Tenant isolation and authorization

**Preserved, re-verified, not modified.** Confirmed unchanged from the Stage 3 audit:
`DatabaseTenancyBootstrapper` is registered first (`config/tenancy.php`), every tenant route sits
behind `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains` + (except 7 intentionally
public routes) `auth:sanctum`, and queued jobs correctly re-establish tenant context via
stancl/tenancy's `QueueTenancyBootstrapper` payload-stamping mechanism. No tenancy-resolution code
was touched in Stage 3.

**Deferred.** `GET /api/ping` (`routes/api.php:27`) sits outside the `EnsureCentralDomain` group
and is reachable from tenant subdomains too — returns no data (`{"scope":"central","ok":true}`),
explicitly kept unchanged this stage per your instruction. Low-priority follow-up.

## 7. Rate limiting

**Preserved, documented, not modified.** No change to `AppServiceProvider::configureRateLimiting()`,
`ThrottleLoginMiddleware`, or any `throttle:` usage. Full reference already exists in
[ERRORS_AND_VALIDATION.md](ERRORS_AND_VALIDATION.md#rate-limiting) — summary: 240 req/min general
tenant-API limit (per tenant+actor), 5 failed logins/15min (per tenant+email AND per tenant+IP
independently), `Retry-After` always present on 429, `X-RateLimit-Limit`/`X-RateLimit-Remaining`
present except on the hand-rolled login throttle.

**Manual production action.** No lower-layer (WAF/reverse-proxy) rate-limit backstop exists in
this repository — confirmed by audit (no nginx/Cloudflare/WAF config anywhere). A production
deployment should add one at the load balancer/CDN layer (e.g. Cloudflare rate limiting, an nginx
`limit_req` zone in front of the app) as defense-in-depth against volumetric abuse the
application-layer limiter alone can't absorb (it still costs a PHP request to reject a request).

## 8. Cache — the CACHE_STORE finding

**Implemented, empirically verified, not just asserted.** Confirmed directly (not assumed): the
`database` cache store throws `BadMethodCallException: This cache store does not support tagging.`
the moment `Cache::tags(...)` is called. Then traced *why this matters*: `Stancl\Tenancy\CacheManager`
(the manager this app's `CacheTenancyBootstrapper` swaps in while any tenant is active) intercepts
**every** `Cache::` call via `__call()` and wraps it in `->tags([...])` unconditionally — not just
explicit tag usage. And the app genuinely calls `Cache::` in tenant-context code
(`EnsureIdempotency.php` among others). So under the previous `.env.example` default
(`CACHE_STORE=database`), **any idempotent tenant endpoint call would throw a 500** the moment
someone sent an `Idempotency-Key` header — this was masked in tests only because `phpunit.xml`
forces `CACHE_STORE=array` (which does support tags) for the whole suite.

Fix: `.env.example`'s `CACHE_STORE` default changed to `redis` (already fully configured in
`config/cache.php`'s `redis` store — no code work needed, just the env flip. `REDIS_HOST` default
also changed to `redis`, the Sail/Docker service hostname — non-Docker/CI setups override to
`127.0.0.1`, documented inline).

**CI-verified — a real round-trip, not just config presence.** The `migrations-and-docs` CI job's
"Verify Redis Cache (put/get + tags)" step actually calls `Cache::put`/`Cache::get` and
`Cache::tags([...])->put/get` against the job's real `redis:alpine` service container and fails
the job if either doesn't round-trip correctly.

**Never use `CACHE_STORE=array` for production** — it's non-persistent and per-process; fine for
tests/CI, wrong for a real multi-process deployment (each PHP-FPM worker would have its own
disconnected cache).

## 9. MySQL production configuration

**Preserved.** `utf8mb4`/`utf8mb4_unicode_ci`, `strict: true`, InnoDB (default engine) are already
correct in `config/database.php` and were not changed.

**Manual production action.**
- TLS: `config/database.php`'s mysql connection already supports `MYSQL_ATTR_SSL_CA` via env, but
  `.env.example` has no example value (adding a fake CA path would be actively misleading). Set
  `MYSQL_ATTR_SSL_CA=/path/to/real-ca-bundle.pem` in production if your MySQL provider requires/
  supports TLS (most managed providers — RDS, Cloud SQL, PlanetScale — do and often require it).
- Credentials: never reuse the Sail dev credentials (`sail`/`password`, empty-password-allowed).
  Use your platform's secrets manager to inject real `DB_USERNAME`/`DB_PASSWORD`.
- Connection sizing: no read-replica/pooling config exists in this app (single connection only).
  If your MySQL provider needs connection pooling (e.g. RDS Proxy, PgBouncer-equivalent), that's
  configured at the infrastructure layer, not in `config/database.php`.
- Backups: see [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md).

## 10. HTTPS

**Implemented (safe, conditional).** `AppServiceProvider::forceHttpsInProduction()` calls
`URL::forceScheme('https')` only when `app()->environment('production')` — a no-op everywhere
else (local/testing/CI all report `local`/`testing`). This only affects URLs the app itself
generates (e.g. certificate-verification links); it does not — and cannot — force incoming
requests onto HTTPS, since a Laravel app behind a reverse proxy has no way to redirect a raw TCP
connection. That's the reverse proxy/load balancer's job.

`SESSION_SECURE_COOKIE=false` is now explicit in `.env.example` (previously unset/implicit-null,
same practical effect) with a comment to set it `true` once HTTPS is correctly terminated in
front of the app.

**Manual production action.** Terminate TLS at your load balancer/reverse proxy/CDN (this repo
has no TLS-terminating config — `docker/production/nginx.conf` deliberately serves plain HTTP
internally, matching a standard "TLS terminates upstream" pattern). Once that's in place: set
`APP_URL=https://your-domain`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.
