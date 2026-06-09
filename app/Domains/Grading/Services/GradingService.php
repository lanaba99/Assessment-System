<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

/**
 * @deprecated Inject the App\Domains\Grading\Contracts\GradingService interface instead.
 *
 * This stub exists only to prevent a hard crash if any code still references the old
 * concrete class name during the Contract/Impl migration. It will be removed once all
 * callers have been updated to depend on the contract.
 *
 * The GradingServiceProvider binds:
 *   Contracts\GradingService → Services\GradingServiceImpl
 */
class GradingService extends GradingServiceImpl
{
}
