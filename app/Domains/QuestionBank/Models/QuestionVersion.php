<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionVersion extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'question_versions';

    protected $primaryKey = 'version_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'created_by_user_id',
        'ver_num',
        'question_text',
        'question_type',
        'question_stem',
        'options_json',
        'correct_answer_json',
        'explanation_text',
        'evaluator_instructions',
        'approval_status',
        'approved_by_user_id',
        'usage_count_in_exams',
        'content_hash',
        'version_metadata',
        'created_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'ver_num' => 'integer',
            'options_json' => 'array',
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
}
