<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'report_executions';

    protected $primaryKey = 'execution_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'schedule_id',
        'tenant_id',
        'triggered_by_user_id',
        'file_path',
        'record_count',
        'execution_status',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_id', 'report_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class, 'schedule_id', 'schedule_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id', 'id');
    }
}
