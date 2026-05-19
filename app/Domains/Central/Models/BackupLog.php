<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'backup_jobs';

    protected $primaryKey = 'job_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'initiated_by_user_id',
        'backup_type',
        'backup_size_bytes',
        'backup_location',
        'backup_status',
        'backup_metadata',
        'created_at',
        'backup_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'backup_size_bytes' => 'integer',
            'backup_metadata' => 'array',
            'created_at' => 'datetime',
            'backup_completed_at' => 'datetime',
        ];
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id', 'id');
    }
}
