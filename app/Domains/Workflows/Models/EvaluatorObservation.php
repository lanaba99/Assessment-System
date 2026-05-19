<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluatorObservation extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'evaluator_observations';

    protected $primaryKey = 'observation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'manual_assessment_id',
        'evaluator_user_id',
        'tenant_id',
        'observation_category',
        'observation_data',
        'severity_level',
        'affects_final_score',
        'observation_status',
        'observation_metadata',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'observation_data' => 'array',
            'affects_final_score' => 'boolean',
            'observation_metadata' => 'array',
            'observed_at' => 'datetime',
        ];
    }

    public function manualAssessment(): BelongsTo
    {
        return $this->belongsTo(ManualAssessment::class, 'manual_assessment_id', 'assessment_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id', 'id');
    }
}
