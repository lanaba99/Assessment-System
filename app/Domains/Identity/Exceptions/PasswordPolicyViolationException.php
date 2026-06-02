<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

class PasswordPolicyViolationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $violations
     */
    public function __construct(
        public readonly array $violations,
    ) {
        parent::__construct('Password does not meet the tenant security policy.');
    }
}
