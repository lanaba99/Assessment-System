<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Tenant $resource
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource;

        return [
            'id' => (string) $tenant->id,
            'organization_name' => (string) $tenant->organization_name,
            'organization_type' => $tenant->organization_type,
            'primary_contact_email' => (string) $tenant->primary_contact_email,
            'status' => (string) $tenant->status,
            'domains' => $tenant->relationLoaded('domains')
                ? $tenant->domains->pluck('domain')->all()
                : [],
            'feature_flags' => $tenant->feature_flags,
            'max_concurrent_users' => $tenant->max_concurrent_users,
            'max_storage_quota_mb' => $tenant->max_storage_quota_mb,
        ];
    }
}
