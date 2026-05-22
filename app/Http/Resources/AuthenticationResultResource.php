<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Identity\DTOs\AuthenticationResult;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthenticationResult $resource
 */
class AuthenticationResultResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'status' => $view->status,
            'user_id' => $view->userId,
            'session_id' => $view->sessionId,
            'mfa_required' => $view->mfaRequired,
            'authenticated_at' => $view->authenticatedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
