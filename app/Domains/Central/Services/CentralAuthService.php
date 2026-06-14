<?php

declare(strict_types=1);

namespace App\Domains\Central\Services;

use App\Domains\Central\DTOs\CentralAuthenticationResult;
use App\Domains\Central\Models\CentralAdminUser;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CentralAuthService
{
    public function login(string $email, string $password): CentralAuthenticationResult
    {
        $admin = CentralAdminUser::query()
            ->where('email', $email)
            ->where('status', 'active')
            ->first();

        if ($admin === null || ! Hash::check($password, (string) $admin->password_hash)) {
            throw new RuntimeException('Invalid central admin credentials.');
        }

        $admin->forceFill(['last_login_at' => now()])->save();
        $token = $admin->createToken('central-admin')->plainTextToken;

        return new CentralAuthenticationResult(
            token: $token,
            adminUserId: (string) $admin->admin_user_id,
            email: (string) $admin->email,
            firstName: (string) $admin->first_name,
            lastName: (string) $admin->last_name,
        );
    }
}
