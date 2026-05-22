<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class MfaEnrollmentResult
{
    /**
     * @param  array<int, string>|null  $backupCodes  one-time plaintext backup codes returned ONCE
     */
    public function __construct(
        public string $deviceId,
        public string $userId,
        public string $deviceType,
        public ?string $provisioningUri,
        public ?array $backupCodes,
        public bool $requiresVerification = true,
    ) {
    }
}
