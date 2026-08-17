<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared response envelope for all API controllers.
 *
 * Formalizes the {"data": ...} / {"error": {"code", "message"}} shape that
 * was previously duplicated (with minor drift) across 15+ controllers as a
 * private errorResponse()/error() method. Part of Phase 5 — Public API
 * Foundation: one canonical envelope builder instead of N near-identical
 * copies.
 *
 * errorResponse() is the canonical name going forward; error() is kept as
 * an alias so existing call sites (RoleController, TenantSettingsController,
 * IdentityController, UserController) don't all need renaming in the same
 * pass.
 */
trait BuildsApiResponses
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function successResponse(mixed $data, int $status = Response::HTTP_OK, ?array $meta = null): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    protected function errorResponse(string $code, string $message, int $status, array $extras = []): JsonResponse
    {
        return new JsonResponse(
            ['error' => array_merge(['code' => $code, 'message' => $message], $extras)],
            $status,
        );
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    protected function error(string $code, string $message, int $status, array $extras = []): JsonResponse
    {
        return $this->errorResponse($code, $message, $status, $extras);
    }
}