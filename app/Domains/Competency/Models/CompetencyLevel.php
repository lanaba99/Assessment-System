<?php

declare(strict_types=1);

namespace App\Domains\Competency\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyLevel extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'competency_levels';

    protected $primaryKey = 'level_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'competency_id',
        'level_number',
        'level_name',
        'level_description',
        'min_score_threshold',
        'max_score_threshold',
        'assessment_criteria',
        'learning_resources',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'level_number' => 'integer',
            'min_score_threshold' => 'decimal:2',
            'max_score_threshold' => 'decimal:2',
            'assessment_criteria' => 'array',
            'learning_resources' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'competency_id');
    }
}
