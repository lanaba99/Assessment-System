<?php

declare(strict_types=1);

namespace App\Domains\Rules\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'rules';

    protected $primaryKey = 'rule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'rule_name',
        'rule_type',
        'rule_scope',
        'rule_category',
        'condition_tree_json',
        'action_payload_json',
        'is_active',
        'execution_order',
        'rule_metadata',
        'last_executed_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_tree_json' => 'array',
            'action_payload_json' => 'array',
            'is_active' => 'boolean',
            'execution_order' => 'integer',
            'rule_metadata' => 'array',
            'last_executed_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class, 'rule_id', 'rule_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RuleAction::class, 'rule_id', 'rule_id');
    }
}
