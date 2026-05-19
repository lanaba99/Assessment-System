<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\DTOs;

use Carbon\CarbonImmutable;

final readonly class ItemPsychometrics
{
    public function __construct(
        public string $questionVersionId,
        public ?float $difficultyIndex,
        public ?float $discriminationIndex,
        public ?float $pointBiserial,
        public int $sampleSize,
        public int $correctCount,
        public bool $isCalibrated,
        public string $calibrationStatus,
        public ?CarbonImmutable $lastCalibratedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'question_version_id' => $this->questionVersionId,
            'difficulty_index' => $this->difficultyIndex,
            'discrimination_index' => $this->discriminationIndex,
            'point_biserial' => $this->pointBiserial,
            'sample_size' => $this->sampleSize,
            'correct_count' => $this->correctCount,
            'is_calibrated' => $this->isCalibrated,
            'calibration_status' => $this->calibrationStatus,
            'last_calibrated_at' => $this->lastCalibratedAt,
        ];
    }
}
