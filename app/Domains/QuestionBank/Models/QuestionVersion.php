<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionVersion extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesUuid;

    protected $table = 'question_versions';

    protected $primaryKey = 'version_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Versions are written exclusively by the repository/service (forceCreate),
     * so identity/workflow columns (question_id, created_by_user_id, ver_num,
     * approval_status, approved_by_user_id, usage_count_in_exams, content_hash,
     * timestamps) are kept out of $fillable as a mass-assignment tripwire.
     */
    protected $fillable = [
        'question_text',
        'question_type',
        'question_stem',
        'correct_answer_json',
        'explanation_text',
        'evaluator_instructions',
        'version_metadata',
    ];

    protected function casts(): array
    {
        return [
            'ver_num' => 'integer',
            'correct_answer_json' => 'array',
            'explanation_text' => 'array',
            'evaluator_instructions' => 'array',
            'usage_count_in_exams' => 'integer',
            'version_metadata' => 'array',
            'created_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id', 'id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'version_id', 'version_id');
    }

    public function psychometrics(): HasOne
    {
        return $this->hasOne(QuestionPsychometrics::class, 'question_version_id', 'version_id');
    }
}
