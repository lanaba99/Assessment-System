# Authentication

Two separate authentication tracks exist: **tenant users** (candidates, proctors, evaluators,
tenant admins) and **central admins** (super-admin, manages tenants themselves). They use
different Sanctum guards, different login routes, and are never interchangeable.

## Tenant-user login

```
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "candidate1@alpha-engine.example", "password": "..." }
```

- Public route (no token required). Must be called against a tenant subdomain
  (`https://<tenant>.localhost/api/v1/auth/login`) — see [TENANCY.md](TENANCY.md) for how the
  tenant is resolved from the host.
- The `email` field also accepts an `external_employee_id` value as a fallback lookup — the
  field name in the request body stays `email` either way.
- Rate limited: 5 failed attempts / 15 minutes, tracked independently per `(tenant, email)` **and**
  per `(tenant, IP)` — either bucket alone can block. See
  [ERRORS_AND_VALIDATION.md](ERRORS_AND_VALIDATION.md#rate-limiting).

Success response:

```json
{
  "data": {
    "status": "authenticated",
    "user_id": "uuid",
    "session_id": "uuid",
    "mfa_required": false,
    "authenticated_at": "2026-08-18T12:00:00+00:00",
    "token": "1|plaintext-sanctum-token"
  }
}
```

If MFA is enabled for the user, `status` is `"mfa_required"` instead, **no `token` is issued
yet**, and the client must call `POST /api/v1/auth/mfa/verify` with the returned `session_id`
(rate limited 5/15min) to complete login and receive the token.

Failure → `401`, `{"error":{"code": "...", "message": "..."}}` with one of:
`invalid_credentials`, `user_inactive`, `ip_not_allowed`.

### `session_id` vs the bearer token — track both

Login returns two different identifiers: the Sanctum bearer `token` (send as
`Authorization: Bearer <token>` on every request) and a separate `session_id` (an internal
session record, not a Sanctum concept). **`session_id` must be sent explicitly** in the body of
`POST /auth/logout` and `POST /auth/refresh` — it is not inferred from the bearer token. Store
both after login.

### Logout / refresh

```
POST /api/v1/auth/logout   { "session_id": "..." }   -> 204 No Content
POST /api/v1/auth/refresh  { "session_id": "..." }   -> { "data": { "token": "2|new...", "session_id": "..." } }
```

`refresh` **rotates** the token (deletes the old one, issues a new one) — it is not a
TTL-extension call, and there is no separate refresh-token grant type.

### Token expiration

**Tokens do not expire on a timer.** `config/sanctum.php` has `expiration => null`, and no login
path passes an explicit TTL. A token is only invalidated by explicit action: `logout`,
`refresh` (rotates it out), a password reset, or "revoke all sessions"
(`DELETE /api/v1/identity/sessions/all`). Clients should not build TTL-based silent-refresh
logic — instead, treat any `401 not_authenticated` response as "the token was explicitly
revoked, redirect to login."

## Central-admin login

```
POST /api/v1/admin/auth/login
{ "email": "superadmin@alpha-engine.example", "password": "..." }
```

- Only reachable on a **central domain** (`http://localhost/...` or `http://127.0.0.1/...`) —
  requesting it on any tenant subdomain 404s.
- Separate Sanctum guard/provider (`central` guard → `CentralAdminUser`, token ability
  `central-admin`) — a central-admin token cannot be used against tenant routes and a tenant
  token cannot be used against `/api/v1/admin/*`.
- No MFA flow, no `session_id` concept, no `throttle.login` middleware (this route isn't rate
  limited the way tenant login is).

Success response:

```json
{
  "data": {
    "scope": "central",
    "token": "1|plaintext-sanctum-token",
    "admin_user_id": "uuid",
    "email": "admin@example.com",
    "first_name": "Alpha",
    "last_name": "Administrator"
  }
}
```

Failure → `401 {"error":{"code":"invalid_credentials","message":"..."}}`.

Every other central admin endpoint (`/api/v1/admin/tenants/...`) additionally requires
`auth:sanctum` plus the caller being a real `CentralAdminUser` instance.

## Authorization header

```
Authorization: Bearer {token}
```

Same format for both tenant and central tokens — Sanctum resolves the correct guard from the
token owner automatically; clients never specify which guard they're using.

**A token minted on one tenant subdomain must keep being used against that same subdomain.**
The user row it belongs to lives in that tenant's own database — it is meaningless (and will
401/404 in confusing ways) against a different tenant's subdomain.

## Current-user info

```
GET /api/v1/identity/profile      -> { "data": { ...profile fields... } }
GET /api/v1/identity/permissions  -> { "data": { "permissions": ["exams.view", ...], "roles": ["Candidate"] } }
```

**There is no `GET /api/v1/identity/me`.** These two endpoints together are the "who am I" API —
build the current-user view from both, not from a single combined endpoint.

`PATCH /api/v1/identity/profile` is self-service only. Editable fields: `first_name`,
`last_name`, `external_employee_id`. Nothing authorization-related (role, permissions, tenant)
can be changed through this endpoint even if included in the body — the server strips it.

## Sessions

```
GET    /api/v1/identity/sessions          -> list the caller's own active sessions
DELETE /api/v1/identity/sessions/{id}     -> revoke one
DELETE /api/v1/identity/sessions/all      -> revoke all of the caller's sessions (and all tokens)
```

## Roles and permissions

Four system roles ship per tenant (seeded by `TenantMasterSeeder`) and **cannot be edited or
deleted via the API** (`RolePolicy` refuses on any `is_system_role = true` row):

| Role | Grants |
|---|---|
| **Tenant Admin** | Every permission in the system. |
| **Proctor** | `exam_sessions.start`, `exam_sessions.view`, `exam_sessions.manage`, `proctoring.view`, `proctoring.ingest`, `penalties.view` |
| **Technical Evaluator** | `questions.manage`, `categories.manage`, `competencies.manage`, `exams.manage`, `exams.view`, `exams.publish`, `grading.evaluate`, `grading.view`, `grading.publish`, `workflows.manage`, `eligibility.manage`, `eligibility.view`, `exam_sessions.view` |
| **Candidate** | `exams.view`, `exam_sessions.start` |

Note the deliberate separation of duties: only **Tenant Admin** gets `workflows.approve` by
default — a Technical Evaluator can *initiate* an approval workflow (`workflows.manage`) but
never approve their own or anyone else's.

Custom roles can be created with `POST /api/v1/roles` and assigned arbitrary permissions from
the same permission set. The full permission list (grouped by domain — users, roles, exams,
grading, exam_sessions, enrollments, proctoring, penalties, workflows, analytics, categories,
competencies, eligibility, cohorts, tenant, security_policies) is enumerated in
`database/seeders/TenantMasterSeeder.php` and mirrored in
`app/Domains/Identity/Enums/RoleName.php` for the role names themselves.

Gating pattern for the frontend to rely on: every Laravel Policy method name maps 1:1 to a
permission-name string (e.g. `RolePolicy::update` → `roles.update`), checked via
`GET /api/v1/identity/permissions`'s `permissions` array — if the ability the UI wants isn't in
that array, hide/disable the action; the server enforces the same check regardless.

## Seeded test accounts (sandbox / local dev only)

All seeded by `TenantMasterSeeder`, password `password` for every account:

- `tenant.admin@alpha-engine.example` — Tenant Admin
- `proctor@alpha-engine.example` — Proctor
- `evaluator@alpha-engine.example` — Technical Evaluator
- `candidate.1@alpha-engine.example` … `candidate.5@alpha-engine.example` — Candidate

See [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md) for the Docs Sandbox Tenant, which seeds the
same account shapes under a dedicated, isolated tenant meant for Web/Mobile development.
