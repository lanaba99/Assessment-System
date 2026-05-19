<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'rubrics';

    protected $primaryKey = 'rubric_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'exam_id',
        'created_by_user_id',
        'tenant_id',
        'rubric_name',
        'rubric_type',
        'rubric_description',
        'rubric_structure',
        'is_mandatory_rubric',
    ];

    protected function casts(): array
    {
        return [
            'rubric_structure' => 'array',
            'is_mandatory_rubric' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id', 'rubric_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AnswerEvaluation::class, 'rubric_id', 'rubric_id');
    }
}
