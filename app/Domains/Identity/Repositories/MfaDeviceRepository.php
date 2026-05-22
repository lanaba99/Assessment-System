<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\MfaDevice;
use Illuminate\Support\Collection;

class MfaDeviceRepository
{
    public function __construct(
        private readonly MfaDevice $model,
    ) {
    }

    public function findById(string $tenantId, string $deviceId): ?MfaDevice
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($deviceId)
            ->first();
    }

    /**
     * @return Collection<int, MfaDevice>
     */
    public function listForUser(string $tenantId, string $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * @return Collection<int, MfaDevice>
     */
    public function listVerifiedForUser(string $tenantId, string $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_verified', true)
            ->get();
    }

    public function create(string $tenantId, string $userId, array $attributes): MfaDevice
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['user_id'] = $userId;
        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return $this->model->newQuery()->create($attributes);
    }

    public function markVerified(MfaDevice $device): MfaDevice
    {
        $device->fill([
            'is_verified' => true,
            'verified_at' => now(),
        ])->save();

        return $device;
    }

    public function recordLastUse(MfaDevice $device): MfaDevice
    {
        $device->fill(['last_used_at' => now()])->save();

        return $device;
    }

    public function delete(string $tenantId, string $userId, string $deviceId): bool
    {
        $deleted = $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereKey($deviceId)
            ->delete();

        return $deleted > 0;
    }
}
