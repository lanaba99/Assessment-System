<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Strategies;

use App\Domains\QuestionBank\DTOs\ItemResolutionRequest;
use Illuminate\Support\Collection;

interface ItemResolutionStrategy
{
    public function resolve(ItemResolutionRequest $request, Collection $eligiblePool): Collection;

    public function name(): string;
}
