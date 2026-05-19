<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Workflows\Models\ApprovalWorkflow;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssessmentResult extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'assessment_results';

    protected $primaryKey = 'result_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'candidate_user_id',
        'session_id',
        'exam_id',
        'tenant_id',
        'result_status',
        'skill_radar_data_json',
        'benchmark_comparison_data',
        'ai_recommendation_text',
        'ai_recommendation_confidence',
        'performance_insights',
        'learning_path_recommendations',
        'result_calculated_at',
        'publication_status',
        'published_at',
        'result_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'skill_radar_data_json' => 'array',
            'benchmark_comparison_data' => 'array',
            'ai_recommendation_confidence' => 'decimal:4',
            'performance_insights' => 'array',
            'learning_path_recommendations' => 'array',
            'result_calculated_at' => 'datetime',
            'published_at' => 'datetime',
            'result_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function publicationWorkflows(): MorphMany
    {
        return $this->morphMany(ApprovalWorkflow::class, 'resource', 'resource_type', 'resource_id');
    }
}
