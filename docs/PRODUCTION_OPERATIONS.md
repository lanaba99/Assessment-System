# Production Operations

## Queue workers

**Implemented.** The audit found the deployed worker only consumed the `grading` queue, while 6
`ShouldQueue` classes across the app actually use 4 distinct queue names:

| Class | Queue |
|---|---|
| `ResponseSubmittedListener`, `ExamSessionCompletedListener` | `grading` |
| `RecalculatePsychometricsListener`, `CalculateQuestionMetricsJob` | `psychometrics` |
| `ApplyPenaltyOnProctorEventListener` | `penalties` |
| `IngestResultGeneratedListener` | `analytics` |

**`psychometrics`, `penalties`, and `analytics` jobs were never consumed as deployed** —
proctoring-triggered penalties never actually applied, psychometric recalculation never ran,
analytics ingestion never happened. Silently, since nothing alerted on it either (see below).

Fixed in both `compose.yaml` (Sail dev worker) and `compose.prod.yaml` (production worker
service): `queue:work --queue=grading,psychometrics,penalties,analytics --tries=3 --backoff=5`.
Job payloads/logic were not touched — only which queues get drained.

**Manual production action.** If your platform runs workers as separate scaled services rather
than one process per queue name, you can instead run one worker process per queue
(`--queue=grading`, `--queue=psychometrics`, etc.) for independent scaling/monitoring — just
make sure all 4 are covered by *something*. Watch for a 5th queue name being introduced by future
code without updating whatever runs the worker(s) — nothing currently prevents that drift.

## Failed jobs

**Implemented.** `AppServiceProvider::logFailedQueueJobs()` registers a `JobFailed` event
listener that logs the job class, queue/connection name, and exception message via `Log::error()`
— **never the job payload**, since payloads can carry tenant/candidate data that has no business
sitting in a log line. This is the minimum safe visibility fix: previously, a failed job in any
queue sat silently in `failed_jobs` with nothing surfacing it at all.

`failed_jobs` exists both in the landlord database and in every tenant database (the `database`
queue driver uses whichever connection is active at dispatch time).

**Manual production action.** `Log::error()` only writes to whatever `LOG_CHANNEL` is configured
— by default that's `storage/logs/laravel.log`, which nobody is paged from. Wire a real alerting
channel (see "Monitoring and alerting" below) if failed jobs should page someone rather than just
appear in a log file. `routes/console.php` schedules `queue:prune-failed --hours=168` daily
(Laravel's own built-in command — not custom code) so the landlord `failed_jobs` table doesn't
grow forever; **note this only prunes the landlord table**, not each tenant's own `failed_jobs`
table — a tenant-scoped prune would need to iterate every tenant
(`tenancy()->runForMultiple($allTenants, fn () => Artisan::call('queue:prune-failed', [...]))`)
and was left as a documented follow-up rather than guessed at, since a wrong cross-tenant
iteration under time pressure is worse than an honest gap.

## Scheduler

**Implemented, minimally.** No application-defined scheduled business tasks existed anywhere in
this codebase before Stage 3 (confirmed by audit — zero `Schedule::` calls). None were invented
here without a requirement. The one addition, `routes/console.php`:
```php
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily();
```
This is Laravel's own built-in maintenance command, not custom business logic.

**Manual production action — this is the part that actually makes it run.** Neither
`compose.yaml` nor `compose.prod.yaml` runs a scheduler process by default beyond what's in
`compose.prod.yaml`'s `scheduler` service (`php artisan schedule:work`, a long-running process —
the alternative is a cron entry invoking `php artisan schedule:run` every minute). If you deploy
without running one of those, `queue:prune-failed` (and any future scheduled task) simply never
executes, silently.

## Redis and cache — see [SECURITY_BASELINE.md #8](SECURITY_BASELINE.md#8-cache--the-cache_store-finding)
for the full empirical finding. Summary for operations: `CACHE_STORE=redis` is now the
`.env.example` default (was `database`, which is actively broken for this app's tenancy design).
Set `REDIS_PASSWORD` to a real value in production — `.env.example` ships it as the literal
string `null` (no auth), fine for local Sail, not for a real deployment.

## MySQL

See [SECURITY_BASELINE.md #9](SECURITY_BASELINE.md#9-mysql-production-configuration) for the
security-relevant parts (TLS, credentials). Operationally:
- `utf8mb4`, strict mode, InnoDB — already correct, unchanged.
- Sizing/connection pooling is infrastructure-layer, not application config — size your managed
  MySQL instance (or self-hosted `compose.prod.yaml` fallback) to the tenant count and traffic
  you actually expect; this repo has no data to guess that from.

## HTTPS / reverse proxy

See [SECURITY_BASELINE.md #10](SECURITY_BASELINE.md#10-https). `docker/production/nginx.conf`
deliberately serves plain HTTP internally (port 80) — TLS termination happens upstream (your load
balancer, ingress controller, or CDN), not in this repo's nginx config, since guessing at
certificate/domain management would mean assuming a specific hosting provider.

## Health / readiness checks

**Implemented, additively.** `GET /api/v1/system/status` (tenant-scoped) previously checked only
database connectivity. Now also reports Redis:
```json
{ "data": { "status": "ok", "tenant_id": "...", "database": "connected", "redis": "not_configured", "timestamp": "..." } }
```
`redis` is `"not_configured"` (not degraded) when no cache/queue/session driver is actually
`redis` — relevant for local/testing where `CACHE_STORE=array` is intentional (see
`phpunit.xml`). It's `"connected"` or `"unavailable"` when a redis-backed driver is configured.
Overall `status` becomes `"degraded"` (HTTP 503) only when the database is unreachable, or Redis
is configured but unreachable — never for "not configured". No exception message, connection
string, or credential is ever included in the response — confirmed by
`tests/Feature/Security/SystemHealthCheckTest.php`, which asserts the response body's keys are
exactly `status`, `tenant_id`, `database`, `redis`, `timestamp`.

**Deferred, explained.** "Queue readiness" was considered and deliberately not added as a fake
field — there is no meaningful way for an HTTP request handler to observe whether the separate
worker *process* is alive; that's the worker container/process's own liveness, not something the
web app can see. Monitor the worker process's own health via your orchestrator (Docker/K8s
liveness probe on the worker container) and watch `failed_jobs`/queue depth growth via your
monitoring stack instead of trusting a synthetic "queue ok" field from the web process.

`GET /api/ping` (central) stays a static `200` — kept unchanged this stage per your instruction.
Laravel's default `GET /up` route is present and unmodified (stock behavior, no custom checks).

## Monitoring and alerting

**Deferred — documented integration points only, no vendor chosen, no credentials added.**
Nothing in this app integrates with an external APM/monitoring/alerting provider currently
(`laravel/pail` is a local log-tail CLI tool, not production monitoring — confirmed). What should
be monitored, and where the hooks already exist for you to wire up:

| Signal | Where it already surfaces | What you'd add |
|---|---|---|
| Application errors | Laravel's exception handler (`bootstrap/app.php`) | A Sentry/Bugsnag-equivalent SDK + `report()` callback, or ship `storage/logs/laravel.log` to your log aggregator |
| Failed queue jobs | `AppServiceProvider::logFailedQueueJobs()` → `Log::error(...)` (this stage) | Route the `stack`/`slack` log channel (already defined in `config/logging.php`, just needs `LOG_SLACK_WEBHOOK_URL`) to a real alert destination |
| Database health | `GET /api/v1/system/status` `data.database` | Poll this endpoint from your uptime/monitoring tool |
| Redis health | `GET /api/v1/system/status` `data.redis` (this stage) | Same |
| HTTP 5xx rate | Standard access/error logs, or your reverse proxy's own metrics | Your APM/log platform's usual 5xx-rate alert |
| Rate-limit spikes | `429` responses with `error.code: "rate_limited"` (see ERRORS_AND_VALIDATION.md) | Alert on elevated 429 rate in your log/metrics platform |
| Backup success/failure | See [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md) — no automated backup job exists yet | Whatever backup mechanism you stand up should emit a success/failure signal your monitoring can see |
| Certificate/storage failures | `CertificateGenerationService`/`CertificateController` currently don't emit a distinct signal beyond a 500 | Watch application-error monitoring for exceptions in these classes specifically |

No monitoring vendor was chosen and no credentials were added — that's an explicit product/infra
decision for you to make, not something to guess at here.

## Production Docker baseline

**Implemented — new files, existing dev stack untouched.** `docker/production/Dockerfile` +
`docker/production/nginx.conf` + `docker/production/php.ini` + `compose.prod.yaml` (repo root).
`compose.yaml` (Sail's dev stack) was **not** rewritten — only its worker queue list was fixed
(see above); everything else about local development is unchanged.

What the production baseline avoids, vs. the dev stack:
- No `MYSQL_ALLOW_EMPTY_PASSWORD`, no bundled MySQL/Redis containers by default (commented-out
  optional fallback only — a real deployment should prefer managed MySQL/Redis).
- `APP_DEBUG=false`, `APP_ENV=production` set directly in the service definition.
- No Xdebug, no Mailpit, no Meilisearch dev tooling.
- No full-source bind mount — `docker/production/Dockerfile` `COPY`s the code in at build time,
  producing an immutable image (a real deploy rebuilds and redeploys the image, it doesn't edit
  files inside a running container).
- Runs as the image's non-root `www-data` user, not root.
- `docker/production/php.ini` turns off `display_errors`/`expose_php`, turns on opcache with
  `validate_timestamps=0` (correct for an immutable image — a code change requires a rebuild,
  which is the intended production deploy flow).

**What remains genuinely provider-specific — not guessed at:**
- Real CPU/memory resource limits (depends on measured traffic — `docker/production/php.ini` sets
  reasonable-but-unverified defaults: 256M memory_limit, 10M upload limit).
- Secrets injection — `compose.prod.yaml` references `.env` via `env_file:`; a real deployment
  should use its platform's secrets manager instead of a mounted plaintext file.
- TLS termination, load balancing, autoscaling — all assumed to happen outside this compose file.
- The `public_assets` named-volume seeding limitation is documented directly in
  `compose.prod.yaml`'s header comment (it's populated from the image only on first creation —
  redeploys with new frontend-served assets need the volume recreated, or a proper CDN/static-
  asset pipeline instead).

**Validated:** `docker compose -f compose.prod.yaml config` — static YAML/interpolation validation
only. **Never built or run** — that would require a real target environment and secrets this repo
intentionally doesn't have. Building/running it for the first time against a real (non-production)
environment is a manual verification step before trusting it in production.
