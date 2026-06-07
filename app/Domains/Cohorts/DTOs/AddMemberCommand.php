<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\DTOs;

final readonly class AddMemberCommand
{
    public function __construct(
        public string $tenantId,
        public string $cohortId,
        public string $userId,
        public string $membershipRole = 'member',
    ) {
    }
}
