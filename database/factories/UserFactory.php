<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Domains\Identity\Models\User::class;
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'external_employee_id' => 'EMP-' . $this->faker->unique()->numerify('######'),
            'email' => $this->faker->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'user_type' => $this->faker->randomElement(['examinee', 'evaluator', 'proctor', 'tenant_admin']),
            'department_id' => null,
            'status' => 'active',
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
            'user_attributes' => null,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
            'last_login_at' => null,
        ];
    }

    public function examinee(): static
    {
        return $this->state(fn () => ['user_type' => 'examinee']);
    }

    public function evaluator(): static
    {
        return $this->state(fn () => ['user_type' => 'evaluator']);
    }

    public function proctor(): static
    {
        return $this->state(fn () => ['user_type' => 'proctor']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['user_type' => 'tenant_admin']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'status' => 'inactive',
            'deactivated_at' => now(),
        ]);
    }
}
