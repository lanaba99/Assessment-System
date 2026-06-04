<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use App\Domains\Identity\Models\User;
use DateTimeImmutable;

final class AuthenticationResult
{
    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_MFA_REQUIRED = 'mfa_required';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        public readonly string $status,
        public readonly ?string $userId,
        public readonly ?string $sessionId,
        public readonly ?string $rejectionReason,
        public readonly ?DateTimeImmutable $authenticatedAt,
        public readonly bool $mfaRequired = false,
        public ?User $user = null,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->status === self::STATUS_AUTHENTICATED;
    }
}
