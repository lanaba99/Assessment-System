# API Contract — Versioning, Envelopes, Errors

Phase 5 (Public API Foundation). This document is the source of truth for
how the public API is shaped and how it evolves. It codifies conventions
already in use across the codebase — it does not introduce a rewrite.

## 1. Versioning

- The API is versioned in the URL path: `/api/v1/...`.
- All tenant-scoped endpoints live under `routes/tenant.php`, resolved via
  `InitializeTenancyBySubdomain`. Central/landlord endpoints live under
  `routes/api.php` at `/api/v1/admin/...`, resolved via `EnsureCentralDomain`.
- **What does NOT require a version bump (`v2`):**
  - Adding a new endpoint.
  - Adding a new optional field to a request body or query string.
  - Adding a new field to a response payload (`data` object gains a key).
  - Adding a new enum value to a field that consumers are expected to treat
    as an open set (e.g. `event_type` on proctoring events — already
    open-ended, see `LogProctorEventRequest`).
  - Loosening a validation rule (making a previously-required field optional,
    widening an accepted range).
- **What DOES require a version bump:**
  - Removing or renaming a response field.
  - Changing a field's type or semantic meaning (e.g. `status` changing from
    a string enum to an object).
  - Removing an endpoint or changing its HTTP method.
  - Tightening validation in a way that would reject previously-valid
    requests (new required field, narrower enum on `severity_level`-style
    closed fields).
  - Changing default behavior in a way that changes the response for
    existing callers without them changing anything (e.g. changing what
    `GET /exam-sessions` returns with no query params).
- When a `v2` is introduced, `v1` is not deleted immediately — it is marked
  deprecated (see §4) and both versions are served in parallel for a
  announced window.

## 2. Response envelope

Every JSON response uses one of exactly two shapes:

**Success:**
```json
{ "data": { ... } }
```
or, for paginated/list endpoints:
```json
{ "data": [ ... ], "meta": { ... } }
```

**Error:**
```json
{ "error": { "code": "some_snake_case_code", "message": "Human-readable message" } }
```
Domain-specific errors may add extra fields alongside `code`/`message`
(e.g. `password_policy_violation` includes a `violations` array,
`category_not_empty` includes `has_children`/`has_questions`) — these
extra fields are additive and do not break the contract.

There is no third shape. A `204 No Content` response (e.g. `DELETE`
endpoints) has no body at all — that's the only exception.

**Enforcement:**
- `app/Http/Concerns/BuildsApiResponses.php` is the shared trait every
  controller uses to build both shapes (`successResponse()`,
  `errorResponse()`/`error()`). New controllers should use it instead of
  hand-rolling `new JsonResponse([...])`.
- `bootstrap/app.php` registers a set of global exception renderers as a
  safety net — `ValidationException`, `AuthenticationException`,
  `AuthorizationException`, `ModelNotFoundException`,
  `NotFoundHttpException`, and a generic `Throwable` fallback all render
  into the `{"error": {...}}` shape automatically, even if a controller
  never explicitly catches them. Domain-specific exceptions (e.g.
  `InvalidExamStateException`, `PasswordPolicyViolationException`) are
  still handled first, above the generic fallbacks, so they keep their
  richer payloads.

## 3. Error codes

- `code` is always `snake_case`, stable, and meant to be programmatically
  matched by API consumers (not just displayed). Never change an existing
  `code` value without a version bump.
- `message` is human-readable and MAY change wording between releases
  without a version bump — consumers should not string-match `message`.
- Standard codes from the global fallback layer:
  | Code | HTTP status | Meaning |
  |---|---|---|
  | `validation_failed` | 422 | Request body/query failed validation; see `fields` |
  | `not_authenticated` | 401 | No/invalid Sanctum token |
  | `forbidden` | 403 | Authenticated but not authorized (policy denied) |
  | `not_found` | 404 | Resource or route not found |
  | `http_error` | varies | Any other HTTP-flavored exception |
  | `rate_limited` | 429 | Exceeded the 240/min tenant-api limit — see §5 |
  | `too_many_login_attempts` | 429 | Exceeded the auth-specific throttle — see §5 |
  | `idempotency_key_reused` | 409 | Same `Idempotency-Key` sent with a different request body — see §6 |
  | `internal_error` | 500 | Unhandled exception (production only; debug mode shows real trace) |

## 4. Deprecation policy (for future `v2`)

- A deprecated endpoint/version returns a `Deprecation` and `Sunset` HTTP
  header (RFC 8594) once `v2` ships, pointing to the migration doc.
- Minimum deprecation window before removal: to be set per the project's
  actual release cadence — not yet applicable, no `v2` exists.

## 5. Rate limiting

- Every tenant API request is subject to a global limit of **240 requests
  per minute**, keyed by `(tenant, actor)` — authenticated requests key on
  the user id; unauthenticated requests fall back to client IP. The same
  user id or IP in two different tenants never shares a bucket.
- Registered as the `tenant-api` named limiter
  (`app/Providers/AppServiceProvider.php`) and applied to the whole
  `routes/tenant.php` group.
- A handful of auth-adjacent endpoints have their own **stricter**,
  purpose-specific limits layered on top (these are not replaced by the
  240/min ceiling):
  | Endpoint | Limit |
  |---|---|
  | `POST /auth/login` | 5 failed attempts / 15 min, per (tenant, email) AND per (tenant, IP) |
  | `POST /auth/mfa/verify` | 5 / 15 min |
  | `POST /auth/password/reset` | 5 / 15 min |
  | `POST /auth/accept-invite` | 5 / 15 min |
- Exceeding a limit returns `429 Too Many Requests` in the standard error
  envelope:
```json
  { "error": { "code": "rate_limited", "message": "Too many requests. Please slow down and try again shortly." } }
```
  with a `Retry-After` header (seconds) — clients should back off and
  retry after that interval rather than immediately re-requesting.
- Login-specific throttling returns `code: "too_many_login_attempts"`
  instead, with an additional `retry_after_seconds` field in the error
  body (kept distinct from the generic `rate_limited` code since it also
  needs to communicate account-lockout semantics, not just request pacing).

## 6. Idempotency

- Applies only to specific unsafe (`POST`) endpoints where a duplicated
  submission would cause a real problem — **not** applied globally.
  Currently tagged with the `idempotent` middleware:
  - `POST /exam-sessions/{sessionId}/proctor-events`
  - `POST /workflows/{workflowId}/approve`
  - `POST /workflows/{workflowId}/reject`
  - `POST /certificates/{certificateId}/regenerate`
  - `POST /certificates/{certificateId}/revoke`
- **Not** tagged, because they already have equivalent idempotency at the
  domain/service layer:
  - `POST /exam-sessions` (start) — returns the candidate's existing
    in-progress session instead of creating a duplicate.
  - `POST /exam-sessions/{sessionId}/result/publish` — publishing an
    already-published result is a safe no-op at the service layer.
- **How it works:** send an `Idempotency-Key` header (any client-generated
  unique string — a UUID is recommended) with the request.
  - **No header** → request executes normally every time; no caching, no
    replay. Fully backward compatible — omitting the header is safe, just
    not idempotent.
  - **Header + first use** → executes normally; if the response status is
    under 500, the response is cached against
    `(tenant, actor, route, key)` for **24 hours**.
  - **Header + replay, same body** → the original cached response is
    returned verbatim, the handler does **not** re-execute. Response
    carries `X-Idempotent-Replay: true`.
  - **Header + replay, different body** → `409 Conflict`:
```json
    { "error": { "code": "idempotency_key_reused", "message": "This Idempotency-Key was already used with a different request body. Use a new key for a new logical request." } }
```
    This is almost always a client bug — the key must be regenerated per
    logical operation, not reused across different requests.
  - A `5xx` response is never cached, so a transient server error doesn't
    get "locked in" for the full replay window — the client's next
    identical retry executes fresh rather than replaying the failure.
- **Recommendation for the frontend/SDK:** generate a new UUID per logical
  user action (e.g. per "tap Approve" click) and reuse it only when
  retrying that exact same action after a timeout or dropped connection —
  never reuse a key across two different actions.

## 7. Token scopes / abilities

- **Current state: not implemented.** Every Sanctum token issued
  (`createToken('api')`, `createToken('central-admin')`) carries the
  default blanket `['*']` ability — there is no per-token scoping today.
  Authorization is enforced entirely server-side per request via the
  existing role/permission system (policies + `AuthorizationService`), not
  via what the bearer token itself is allowed to do.
- This means a leaked token has the same effective reach as the user
  account that issued it — no way to mint a narrower-scoped token (e.g.
  "read-only", "proctoring-events-only") for a specific integration.
- **Deliberately out of scope for Phase 6.** Retrofitting abilities onto
  every existing `createToken()` call site plus every downstream
  permission check is a materially larger change than rate
  limiting/idempotency, and touches authentication paths already
  hardened in Phase 1–4. Recommended as a Phase 11 (Secure Operations and
  Production) item instead, evaluated before go-live.