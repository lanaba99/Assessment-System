<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'tenant_settings';

    protected $primaryKey = 'setting_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'setting_key',
        'setting_value',
        'field_type',
        'setting_group',
        'is_encrypted',
        'is_public',
    ];

    protected $hidden = [
        'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
