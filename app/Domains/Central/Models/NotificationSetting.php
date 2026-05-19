<?php

declare(strict_types=1);

namespace App\Domains\Central\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'notification_settings';

    protected $primaryKey = 'setting_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email_notifications_enabled',
        'push_notifications_enabled',
        'sms_notifications_enabled',
        'in_app_notifications_enabled',
        'notification_preferences',
    ];

    protected function casts(): array
    {
        return [
            'email_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'in_app_notifications_enabled' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
