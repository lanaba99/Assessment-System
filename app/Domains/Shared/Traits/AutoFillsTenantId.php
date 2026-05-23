<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

/**
 * Auto-fills `tenant_id` on creation from the currently-bound tenant.
 *
 * Under per-database tenancy the `tenant_id` column is redundant within a
 * tenant DB — the whole schema belongs to one tenant — but keeping it
 * populated is cheap defense-in-depth: if a connection ever leaks across
 * tenant boundaries, rows with the wrong tenant_id will fail FK / scope
 * checks instead of silently mixing data.
 *
 * Eloquent boots any trait method named `boot{TraitName}` automatically.
 */
trait AutoFillsTenantId
{
    public static function bootAutoFillsTenantId(): void
    {
        static::creating(function ($model): void {
            if ($model->tenant_id !== null && $model->tenant_id !== '') {
                return;
            }

            if (! function_exists('tenant')) {
                return;
            }

            $tenant = tenant();
            if ($tenant === null) {
                return;
            }

            $model->tenant_id = $tenant->getKey();
        });
    }
}
