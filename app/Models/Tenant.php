<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'deployment_config' => 'array',
        'feature_flags' => 'array',
        'security_policies' => 'array',
        'max_concurrent_users' => 'integer',
        'max_storage_quota_mb' => 'integer',
        'contract_start_date' => 'datetime',
        'contract_end_date' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'subdomain',
            'organization_name',
            'organization_type',
            'primary_contact_email',
            'primary_contact_phone',
            'deployment_config',
            'deployment_mode',
            'data_residency_location',
            'max_concurrent_users',
            'max_storage_quota_mb',
            'feature_flags',
            'status',
            'security_policies',
            'contract_start_date',
            'contract_end_date',
            'suspended_at',
        ];
    }
}
