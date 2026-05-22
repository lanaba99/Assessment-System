<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\DTOs;

final readonly class SubmitResponseCommand
{
    public function __construct(
        public string $tenantId,
        public string $sessionId,
        public string $sessionItemId,
        public string $candidateId,
        public string $responseType,
        public ?array $responseData = null,
        public ?string $responseText = null,
        public ?array $selectedOptions = null,
        public ?string $fileUploadUrl = null,
        public ?int $timeSpentSeconds = null,
        public ?int $timeElapsedFromStartSeconds = null,
        public bool $isFlaggedForReview = false,
        public ?int $expectedItemVersionLock = null,
    ) {
    }
}
