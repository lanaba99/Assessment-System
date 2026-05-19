<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataMigrationLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'data_migration_logs';

    protected $primaryKey = 'migration_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'initiated_by_user_id',
        'migration_type',
        'source_env',
        'target_env',
        'records_migrated',
        'migration_status',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'records_migrated' => 'integer',
            'error_details' => 'array',
        ];
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id', 'id');
    }
}
