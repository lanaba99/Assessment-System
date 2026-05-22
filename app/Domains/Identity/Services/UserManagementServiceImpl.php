<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\UserManagementService;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Repositories\UserRepository;
use App\Domains\Identity\Repositories\UserSubtypeRepository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserManagementServiceImpl implements UserManagementService
{
    private const ALLOWED_PROFILE_FIELDS = [
        'first_name',
        'last_name',
        'external_employee_id',
        'user_type',
        'department_id',
        'user_attributes',
        'status',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSubtypeRepository $subtypes,
        private readonly Hasher $hasher,
    ) {
    }

    public function createUser(
        string $tenantId,
        string $email,
        string $plaintextPassword,
        array $profile,
        string $createdByUserId,
    ): string {
        return DB::transaction(function () use ($tenantId, $email, $plaintextPassword, $profile): string {
            $sanitized = $this->sanitizeProfile($profile);

            $user = $this->users->create($tenantId, array_merge($sanitized, [
                'email' => $email,
                'password_hash' => $this->hasher->make($plaintextPassword),
                'is_active' => $sanitized['is_active'] ?? true,
                'status' => $sanitized['status'] ?? 'active',
                'activated_at' => now(),
            ]));

            return (string) $user->id;
        });
    }

    public function updateProfile(string $tenantId, string $userId, array $changes): void
    {
        DB::transaction(function () use ($tenantId, $userId, $changes): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            $this->users->update($user, $this->sanitizeProfile($changes));
        });
    }

    public function changePassword(
        string $tenantId,
        string $userId,
        string $currentPlaintextPassword,
        string $newPlaintextPassword,
    ): void {
        DB::transaction(function () use ($tenantId, $userId, $currentPlaintextPassword, $newPlaintextPassword): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            if (! $this->hasher->check($currentPlaintextPassword, (string) $user->password_hash)) {
                throw InvalidCredentialsException::forWrongPassword();
            }

            $this->users->update($user, [
                'password_hash' => $this->hasher->make($newPlaintextPassword),
            ]);
        });
    }

    public function resetPassword(
        string $tenantId,
        string $userId,
        string $newPlaintextPassword,
        string $resetByUserId,
    ): void {
        DB::transaction(function () use ($tenantId, $userId, $newPlaintextPassword): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            $this->users->update($user, [
                'password_hash' => $this->hasher->make($newPlaintextPassword),
            ]);
        });
    }

    public function deactivateUser(string $tenantId, string $userId, string $deactivatedByUserId): void
    {
        DB::transaction(function () use ($tenantId, $userId): void {
            $user = $this->users->deactivate($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }
        });
    }

    public function assignToDepartment(string $tenantId, string $userId, string $departmentId): void
    {
        $this->updateProfile($tenantId, $userId, ['department_id' => $departmentId]);
    }

    public function setSubtype(string $tenantId, string $userId, array $subtypeAttributes): void
    {
        DB::transaction(function () use ($tenantId, $userId, $subtypeAttributes): void {
            $existing = $this->subtypes->findForUser($tenantId, $userId);

            if ($existing === null) {
                $this->subtypes->createForUser($tenantId, $userId, $subtypeAttributes);
            } else {
                $this->subtypes->update($tenantId, $userId, $subtypeAttributes);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function sanitizeProfile(array $profile): array
    {
        return array_intersect_key($profile, array_flip(self::ALLOWED_PROFILE_FIELDS));
    }
}
