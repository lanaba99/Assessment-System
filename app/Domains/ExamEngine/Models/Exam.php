<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\Grading\Models\Rubric;
use App\Domains\Identity\Models\User;
use App\Domains\Rules\Models\EligibilityChain;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exams';

    protected $primaryKey = 'exam_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'exam_name',
        'exam_code',
        'exam_description',
        'exam_type',
        'assessment_mode',
        'total_questions',
        'total_duration_minutes',
        'pass_mark_percentage',
        'difficulty_tier_level',
        'is_adaptive_exam',
        'is_randomized',
        'allow_review_after_submit',
        'allow_flagging_for_review',
        'timer_visible_to_candidate',
        'show_correct_answers_after',
        'security_protocols',
        'exam_metadata',
        'is_published',
        'exam_status',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'total_duration_minutes' => 'integer',
            'pass_mark_percentage' => 'decimal:2',
            'difficulty_tier_level' => 'integer',
            'is_adaptive_exam' => 'boolean',
            'is_randomized' => 'boolean',
            'allow_review_after_submit' => 'boolean',
            'allow_flagging_for_review' => 'boolean',
            'timer_visible_to_candidate' => 'boolean',
            'show_correct_answers_after' => 'boolean',
            'security_protocols' => 'array',
            'exam_metadata' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class, 'exam_id', 'exam_id');
    }

    public function configs(): HasMany
    {
        return $this->hasMany(ExamConfig::class, 'exam_id', 'exam_id');
    }

    public function blueprints(): HasMany
    {
        return $this->hasMany(ExamBlueprint::class, 'exam_id', 'exam_id');
    }

    public function eligibleCandidates(): HasMany
    {
        return $this->hasMany(ExamCandidateEligible::class, 'exam_id', 'exam_id');
    }

    public function candidateStatuses(): HasMany
    {
        return $this->hasMany(CandidateExamStatus::class, 'exam_id', 'exam_id');
    }

    public function rubrics(): HasMany
    {
        return $this->hasMany(Rubric::class, 'exam_id', 'exam_id');
    }

    public function eligibilityChain(): HasMany
    {
        return $this->hasMany(EligibilityChain::class, 'exam_id', 'exam_id');
    }

    public function gatesForPrerequisites(): HasMany
    {
        return $this->hasMany(EligibilityChain::class, 'prerequisite_exam_id', 'exam_id');
    }
}
