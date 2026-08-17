# Introduction

Tenant-scoped REST API for the Enterprise Assessment Engine (EAE) SaaS platform.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

    This documentation aims to provide all the information you need to work with our API.

    <aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
    You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

    ## Response envelope
    Every response is either `{"data": ...}` (success) or `{"error": {"code", "message"}}` (failure).
    Error `code` values are stable and safe to match on programmatically; `message` text may change between releases.

    ## Rate limiting
    All tenant API requests are limited to **240 requests/minute**, keyed per authenticated user (or IP if unauthenticated).
    A handful of auth endpoints (login, MFA verify, password reset, accept-invite) have their own stricter 5-attempts/15-minute limit.
    Exceeding either returns `429` with a `Retry-After` header (seconds).

    ## Idempotency
    A small set of POST endpoints (see their individual docs below for the `Idempotency-Key` header) support safe retries:
    send a client-generated unique key, and a retried request with the same key + body replays the original response
    instead of re-executing. Reusing a key with a different body returns `409`.

    Full details: see `API-CONTRACT.md` in the repository root.

