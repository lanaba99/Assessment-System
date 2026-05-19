<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'feature_flags';

    protected $primaryKey = 'flag_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'flag_key',
        'is_enabled',
        'roll_out_percentage',
        'constraint_rules',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'roll_out_percentage' => 'decimal:2',
            'constraint_rules' => 'array',
        ];
    }
}
