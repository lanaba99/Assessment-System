<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Contracts;

use App\Domains\QuestionBank\DTOs\AdaptiveContext;
use App\Domains\QuestionBank\DTOs\BlueprintSpecification;
use App\Domains\QuestionBank\DTOs\CoverageReport;
use App\Domains\QuestionBank\DTOs\ItemPsychometrics;
use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use App\Domains\QuestionBank\DTOs\ItemResolutionResult;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Support\Collection;

interface QuestionBankService
{
    public function resolveItems(ItemResolutionRequest $request): ItemResolutionResult;

    public function resolveNextAdaptiveItem(AdaptiveContext $context): ?QuestionVersion;

    public function recalibrateItem(string $questionVersionId): ItemPsychometrics;

    public function recalibrateBatch(array $questionVersionIds): Collection;

    public function flagItemsForReview(string $tenantId, float $discriminationThreshold = 0.20): Collection;

    public function analyzeCoverage(BlueprintSpecification $blueprint): CoverageReport;
}
