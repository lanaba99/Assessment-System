# Errors, Validation, Rate Limiting, Idempotency, Pagination

This is the shared contract every endpoint follows. See also
[../API-CONTRACT.md](../API-CONTRACT.md) for the canonical, code-verified reference this file
expands on.

## Response envelopes

**Single resource:**
```json
{ "data": { "...": "..." } }
```

**List / paginated:**
```json
{
  "data": [ { "...": "..." } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

**Error:**
```json
{ "error": { "code": "snake_case_code", "message": "Human-readable message" } }
```
(sometimes with extra fields alongside `code`/`message` — see the table below).

**The one exception:** `204 No Content` responses (logout, delete/deactivate/revoke endpoints)
have **no body at all** — don't try to parse JSON out of a 204.

## Status codes — with a real trigger for each

| Status | Example trigger | Error code |
|---|---|---|
| **401** | Bad login credentials | `invalid_credentials` |
| **401** | Missing/invalid/expired bearer token on any protected route | `not_authenticated` |
| **403** | Caller's role lacks the required permission (e.g. non-admin calling `PATCH /roles/{id}`) | `forbidden` |
| **404** | Resource doesn't exist, or exists in a different tenant | `*_not_found` (e.g. `user_not_found`, `workflow_not_found`, `result_not_ready`) |
| **409** | Conflicting state (e.g. inviting an email that already has a user; reusing an `Idempotency-Key` with a different body) | `user_already_exists`, `idempotency_key_reused`, `enrollment_already_exists`, `invalid_exam_state`, `stale_version_lock` |
| **422** | FormRequest validation failure | `validation_failed` (+ `fields` map) |
| **422** | Weak password | `password_policy_violation` (+ `violations` array) |
| **422** | Domain-rule violation (e.g. publishing a result that isn't final yet, enrollment window not active) | `result_not_finalized`, `eligibility_violation`, `penalty_processing_pending`, `workflow_not_approved`, `exam_duration_exceeded` |
| **429** | Rate limit exceeded | `rate_limited` (general) / `too_many_login_attempts` (login-specific) |

### Validation error shape

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The email field is required. (and 1 more error)",
    "fields": {
      "email": ["The email field is required."],
      "password": ["The password field is required."]
    }
  }
}
```

**Note the deviation from vanilla Laravel:** field errors are nested under `error.fields`, not a
top-level `errors` key. If you're used to Laravel's default `{"message":...,"errors":{...}}`
shape from other projects, this API does not use it.

### Certificate verification is a deliberate exception

`GET /api/v1/certificates/verify/{token}` does **not** use the error envelope for a "not found"
result — it returns `{"data": {"valid": false}}` with a `404` status instead of
`{"error": {...}}`. This is the one endpoint that doesn't follow the standard error shape; handle
it explicitly rather than assuming every 404 is `{"error": {...}}`.

## Rate limiting

| Scope | Limit | Keyed by |
|---|---|---|
| Every tenant API route (`throttle:tenant-api`) | 240 requests / minute | `(tenant, user_id-or-ip)` |
| `POST /auth/login` (`throttle.login`) | 5 failed attempts / 15 minutes | `(tenant, email)` **and** `(tenant, IP)` independently — either can block |
| `POST /auth/mfa/verify`, `POST /auth/password/reset`, `POST /auth/accept-invite` | 5 attempts / 15 minutes | Laravel's default `user_id|ip` (not tenant-scoped) |
| `POST /auth/password/forgot` | **No rate limit applied.** | — |

`Retry-After` (seconds) is always present on a 429. `X-RateLimit-Limit` / `X-RateLimit-Remaining`
are present on the general `tenant-api`-limited responses (Laravel's framework-default throttle
headers), **but not on `/auth/login`** specifically — that route's throttle is hand-written and
only sets `Retry-After`.

## Idempotency

`Idempotency-Key` (any client-generated UUID header) is implemented on **exactly these 5
routes** — this is a closed list, do not assume any other endpoint honors the header:

- `POST /exam-sessions/{sessionId}/proctor-events`
- `POST /workflows/{workflowId}/approve`
- `POST /workflows/{workflowId}/reject`
- `POST /certificates/{certificateId}/regenerate`
- `POST /certificates/{certificateId}/revoke`

Mechanics: first call with a given key executes and caches the response (status + body) for 24h,
keyed on `(tenant, actor, route, key)`. A replay with the **same** key and **same** request body
returns the cached response verbatim with an `X-Idempotent-Replay: true` header — the handler
does not re-run. A replay with the same key but a **different** body → `409
idempotency_key_reused`. 5xx responses are never cached.

**Two more endpoints are safe to retry without the header**, because they're idempotent at the
domain-logic level rather than via this middleware — do not send `Idempotency-Key` to them (it
would just be ignored):
- `POST /exam-sessions` (starting a session that's already in progress returns that same session)
- `POST /exam-sessions/{sessionId}/result/publish` (publishing an already-published result is a
  safe no-op)

## Pagination, filtering, sorting

- **Page:** standard `?page=` query param (Laravel's paginator default).
- **Page size:** `?per_page=`, default `15`, capped at `100` on nearly every list endpoint. One
  known inconsistency: `GET /workflows/{workflowId}/history` accepts `per_page` but does **not**
  cap it at 100 — don't rely on that cap universally.
- **Filtering:** no generic `filter[x]=y` bracket convention. Each list endpoint defines its own
  flat, named query params. Examples:
  - `GET /questions?category_id=&bloom_level=&type=&per_page=`
  - `GET /exam-sessions?status=&exam_id=&candidate_id=&per_page=`
  - `GET /workflows?status=&workflow_type=&resource_type=&resource_id=&per_page=` — note
    non-approvers are always additionally scoped server-side to their own initiated workflows
    regardless of query params; you cannot widen that from the client.
- **Sorting:** **there is no `sort=`/`order=` param anywhere in this API.** Results come back in
  whatever order the underlying query uses (frequently `created_at desc`). If a screen needs
  client-controlled sorting, that's a gap to raise with the backend team, not something to build
  against speculatively.
