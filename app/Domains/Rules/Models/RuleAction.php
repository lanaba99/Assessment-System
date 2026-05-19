<?php

declare(strict_types=1);

namespace App\Domains\Rules\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleAction extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'rule_actions';

    protected $primaryKey = 'action_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'action_type',
        'action_parameters',
        'action_value',
        'execution_sequence',
        'action_metadata',
    ];

    protected function casts(): array
    {
        return [
            'action_parameters' => 'array',
            'action_value' => 'decimal:4',
            'execution_sequence' => 'integer',
            'action_metadata' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'rule_id', 'rule_id');
    }
}
