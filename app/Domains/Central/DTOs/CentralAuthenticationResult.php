<?php

declare(strict_types=1);

namespace App\Domains\Central\DTOs;

final readonly class CentralAuthenticationResult
{
    public function __construct(
        public string $token,
        public string $adminUserId,
        public string $email,
        public string $firstName,
        public string $lastName,
    ) {
    }
}
