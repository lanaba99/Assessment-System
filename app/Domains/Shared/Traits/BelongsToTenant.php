<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for tenant binding on tenant-scoped models.
 *
 * Under stancl/tenancy (database-per-tenant) every row already lives in the
 * tenant's own database, so the `tenant_id` column + this scope are
 * defense-in-depth — not the primary isolation boundary (the connection is).
 *
 * Centralising the behaviour here is the audit's "Option A": trust the
 * connection, apply the tenant filter in exactly one place, and never let a
 * repository hand-write `where('tenant_id', ...)` again. Two responsibilities:
 *
 *   1. Auto-fill `tenant_id` on insert from the bound tenant.
 *   2. Constrain every read to the bound tenant via a global scope.
 *
 * Eloquent boots any trait method named `boot{TraitName}` automatically.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('tenant_id'))) {
                return;
            }

            $tenantId = self::currentTenantId();

            if ($tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = self::currentTenantId();

            if ($tenantId !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('tenant_id'),
                    $tenantId,
                );
            }
        });
    }

    /**
     * The currently-bound tenant's key, or null when running outside a tenant
     * context (console, migrations, central domain) — in which case the scope
     * is a no-op and callers are responsible for their own constraints.
     */
    private static function currentTenantId(): ?string
    {
        if (! function_exists('tenant')) {
            return null;
        }

        $tenant = tenant();

        if ($tenant === null || $tenant->getKey() === null) {
            return null;
        }

        return (string) $tenant->getKey();
    }
}
