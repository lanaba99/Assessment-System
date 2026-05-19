<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Models;

use App\Domains\Competency\Models\Competency;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamConfig extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_blueprints';

    protected $primaryKey = 'blueprint_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'exam_id',
        'competency_id',
        'min_questions_count',
        'max_questions_count',
        'min_weight_percentage',
        'max_weight_percentage',
        'difficulty_distribution_easy_count',
        'difficulty_distribution_medium_count',
        'difficulty_distribution_hard_count',
        'blueprint_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'min_questions_count' => 'integer',
            'max_questions_count' => 'integer',
            'min_weight_percentage' => 'decimal:2',
            'max_weight_percentage' => 'decimal:2',
            'difficulty_distribution_easy_count' => 'integer',
            'difficulty_distribution_medium_count' => 'integer',
            'difficulty_distribution_hard_count' => 'integer',
            'blueprint_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'competency_id');
    }
}
