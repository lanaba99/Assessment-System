<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Enums;

enum CalibrationStatus: string
{
    case Pending = 'pending';
    case Calibrated = 'calibrated';
    case Marginal = 'marginal';
    case FlaggedForReview = 'flagged_for_review';
    case Retired = 'retired';
}
