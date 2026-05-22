<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown by guard/middleware code, not by AuthorizationService itself
 * (its contract returns bool). Map to HTTP 403 at the HTTP boundary.
 */
class InsufficientPermissionsException extends RuntimeException
{
    public static function forPermission(string $permissionName): self
    {
        return new self("Permission '{$permissionName}' is required for this operation.");
    }

    public static function forRole(string $roleName): self
    {
        return new self("Role '{$roleName}' is required for this operation.");
    }
}
