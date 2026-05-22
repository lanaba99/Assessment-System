<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

class IpNotAllowedException extends AuthenticationFailedException
{
    public static function forIp(string $ipAddress): self
    {
        return new self("Access from IP '{$ipAddress}' is not permitted by tenant policy.", 'ip_not_allowed');
    }
}
