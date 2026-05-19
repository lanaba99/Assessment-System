<?php

declare(strict_types=1);

namespace App\Domains\Rules\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleCondition extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'rule_conditions';

    protected $primaryKey = 'condition_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'condition_type',
        'condition_definition',
        'comparison_operator',
        'logical_operator',
        'nesting_level',
        'condition_metadata',
    ];

    protected function casts(): array
    {
        return [
            'condition_definition' => 'array',
            'nesting_level' => 'integer',
            'condition_metadata' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'rule_id', 'rule_id');
    }
}
