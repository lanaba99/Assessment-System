<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SecurityPolicyService;
use App\Domains\Identity\Contracts\UserManagementService;
use App\Domains\Identity\DTOs\UserInviteResult;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\Identity\Repositories\UserInvitationTokenRepository;
use App\Domains\Identity\Repositories\UserRepository;
use App\Domains\Identity\Repositories\UserSessionRepository;
use App\Domains\Identity\Repositories\UserSubtypeRepository;
use App\Http\Requests\Identity\UpdateProfileRequest;
use App\Domains\Identity\Notifications\UserInvited;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UserManagementServiceImpl implements UserManagementService
{
    private const INVITE_PLACEHOLDER_PASSWORD_LENGTH = 64;

    /**
     * Admin-scoped profile fields. Includes authorization-bearing attributes
     * (user_type, status, department_id) that self-update MUST NOT touch.
     * Self-update goes through self-editable fields exposed by UpdateProfileRequest.
     */
    private const ADMIN_EDITABLE_FIELDS = [
        'first_name',
        'last_name',
        'external_employee_id',
        'user_type',
        'department_id',
        'user_attributes',
        'status',
        'is_active',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSubtypeRepository $subtypes,
        private readonly UserInvitationTokenRepository $invitationTokens,
        private readonly UserSessionRepository $sessions,
        private readonly SecurityPolicyService $securityPolicyService,
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
            $sanitized = $this->sanitizeAdminProfile($profile);

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

    public function inviteUser(
        string $tenantId,
        string $email,
        array $profile,
        string $invitedByUserId,
    ): UserInviteResult {
        return DB::transaction(function () use ($tenantId, $email, $profile, $invitedByUserId): UserInviteResult {
            $existing = $this->users->findByEmail($tenantId, $email);

            if ($existing !== null && (string) $existing->status !== 'pending') {
                throw new RuntimeException("A user with email {$email} already exists in this tenant.");
            }

            if ($existing !== null) {
                $this->invitationTokens->deleteByEmail($email);
                $existing->delete();
            }

            $sanitized = $this->sanitizeAdminProfile($profile);
            $placeholderPassword = Str::random(self::INVITE_PLACEHOLDER_PASSWORD_LENGTH);

            $user = $this->users->create($tenantId, array_merge($sanitized, [
                'email' => $email,
                'password_hash' => $this->hasher->make($placeholderPassword),
                'is_active' => false,
                'status' => 'pending',
                'activated_at' => null,
            ]));

            $plainToken = Str::random(64);
            $this->invitationTokens->upsertToken(
                $email,
                (string) $user->id,
                $this->hasher->make($plainToken),
            );

            // Done: dispatch invitation notification/email with the plaintext token.
            $user->notify(new UserInvited($plainToken));

            return new UserInviteResult(
                userId: (string) $user->id,
                inviteToken: $plainToken,
            );
        });
    }

    public function getProfile(string $tenantId, string $userId): ?array
    {
        $user = $this->users->findById($tenantId, $userId);

        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'tenant_id' => (string) $user->tenant_id,
            'email' => (string) $user->email,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'external_employee_id' => $user->external_employee_id,
            'user_type' => (string) $user->user_type,
            'department_id' => $user->department_id,
            'status' => (string) $user->status,
            'is_active' => (bool) $user->is_active,
            'user_attributes' => $user->user_attributes,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Self-update path. Always strips authorization-bearing fields (user_type,
     * status, department_id) — those are admin-only. Callers that need to
     * update those fields must use updateProfileByAdmin().
     */
    public function updateProfile(string $tenantId, string $userId, array $changes): void
    {
        DB::transaction(function () use ($tenantId, $userId, $changes): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            $this->users->update($user, $this->sanitizeSelfProfile($changes));
        });
    }

    public function updateProfileByAdmin(string $tenantId, string $userId, array $changes): void
    {
        DB::transaction(function () use ($tenantId, $userId, $changes): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            $this->users->update($user, $this->sanitizeAdminProfile($changes));
        });
    }

    public function changePassword(
        string $tenantId,
        string $userId,
        string $currentPlaintextPassword,
        string $newPlaintextPassword,
    ): void {
        $validation = $this->securityPolicyService->validatePassword($tenantId, $newPlaintextPassword);
        if (! $validation->passed) {
            throw new PasswordPolicyViolationException($validation->violations);
        }

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

            $this->sessions->revokeAllForUser($tenantId, (string) $user->id);
            $user->tokens()->delete();
        });
    }

    public function resetPassword(
        string $tenantId,
        string $userId,
        string $newPlaintextPassword,
        string $resetByUserId,
    ): void {
        $validation = $this->securityPolicyService->validatePassword($tenantId, $newPlaintextPassword);
        if (! $validation->passed) {
            throw new PasswordPolicyViolationException($validation->violations);
        }

        DB::transaction(function () use ($tenantId, $userId, $newPlaintextPassword): void {
            $user = $this->users->findById($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            $this->users->update($user, [
                'password_hash' => $this->hasher->make($newPlaintextPassword),
            ]);

            // Admin-initiated reset is also a containment action: drop every
            // active session and revoke every bearer token belonging to the
            // target user.
            $this->sessions->revokeAllForUser($tenantId, (string) $user->id);
            $user->tokens()->delete();
        });
    }

    public function deactivateUser(string $tenantId, string $userId, string $deactivatedByUserId): void
    {
        DB::transaction(function () use ($tenantId, $userId): void {
            $user = $this->users->deactivate($tenantId, $userId);

            if ($user === null) {
                throw new RuntimeException("User {$userId} not found in tenant {$tenantId}.");
            }

            // A deactivated user must not retain valid auth artifacts.
            $this->sessions->revokeAllForUser($tenantId, (string) $user->id);
            $user->tokens()->delete();
        });
    }

    public function assignToDepartment(string $tenantId, string $userId, string $departmentId): void
    {
        $this->updateProfileByAdmin($tenantId, $userId, ['department_id' => $departmentId]);
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

    public function listUsers(string $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginateForTenant($tenantId, $perPage);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function sanitizeAdminProfile(array $profile): array
    {
        return array_intersect_key($profile, array_flip(self::ADMIN_EDITABLE_FIELDS));
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function sanitizeSelfProfile(array $profile): array
    {
        return array_intersect_key(
            $profile,
            array_flip(UpdateProfileRequest::SELF_EDITABLE_FIELDS),
        );
    }
}
