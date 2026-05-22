<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Models;

use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionResponse extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'question_responses';

    protected $primaryKey = 'response_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'question_version_id',
        'candidate_user_id',
        'tenant_id',
        'question_sequence_number',
        'response_type',
        'response_data',
        'response_text',
        'selected_options_json',
        'file_upload_url',
        'time_spent_seconds',
        'time_elapsed_from_start_seconds',
        'is_flagged_for_review',
        'is_correct',
        'raw_score',
        'normalized_score',
        'final_score',
        'scoring_metadata',
        'integrity_status',
        'response_metadata',
        'response_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'question_sequence_number' => 'integer',
            'response_data' => 'array',
            'selected_options_json' => 'array',
            'time_spent_seconds' => 'integer',
            'time_elapsed_from_start_seconds' => 'integer',
            'is_flagged_for_review' => 'boolean',
            'is_correct' => 'boolean',
            'raw_score' => 'decimal:2',
            'normalized_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'scoring_metadata' => 'array',
            'response_metadata' => 'array',
            'response_submitted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id', 'version_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }
}
