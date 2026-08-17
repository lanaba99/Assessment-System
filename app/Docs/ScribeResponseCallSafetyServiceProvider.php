<?php

declare(strict_types=1);

namespace App\Docs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * Makes Scribe's response calls safe to run against real tenant data:
 * makes the transaction rollback actually hold for tenant-scoped routes,
 * stops tenancy state from leaking between simulated requests, and
 * redacts live secrets out of the captured response examples.
 *
 * Problem 1 — mutations weren't rolled back. Scribe's ResponseCalls
 * strategy (see `database_connections_to_transact` in config/scribe.php)
 * begins a transaction on the 'tenant' connection *before* dispatching
 * each simulated request, and rolls it back afterwards. But
 * InitializeTenancyBySubdomain — and therefore stancl/tenancy's
 * DatabaseTenancyBootstrapper, which repoints `database.connections.tenant`
 * at the real per-tenant database — only runs *inside* that dispatched
 * request. By the time tenancy boots, Scribe has already opened its
 * transaction on a stale, unconfigured 'tenant' connection;
 * DatabaseTenancyBootstrapper purges and replaces that connection object,
 * silently discarding the transaction. Scribe's later rollback() then
 * targets a connection with nothing open on it, so mutations from
 * tenant-scoped response calls (e.g. login attempts, exam session inserts)
 * would otherwise persist for real — this was verified empirically (a
 * probe insert survived Scribe's own rollback) before writing this fix.
 * Fixed by redoing the transaction at the *correct* moment (right after
 * tenancy finishes bootstrapping, on the real tenant connection), rolling
 * it back right after each response call, and ending tenancy so the next
 * simulated request starts clean instead of inheriting the previous one's
 * tenant context (which would otherwise also leak into central-route
 * response calls run later in the same `scribe:generate` process).
 *
 * Problem 2 — live secrets leaking into public docs. Once response calls
 * against the real seeded tenant started succeeding (see SCRIBE_AUTH_KEY in
 * config/scribe.php's 'auth' block), endpoints that issue credentials —
 * e.g. `POST /api/v1/auth/refresh` — started returning a genuine, usable
 * Sanctum token in their captured example response, which `scribe:generate`
 * would otherwise bake verbatim into the publicly-reachable /docs output.
 * Fixed by redacting known-sensitive field names (token/password/secret/
 * api_key/private_key, case-insensitive) out of every response call's
 * captured JSON body before Scribe stores it as a docs example.
 *
 * Only active while `scribe:generate` itself is running via console — it
 * has no effect on the app in any other context (HTTP requests, tests,
 * queue workers, other artisan commands).
 */
class ScribeResponseCallSafetyServiceProvider extends ServiceProvider
{
    /**
     * The static `database.connections.tenant` array from config/database.php,
     * as it exists before any tenant has ever been initialized. Scribe's own
     * `database_connections_to_transact` cleanup (see
     * DatabaseTransactionHelpers::endDbTransaction()) resolves
     * `app('db')->connection('tenant')` *outside* of its own try/catch, so if
     * `tenancy()->end()` were left to remove the 'tenant' config key entirely
     * (which is its normal behaviour — see DatabaseManager::purgeTenantConnection()),
     * Scribe's next connection() call would throw an uncaught
     * InvalidArgumentException and abort doc generation. Restoring this
     * snapshot after every response call keeps a 'tenant' connection always
     * resolvable, exactly as it was before the first response call ever ran.
     */
    private array $baselineTenantConnection;

    /**
     * Response field names (matched case-insensitively as a substring) whose
     * values are replaced with a fixed placeholder before Scribe stores a
     * response call's captured JSON as a docs example. Covers the field
     * names actually used across the schema (see database/migrations/tenant)
     * for tokens, password hashes, MFA/API secrets, etc.
     */
    private const REDACTED_FIELD_PATTERNS = ['token', 'password', 'secret', 'api_key', 'private_key'];

    private const REDACTED_PLACEHOLDER = '{REDACTED}';

    public function boot(): void
    {
        if (! $this->runningScribeGenerate()) {
            return;
        }

        $this->baselineTenantConnection = config('database.connections.tenant');

        // Defensive: if a previous response call's tenancy wasn't ended
        // cleanly (e.g. the simulated request threw before afterResponseCall
        // could run), clean it up before starting the next call rather than
        // let tenant context bleed across response calls.
        Scribe::beforeResponseCall(function (): void {
            $this->rollbackAndEndTenancy();
        });

        Event::listen(TenancyBootstrapped::class, function (): void {
            DB::connection('tenant')->beginTransaction();
        });

        Scribe::afterResponseCall(function ($request, $endpointData, $response): void {
            $this->rollbackAndEndTenancy();
            $this->redactSensitiveFields($response);
        });
    }

    /**
     * Mutates the captured response's JSON body in place, replacing any
     * value whose key looks sensitive (see REDACTED_FIELD_PATTERNS) with a
     * fixed placeholder. `$response` is the Symfony/Illuminate Response
     * object Scribe is about to read `getContent()` from immediately after
     * this hook returns (see ResponseCalls::makeResponseCall()), so setting
     * new content here is what actually reaches the generated docs.
     */
    private function redactSensitiveFields($response): void
    {
        if (! is_object($response) || ! method_exists($response, 'getContent')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return;
        }

        $redacted = $this->redactRecursively($decoded);

        if (method_exists($response, 'setContent')) {
            $response->setContent(json_encode($redacted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function redactRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactRecursively($value);

                continue;
            }

            if (is_string($key) && $this->looksSensitive($key)) {
                $data[$key] = self::REDACTED_PLACEHOLDER;
            }
        }

        return $data;
    }

    private function looksSensitive(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach (self::REDACTED_FIELD_PATTERNS as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function rollbackAndEndTenancy(): void
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return;
        }

        try {
            DB::connection('tenant')->rollBack();
        } catch (\Throwable) {
            // No active transaction on the current 'tenant' connection —
            // nothing to roll back.
        }

        tenancy()->end();

        // See $baselineTenantConnection docblock: keep 'tenant' resolvable.
        config(['database.connections.tenant' => $this->baselineTenantConnection]);
    }

    private function runningScribeGenerate(): bool
    {
        return $this->app->runningInConsole()
            && in_array('scribe:generate', $_SERVER['argv'] ?? [], true);
    }
}
