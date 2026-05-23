<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyRule extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'penalty_rules';

    protected $primaryKey = 'penalty_rule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'penalty_name',
        'penalty_type',
        'trigger_condition',
        'trigger_parameters',
        'penalty_points',
        'penalty_percentage',
        'is_cumulative',
        'penalty_metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_parameters' => 'array',
            'penalty_points' => 'decimal:4',
            'penalty_percentage' => 'decimal:2',
            'is_cumulative' => 'boolean',
            'penalty_metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(PenaltySanction::class, 'penalty_rule_id', 'penalty_rule_id');
    }
}
