<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use DateTimeImmutable;

final readonly class AuthenticationResult
{
    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_MFA_REQUIRED = 'mfa_required';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        public string $status,
        public ?string $userId,
        public ?string $sessionId,
        public ?string $rejectionReason,
        public ?DateTimeImmutable $authenticatedAt,
        public bool $mfaRequired = false,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->status === self::STATUS_AUTHENTICATED;
    }
}
