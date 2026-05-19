<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalIdentityMapping extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'external_identity_mapping';

    protected $primaryKey = 'mapping_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'external_provider_name',
        'external_user_id',
        'sync_metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
