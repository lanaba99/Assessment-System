<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class UserInviteResult
{
    public function __construct(
        public string $userId,
        public string $inviteToken,
    ) {
    }
}
