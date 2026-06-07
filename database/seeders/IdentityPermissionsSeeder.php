<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IdentityPermissionsSeeder extends Seeder
{
    /**
     * Canonical Identity-domain permissions referenced by UserPolicy,
     * RolePolicy, and SecurityPolicyPolicy. Keep this list in lockstep
     * with the policies — anything checked there must be seeded here.
     */
    private const PERMISSIONS = [
        'users.viewAny',
        'users.view',
        'users.create',
        'users.update',
        'users.deactivate',
        'users.resetPassword',
        'roles.viewAny',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign',
        'security_policies.view',
        'security_policies.update',
        'cohorts.view',
        'cohorts.manage',
        'cohorts.members.manage',
        'exam_sessions.start',
        'exam_sessions.view',
        'exam_sessions.manage',
        'proctoring.ingest',
        'proctoring.view',
    ];

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId === null) {
            $this->command?->warn('IdentityPermissionsSeeder skipped — no tenant context. Run via `tenants:seed` to seed tenant databases.');

            return;
        }

        foreach (self::PERMISSIONS as $name) {
            [$resource, $action] = $this->splitName($name);

            Permission::query()->updateOrCreate(
                ['permission_name' => $name],
                [
                    'permission_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'resource_type' => $resource,
                    'action_type' => $action,
                    'description' => "Permission to {$action} {$resource}.",
                    'created_at' => now(),
                ],
            );
        }

        $this->command?->info('Seeded ' . count(self::PERMISSIONS) . " Identity permissions for tenant {$tenantId}.");
    }

    private function resolveTenantId(): ?string
    {
        if (function_exists('tenant') && tenant() !== null) {
            return (string) tenant()->getKey();
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = explode('.', $name, 2);

        return [$parts[0], $parts[1] ?? 'manage'];
    }
}
