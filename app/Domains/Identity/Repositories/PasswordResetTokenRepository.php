<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\PasswordResetToken;

class PasswordResetTokenRepository
{
    public function __construct(
        private readonly PasswordResetToken $model,
    ) {
    }

    public function upsertToken(string $email, string $hashedToken): void
    {
        $this->model
            ->newQuery()
            ->updateOrCreate(
                ['email' => $email],
                ['token' => $hashedToken, 'created_at' => now()]
            );
    }

    public function findByEmail(string $email): ?PasswordResetToken
    {
        return $this->model->newQuery()->whereKey($email)->first();
    }

    public function deleteByEmail(string $email): void
    {
        $this->model->newQuery()->whereKey($email)->delete();
    }
}
