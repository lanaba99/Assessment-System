# Tenancy

Multi-tenant via [stancl/tenancy](https://tenancyforlaravel.com/), **subdomain-based**,
**one MySQL database per tenant**.

## How a tenant is resolved

The tenant is resolved from the **leftmost label of the request host** — nothing else. There is
no tenant header and no path-based tenancy in this API.

```
https://alpha-engine.localhost/api/v1/...   -> tenant "alpha-engine"
https://docs-sandbox.localhost/api/v1/...   -> tenant "docs-sandbox" (the Docs Sandbox Tenant)
https://localhost/api/v1/admin/...          -> central/landlord, no tenant at all
```

`config/tenancy.php`:
```php
'central_domains' => ['127.0.0.1', 'localhost'],
```

A host must end in `.localhost` (or `.127.0.0.1`) to resolve as a tenant subdomain at all — a
request straight to `localhost`/`127.0.0.1` is always central, never a tenant.

## Local Docker hostnames

Most OS/browser resolvers treat any `*.localhost` hostname as `127.0.0.1` automatically (RFC
6761) — so `http://alpha-engine.localhost/api/v1/...` should work against your local Sail stack
with **no `/etc/hosts` edit needed**. This is not universal, though — some mobile emulators and
corporate proxies don't honor RFC 6761. If a mobile client can't resolve `*.localhost`, that's an
emulator/network config issue, not a backend one — flag it early rather than assuming the API
needs a header-based tenancy mode (it doesn't have one).

There is no `X-Tenant-ID`-style header the client can send instead — the subdomain is the only
resolution mechanism for regular API traffic.

## Central routes vs tenant routes

| | Central (`routes/api.php`) | Tenant (`routes/tenant.php`) |
|---|---|---|
| URL shape | `/api/v1/admin/...` (+ `/api/ping`) | `/api/v1/...` |
| Reachable from | Central domain only (`localhost`/`127.0.0.1`) — enforced by `EnsureCentralDomain` | Tenant subdomains only — enforced by `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains` |
| Auth guard | `central` (Sanctum, `CentralAdminUser`) | `api` (Sanctum, tenant `User`) |
| Database | Central/landlord DB | That tenant's own database |
| Used for | Managing tenants themselves (create/suspend/configure) | Everything a candidate/proctor/evaluator/tenant-admin does — exams, sessions, grading, results |

A request can never accidentally cross between the two: hitting a tenant route on a central
domain, or an admin route on a tenant subdomain, is a hard 404/mismatch, not a soft fallback.

## Database isolation

`deployment_mode: multi_database` — each tenant gets its own physical database, named
`tenant_<tenant-uuid>`. Once `InitializeTenancyBySubdomain` resolves the tenant, stancl/tenancy's
`DatabaseTenancyBootstrapper` swaps Laravel's default DB connection to that tenant's database for
the rest of the request — every subsequent query (users, exams, questions, sessions, results...)
is automatically scoped to that one database. A bug in application code cannot leak rows across
tenants the way a `WHERE tenant_id = ?` scope could be forgotten — the *connection itself* only
ever points at one tenant's data per request.

Cache, filesystem, and queue are tenant-scoped too (`CacheTenancyBootstrapper`,
`FilesystemTenancyBootstrapper`, `QueueTenancyBootstrapper`), so none of those layers leak
between tenants either.

## What this means for client development

- **Never hardcode a tenant subdomain** in a shared client build — it must be configurable per
  environment/customer.
- A bearer token is only valid against the exact subdomain it was issued on.
- For local/sandbox development against a stable, reproducible tenant, use the **Docs Sandbox
  Tenant** at `docs-sandbox.localhost` — see [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md).
