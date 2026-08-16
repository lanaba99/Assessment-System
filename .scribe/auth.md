# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {SANCTUM_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtain a tenant-scoped Sanctum token using POST /api/v1/auth/login. Use the same tenant subdomain for all authenticated requests.
