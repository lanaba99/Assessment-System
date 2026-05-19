<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'grades';

    protected $primaryKey = 'grade_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'candidate_user_id',
        'exam_id',
        'tenant_id',
        'raw_score',
        'weighted_score',
        'normalized_score',
        'final_score',
        'grade_letter',
        'is_passing_grade',
        'requires_second_marking',
        'is_final_grade',
        'grading_metadata',
        'graded_at',
        'finalized_at',
        'version_lock',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2',
            'weighted_score' => 'decimal:2',
            'normalized_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'is_passing_grade' => 'boolean',
            'requires_second_marking' => 'boolean',
            'is_final_grade' => 'boolean',
            'grading_metadata' => 'array',
            'graded_at' => 'datetime',
            'finalized_at' => 'datetime',
            'version_lock' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }
}
