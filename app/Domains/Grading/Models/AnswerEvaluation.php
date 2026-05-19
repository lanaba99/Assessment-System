<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerEvaluation extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'answer_evaluations';

    protected $primaryKey = 'evaluation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'question_id',
        'evaluator_user_id',
        'tenant_id',
        'rubric_id',
        'evaluation_type',
        'rubric_criteria_json',
        'score_awarded',
        'max_score_possible',
        'evaluation_status',
        'evaluator_comments',
        'evaluation_metadata',
        'requires_secondary_review',
        'secondary_reviewer_id',
        'evaluated_at',
        'secondary_reviewed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rubric_criteria_json' => 'array',
            'score_awarded' => 'decimal:2',
            'max_score_possible' => 'decimal:2',
            'evaluator_comments' => 'array',
            'evaluation_metadata' => 'array',
            'requires_secondary_review' => 'boolean',
            'evaluated_at' => 'datetime',
            'secondary_reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id', 'id');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id', 'rubric_id');
    }

    public function secondaryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_reviewer_id', 'id');
    }
}
