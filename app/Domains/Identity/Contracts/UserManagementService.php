<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

/**
 * User lifecycle (create, update, deactivate) + org-structure linkage.
 * Password handling lives here too: implementations must enforce SecurityPolicyService
 * password rules and hash before persistence.
 * Collaborates with: UserRepository, UserSubtypeRepository, DepartmentRepository, SecurityPolicyService.
 */
interface UserManagementService
{
    /**
     * @param  array<string, mixed>  $profile  first_name, last_name, external_employee_id, user_type, department_id, user_attributes
     */
    public function createUser(
        string $tenantId,
        string $email,
        string $plaintextPassword,
        array $profile,
        string $createdByUserId,
    ): string;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateProfile(string $tenantId, string $userId, array $changes): void;

    public function changePassword(
        string $tenantId,
        string $userId,
        string $currentPlaintextPassword,
        string $newPlaintextPassword,
    ): void;

    public function resetPassword(
        string $tenantId,
        string $userId,
        string $newPlaintextPassword,
        string $resetByUserId,
    ): void;

    public function deactivateUser(string $tenantId, string $userId, string $deactivatedByUserId): void;

    public function assignToDepartment(string $tenantId, string $userId, string $departmentId): void;

    /**
     * @param  array<string, mixed>  $subtypeAttributes
     */
    public function setSubtype(string $tenantId, string $userId, array $subtypeAttributes): void;
}
