<?php

declare(strict_types=1);

namespace App\Domains\Grading\Contracts;

use App\Domains\Grading\DTOs\GradingRequest;
use App\Domains\Grading\DTOs\GradingResult;

interface GradingStrategy
{
    public function supports(string $questionType): bool;

    public function grade(GradingRequest $request): GradingResult;
}
