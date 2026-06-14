<?php

declare(strict_types=1);

namespace App\Domains\Central\Services;

use App\Models\Tenant;
use Illuminate\Support\Collection;

class TenantManagementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create([
            'organization_name' => $data['organization_name'],
            'organization_type' => $data['organization_type'] ?? 'enterprise',
            'primary_contact_email' => $data['primary_contact_email'],
            'primary_contact_phone' => $data['primary_contact_phone'] ?? null,
            'deployment_config' => $data['deployment_config'] ?? [],
            'deployment_mode' => $data['deployment_mode'] ?? 'multi_database',
            'data_residency_location' => $data['data_residency_location'] ?? null,
            'max_concurrent_users' => $data['max_concurrent_users'] ?? 500,
            'max_storage_quota_mb' => $data['max_storage_quota_mb'] ?? 51200,
            'feature_flags' => $data['feature_flags'] ?? [],
            'status' => 'active',
            'security_policies' => $data['security_policies'] ?? [],
            'contract_start_date' => now(),
            'contract_end_date' => now()->addYear(),
        ]);

        if (! empty($data['domain'])) {
            $tenant->domains()->create(['domain' => (string) $data['domain']]);
        }

        return $tenant->fresh(['domains']);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function list(): Collection
    {
        return Tenant::query()->orderBy('organization_name')->get();
    }

    public function find(string $tenantId): ?Tenant
    {
        return Tenant::query()->with('domains')->find($tenantId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->forceFill(array_intersect_key($data, array_flip([
            'organization_name',
            'organization_type',
            'primary_contact_email',
            'primary_contact_phone',
            'deployment_config',
            'status',
            'feature_flags',
            'security_policies',
            'max_concurrent_users',
            'max_storage_quota_mb',
        ])))->save();

        return $tenant->fresh(['domains']);
    }
}
