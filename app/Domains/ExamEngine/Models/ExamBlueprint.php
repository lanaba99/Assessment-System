<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Models;

use App\Domains\Competency\Models\Competency;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamBlueprint extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_blueprints';

    protected $primaryKey = 'blueprint_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'exam_id',
        'section_id',
        'competency_id',
        'min_questions_count',
        'max_questions_count',
        'min_weight_percentage',
        'max_weight_percentage',
        'bloom_distribution',
        'target_difficulty',
        'min_discrimination',
        'resolution_strategy',
        'blueprint_metadata',
    ];

    protected function casts(): array
    {
        return [
            'min_questions_count' => 'integer',
            'max_questions_count' => 'integer',
            'min_weight_percentage' => 'decimal:2',
            'max_weight_percentage' => 'decimal:2',
            'target_difficulty' => 'decimal:3',
            'min_discrimination' => 'decimal:3',
            'bloom_distribution' => 'array',
            'blueprint_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'section_id', 'section_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'competency_id');
    }
}
