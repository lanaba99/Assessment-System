<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryLog extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'webhook_logs';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'tenant_id',
        'target_url',
        'attempt_number',
        'response_status',
        'response_body',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'response_status' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookConfig::class, 'event_id', 'event_id');
    }
}
