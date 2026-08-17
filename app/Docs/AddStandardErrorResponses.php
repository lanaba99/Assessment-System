<?php

declare(strict_types=1);

namespace App\Docs;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

/**
 * Adds the standard EAE error responses to every documented endpoint.
 *
 * Scribe 5.11 strategies must use the parent __invoke() signature and return
 * response arrays. The old Response::make() API is not available in Scribe 5.11.
 */
final class AddStandardErrorResponses extends Strategy
{
    public function __invoke(
        ExtractedEndpointData $endpointData,
        array $settings = [],
    ): ?array {
        return [
            $this->errorResponse(
                status: 401,
                description: 'Unauthenticated',
                code: 'not_authenticated',
                message: 'Authentication required.',
            ),
            $this->errorResponse(
                status: 403,
                description: 'Forbidden',
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
            ),
            $this->errorResponse(
                status: 404,
                description: 'Not found',
                code: 'not_found',
                message: 'The requested resource was not found.',
            ),
            $this->errorResponse(
                status: 422,
                description: 'Validation error',
                code: 'validation_failed',
                message: 'The given data was invalid.',
            ),
            $this->errorResponse(
                status: 429,
                description: 'Too many requests',
                code: 'rate_limited',
                message: 'Too many requests. Please try again later.',
            ),
        ];
    }

    /**
     * @return array{status:int,description:string,content:string}
     */
    private function errorResponse(
        int $status,
        string $description,
        string $code,
        string $message,
    ): array {
        return [
            'status' => $status,
            'description' => $description,
            'content' => json_encode([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }
}
