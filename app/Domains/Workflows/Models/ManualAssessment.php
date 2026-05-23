<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualAssessment extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'manual_assessments';

    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'exam_id',
        'evaluator_user_id',
        'candidate_user_id',
        'tenant_id',
        'correlated_session_id',
        'assessment_type',
        'assessment_mode',
        'assessment_data_json',
        'assessment_conducted_at',
        'assessment_status',
        'assessment_metadata',
    ];

    protected function casts(): array
    {
        return [
            'assessment_data_json' => 'array',
            'assessment_conducted_at' => 'datetime',
            'assessment_metadata' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id', 'id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function correlatedSession(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'correlated_session_id', 'session_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(EvaluatorObservation::class, 'manual_assessment_id', 'assessment_id');
    }

    public function checklistResponses(): HasMany
    {
        return $this->hasMany(ChecklistResponse::class, 'manual_assessment_id', 'assessment_id');
    }
}
