<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class PasswordValidationResult
{
    /**
     * @param  array<int, string>  $violations  human-readable rule violations
     */
    public function __construct(
        public bool $passed,
        public array $violations = [],
    ) {
    }

    public static function passed(): self
    {
        return new self(passed: true);
    }

    /**
     * @param  array<int, string>  $violations
     */
    public static function failed(array $violations): self
    {
        return new self(passed: false, violations: $violations);
    }
}
