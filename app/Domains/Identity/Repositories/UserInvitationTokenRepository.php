<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\UserInvitationToken;

class UserInvitationTokenRepository
{
    public function __construct(
        private readonly UserInvitationToken $model,
    ) {
    }

    public function upsertToken(string $email, string $userId, string $hashedToken): void
    {
        $this->model
            ->newQuery()
            ->updateOrCreate(
                ['email' => $email],
                ['user_id' => $userId, 'token' => $hashedToken, 'created_at' => now()]
            );
    }

    public function findByEmail(string $email): ?UserInvitationToken
    {
        return $this->model->newQuery()->whereKey($email)->first();
    }

    public function deleteByEmail(string $email): void
    {
        $this->model->newQuery()->whereKey($email)->delete();
    }
}
