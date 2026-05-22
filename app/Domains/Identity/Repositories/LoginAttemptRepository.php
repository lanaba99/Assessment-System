<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\LoginAttempt;

class LoginAttemptRepository
{
    public function __construct(
        private readonly LoginAttempt $model,
    ) {
    }

    public function recordSuccess(
        string $tenantId,
        string $emailAttempted,
        string $ipAddress,
        ?string $deviceInfo,
    ): LoginAttempt {
        return $this->model->newQuery()->create([
            'tenant_id' => $tenantId,
            'email_attempted' => $emailAttempted,
            'ip_address' => $ipAddress,
            'is_successful' => true,
            'failure_reason' => null,
            'device_info' => $deviceInfo,
            'attempted_at' => now(),
        ]);
    }

    public function recordFailure(
        string $tenantId,
        string $emailAttempted,
        string $ipAddress,
        string $failureReason,
        ?string $deviceInfo,
    ): LoginAttempt {
        return $this->model->newQuery()->create([
            'tenant_id' => $tenantId,
            'email_attempted' => $emailAttempted,
            'ip_address' => $ipAddress,
            'is_successful' => false,
            'failure_reason' => $failureReason,
            'device_info' => $deviceInfo,
            'attempted_at' => now(),
        ]);
    }

    public function countRecentFailuresForEmail(
        string $tenantId,
        string $emailAttempted,
        int $withinMinutes,
    ): int {
        $threshold = now()->subMinutes($withinMinutes);

        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('email_attempted', $emailAttempted)
            ->where('is_successful', false)
            ->where('attempted_at', '>=', $threshold)
            ->count();
    }
}
