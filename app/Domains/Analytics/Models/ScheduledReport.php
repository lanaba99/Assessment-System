<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledReport extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'scheduled_reports';

    protected $primaryKey = 'schedule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'report_id',
        'tenant_id',
        'frequency',
        'cron_expression',
        'recipient_emails',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'recipient_emails' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_id', 'report_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'schedule_id', 'schedule_id');
    }
}
