# API Handoff — EAE Assessment Engine

This is the entry point for the Web and Mobile teams building clients against this backend.

**Ownership boundary:** this repository owns the API contract only — routes, request/response
shapes, auth, tenancy, grading, and results. Web and Mobile teams own their own SDK/client
layers, state management, and UI. Nothing in this repo builds or assumes a specific frontend
framework.

## Where to look

| Need | Go to |
|---|---|
| How to authenticate | [AUTHENTICATION.md](AUTHENTICATION.md) |
| How tenants/subdomains work, local Docker URLs | [TENANCY.md](TENANCY.md) |
| Response envelopes, error codes, rate limits, idempotency, pagination | [ERRORS_AND_VALIDATION.md](ERRORS_AND_VALIDATION.md) |
| The exam lifecycle end to end (build → enroll → take → grade) | [EXAM_FLOW.md](EXAM_FLOW.md) |
| `result_status` vs `publication_status`, certificates | [RESULTS_AND_CERTIFICATES.md](RESULTS_AND_CERTIFICATES.md) |
| Local setup, Docs Sandbox Tenant, generating OpenAPI/Postman | [ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md) |
| Full contract reference (versioning, envelopes, error codes, rate limits, idempotency) | [../API-CONTRACT.md](../API-CONTRACT.md) — the canonical source, kept in sync with the code |

## Machine-readable / interactive docs

Scribe generates these directly from the route definitions, so they never drift from the
actual code:

- **HTML docs:** `GET /docs`
- **OpenAPI 3.0.3 spec:** `GET /docs.openapi`
- **Postman v2.1.0 collection:** `GET /docs.postman`

All three are public (no auth wall) but carry `X-Robots-Tag: noindex, nofollow` — reachable,
not search-indexed. They cover all 27 route groups (Auth, ExamEngine, ExamSession, Grading,
QuestionBank, Certificates, Workflows, TenantSettings, etc.). See
[ENVIRONMENT_SETUP.md](ENVIRONMENT_SETUP.md) for how to generate them locally and point them at
a working sandbox.

A hand-written Postman collection also exists at `postman/identity-collection.json`, but it only
covers the Identity module (login/users/roles). For everything else, use `/docs.postman`.

## Identity endpoints — read this before building login/profile screens

The frontend uses exactly these two endpoints for "who am I":

- `GET /api/v1/identity/profile` — the caller's own profile fields.
- `GET /api/v1/identity/permissions` — the caller's roles and permission names.

**There is no `GET /api/v1/identity/me`.** Do not add or call one — it does not exist in this
API and is not planned. Use `profile` + `permissions` together to build a "current user" view.

## Roles, at a glance

Four system roles ship by default (immutable via the API — see AUTHENTICATION.md for the full
permission matrix): **Tenant Admin** (everything), **Proctor** (run/monitor sessions),
**Technical Evaluator** (author/grade content, publish exams and results), **Candidate**
(view exams, take sessions). Custom roles can be created via `POST /api/v1/roles`.

## Idempotency-Key — exact scope

`Idempotency-Key` is implemented on exactly 5 endpoints. Do not send it anywhere else — it is
silently ignored on routes that don't have the middleware, so treating an endpoint as idempotent
when it isn't is a client-side correctness bug waiting to happen. The full list, and which other
endpoints are merely "safe to retry" without the header, is in
[ERRORS_AND_VALIDATION.md](ERRORS_AND_VALIDATION.md#idempotency).

## What this handoff does *not* cover yet

- CI/CD, security/production hardening, and webhooks are separate, later stages of backend work
  and are not part of this handoff package. This handoff describes the API as it exists today.
