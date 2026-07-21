<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Competency\Models\Competency;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionCompetencyWeight extends Model
{
    use UsesUuid;

    protected $table = 'question_competency_weights';

    protected $primaryKey = 'weight_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'question_id',
        'competency_id',
        'weight_percentage',
        'skill_category',
        'skill_gap_trigger',
        'is_primary_competency',
        'weighting_metadata',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:2',
            'is_primary_competency' => 'boolean',
            'weighting_metadata' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'competency_id');
    }
}