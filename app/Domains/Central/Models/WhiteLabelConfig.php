<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteLabelConfig extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'white_label_settings';

    protected $primaryKey = 'config_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'primary_color',
        'secondary_color',
        'custom_logo_url',
        'custom_domain_url',
        'custom_css',
        'email_sender_name',
        'email_sender_address',
        'brand_metadata',
    ];

    protected function casts(): array
    {
        return [
            'brand_metadata' => 'array',
        ];
    }
}
