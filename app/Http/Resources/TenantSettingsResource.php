<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'organization_name' => (string) $this->organization_name,
            'organization_type' => $this->organization_type,
            'primary_contact_email' => (string) $this->primary_contact_email,
            'primary_contact_phone' => $this->primary_contact_phone,
        ];
    }
}