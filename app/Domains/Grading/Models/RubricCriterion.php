<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'rubric_criteria';

    protected $primaryKey = 'criteria_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'rubric_id',
        'criteria_sequence',
        'criteria_name',
        'criteria_description',
        'criteria_weight_percentage',
        'scoring_levels',
        'assessment_guidelines',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'criteria_sequence' => 'integer',
            'criteria_weight_percentage' => 'integer',
            'scoring_levels' => 'array',
            'assessment_guidelines' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id', 'rubric_id');
    }
}
