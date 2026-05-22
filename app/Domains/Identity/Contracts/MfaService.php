<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\DTOs\MfaEnrollmentResult;

/**
 * Owns MFA enrollment, verification, and device lifecycle.
 * Secrets and backup codes must be hashed before persistence; only the enrollment result
 * returns plaintext secrets/codes, and that result is shown to the user exactly once.
 * Collaborates with: MfaDeviceRepository, SecurityPolicyService.
 */
interface MfaService
{
    public function enrollDevice(
        string $tenantId,
        string $userId,
        string $deviceType,
        ?string $deviceName,
    ): MfaEnrollmentResult;

    public function verifyEnrollment(
        string $tenantId,
        string $userId,
        string $deviceId,
        string $oneTimeCode,
    ): bool;

    public function verifyToken(
        string $tenantId,
        string $userId,
        string $oneTimeCode,
    ): bool;

    public function disableDevice(
        string $tenantId,
        string $userId,
        string $deviceId,
        string $disabledByUserId,
    ): bool;

    /**
     * @return array<int, string>  freshly generated plaintext backup codes (returned once)
     */
    public function regenerateBackupCodes(string $tenantId, string $userId): array;
}
