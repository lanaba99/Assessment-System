<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Models;

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCandidateEligible extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_enrollments';

    protected $primaryKey = 'enrollment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'exam_id',
        'candidate_user_id',
        'tenant_id',
        'cohort_id',
        'enrollment_status',
        'enrollment_date',
        'start_window_date',
        'end_window_date',
        'start_eligibility_date',
        'end_eligibility_date',
        'can_retake_exam',
        'max_attempts_allowed',
        'attempts_used',
        'attempts_remaining',
        'highest_score_achieved',
        'highest_score_status',
        'enrollment_notes',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'datetime',
            'start_window_date' => 'datetime',
            'end_window_date' => 'datetime',
            'start_eligibility_date' => 'datetime',
            'end_eligibility_date' => 'datetime',
            'can_retake_exam' => 'boolean',
            'max_attempts_allowed' => 'integer',
            'attempts_used' => 'integer',
            'attempts_remaining' => 'integer',
            'highest_score_achieved' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CandidateExamStatus::class, 'enrollment_id', 'enrollment_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id', 'cohort_id');
    }
}
