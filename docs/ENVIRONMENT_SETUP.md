# Environment Setup

## Local setup (summary — see the root [README.md](../README.md) for the full walkthrough)

```
cp .env.example .env
# one-off composer install via Docker, then:
./vendor/bin/sail up -d
sail artisan key:generate
sail artisan migrate --path=database/migrations/landlord
sail artisan db:seed --class=LandlordSeeder
sail artisan tenants:migrate
sail artisan tenants:seed --class=TenantMasterSeeder
```

Base URL for central/admin routes: `http://localhost/api/v1`. Tenant routes:
`http://<tenant-subdomain>.localhost/api/v1` — see [TENANCY.md](TENANCY.md).

### Known `.env.example` inconsistency — read before debugging a "can't connect to DB" error

`.env.example` ships `DB_CONNECTION=sqlite`, but `config/tenancy.php` (`template_tenant_connection`,
`root_connection.driver`) and `compose.yaml` (MySQL service, host port `33061`) both assume
**MySQL**. If you copy `.env.example` as-is, the app boots against sqlite but any tenant
operation (`tenants:migrate`, `tenants:seed`, or anything that switches DB connection at
request-time) will fail trying to reach a MySQL server that isn't configured. Set
`DB_CONNECTION=mysql` plus the matching `DB_HOST=mysql` / `DB_PORT=3306` /
`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` values for a working Sail setup — this is a real gap
in the committed `.env.example`, not something you're doing wrong.

## The Docs Sandbox Tenant

A stable, well-known tenant meant for Web/Mobile development and API-doc generation — not a
real customer tenant, safe to seed/reset repeatedly, contains only fake data.

- **Subdomain:** `docs-sandbox.localhost` (from `SCRIBE_TENANT_SUBDOMAIN`, default `docs-sandbox`)
- **Tenant ID:** from `SCRIBE_TENANT_ID`, default `00000000-0000-4000-8000-000000000001`
- **Base URL:** `http://docs-sandbox.localhost/api/v1`

### Setup

```
sail artisan sandbox:setup
```

This one command does everything needed to get a fully working sandbox tenant, in the correct
order:
1. Seeds/updates the landlord `Tenant` + `Domain` row (`DocsSandboxTenantSeeder`) — safe to
   re-run, upserts by `SCRIBE_TENANT_ID`.
2. Runs pending tenant migrations **scoped to that one tenant only**
   (`tenants:migrate --tenants=<id>`).
3. If the tenant's database is still empty, seeds fake demo data — admin/proctor/evaluator
   users, 4 roles with the standard permission matrix, 3 competencies, 1 category, sample MCQ
   questions, one published exam, a cohort with 5 candidate users
   (`tenants:seed --tenants=<id> --class=TenantMasterSeeder`).

If the tenant already has data and you run `sandbox:setup` again without `--fresh`, it skips the
seed step with a warning instead of crashing — `TenantMasterSeeder` uses raw inserts
(not upserts), so re-seeding onto existing rows would otherwise fail on unique-constraint
violations (duplicate emails, exam codes, etc.).

### Reset / reseed

```
sail artisan sandbox:reset
```
Equivalent to `sandbox:setup --fresh` — drops and rebuilds the sandbox tenant's database from
scratch, then reseeds fresh fake data. Use this whenever you want a clean slate (e.g. after
manual testing has left the sandbox in a messy state).

### Safety guarantees (both commands)

- **Refuses to run when `APP_ENV=production`** — no override flag exists for this.
- **Never accepts a tenant id argument.** The target is hard-coded to `SCRIBE_TENANT_ID` — there
  is no way to point this command at an arbitrary tenant from the command line.
- **Refuses if the target id already belongs to a real tenant.** Before touching anything, it
  checks any pre-existing row at that id is already marked `organization_type: sandbox` (the
  marker `DocsSandboxTenantSeeder` itself writes) and aborts loudly otherwise — so accidentally
  misconfiguring `SCRIBE_TENANT_ID` to point at a real tenant's UUID can never overwrite that
  tenant.
- **Refuses if the sandbox subdomain is already claimed by a different tenant** (subdomain
  collision check against the `domains` table).
- Never touches `DocsSandboxTenantSeeder.php` or `TenantMasterSeeder.php` — it only orchestrates
  the same commands the README already documents for setting up any tenant, scoped with
  stancl/tenancy's `--tenants=` option so no other tenant's database is ever migrated or seeded.
- Uses only the existing seeders' fake data — no production credentials, no real personal data,
  ever.

Automated tests for these guarantees: `tests/Feature/Sandbox/SandboxSetupCommandTest.php`
(5 tests — production refusal, non-sandbox-tenant refusal with byte-for-byte-unchanged proof,
subdomain-collision refusal, and that a completely unrelated third tenant's row is never
touched). **Note on scope:** these tests cover the safety guards, which run entirely against the
landlord connection. The actual `tenants:migrate`/`tenants:seed` steps need a real per-tenant
MySQL database (`config/tenancy.php` hard-codes the tenant-database driver to MySQL) and so
can't run inside this project's sqlite-based automated test suite — verify the full
migrate-and-seed happy path manually against a real Sail environment using the test scenario
below.

### Isolation from other tenants / from production

Isolation is structural, not just a convention: `deployment_mode: multi_database` means the
sandbox tenant gets its own physical MySQL database (`tenant_<sandbox-id>`), completely separate
from every other tenant's database and from the landlord database — see
[TENANCY.md](TENANCY.md#database-isolation). `sandbox:setup`/`sandbox:reset` additionally refuse
to run in production at all (see guarantees above), so there's no path by which running these
commands against a production landlord could seed fake data into a real customer's environment.

To verify this yourself against a real running stack:
```
sail artisan tenants:list
# confirm the sandbox tenant id is the ONLY new entry after sandbox:setup,
# and that every other tenant's row/database is untouched
```

### Test scenario — login → exam → result → certificate

Once `sandbox:setup` has run, walk the full flow against `http://docs-sandbox.localhost/api/v1`
using [EXAM_FLOW.md](EXAM_FLOW.md) with these seeded accounts (all password `password`):

```
POST /auth/login  { "email": "tenant.admin@alpha-engine.example", "password": "password" }
# -> use this token to confirm the seeded exam is published (GET /exams)

POST /auth/login  { "email": "candidate.1@alpha-engine.example", "password": "password" }
# -> POST /exam-sessions, POST /exam-sessions/{id}/responses, POST /exam-sessions/{id}/complete
# -> GET  /exam-sessions/{id}/result/publication-status   (expect result_status: final,
#         publication_status: published, immediately — the seeded exam is all-MCQ)
# -> GET  /exam-sessions/{id}/certificate                 (only if the seeded exam's pass mark is met)
```
This matches the note in [RESULTS_AND_CERTIFICATES.md](RESULTS_AND_CERTIFICATES.md): a fully
auto-gradable exam publishes itself the instant grading finishes, so no manual "publish" call is
needed to complete this scenario.

## Generating OpenAPI / Postman / HTML docs

```
sail artisan scribe:generate
```
Reads every route + FormRequest and produces:
- `storage/app/private/scribe/openapi.yaml` (served live at `GET /docs.openapi`)
- `storage/app/private/scribe/collection.json` (served live at `GET /docs.postman`)
- `resources/views/scribe/index.blade.php` (served live at `GET /docs`)

Verified by actually running the command: with no sandbox tenant seeded and only a bare sqlite
connection, generation still **completes** and produces valid OpenAPI/Postman files — route
paths, parameters, and validation rules are extracted from the code itself and don't need a live
database. What does depend on a working, seeded sandbox tenant is the **live response-call
examples** for tenant-scoped routes: without `sandbox:setup` having run against a real MySQL
connection, those specific example payloads fail to populate (each shows up as a `FAIL` line in
the command's output) while the rest of the route's documentation (path, params, rules) still
generates correctly.

Scribe's "try it out" response examples dispatch real requests against
`http://docs-sandbox.localhost` for tenant-scoped routes (configured via
`SCRIBE_TENANT_ID`/`SCRIBE_TENANT_SUBDOMAIN` in `config/scribe.php`) — **run `sandbox:setup`
first**, or those example calls will fail/404 and the generated docs will be missing live
response examples for tenant routes (route/parameter documentation is still generated either
way; only the live example payloads depend on the sandbox being seeded).

`/docs`, `/docs.openapi`, and `/docs.postman` are public but carry
`X-Robots-Tag: noindex, nofollow` (`App\Http\Middleware\NoIndexDocs`) — reachable by anyone with
the URL, not search-indexed. No auth wall by design, so Web/Mobile devs can reach them without a
session.

No secrets are baked into generated docs — `SCRIBE_TENANT_ID` is a fixed placeholder UUID, not a
real credential, and the auth section documents the header format
(`Authorization: Bearer {token}`) without embedding a real token.
