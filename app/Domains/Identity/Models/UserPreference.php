<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'user_preferences';

    protected $primaryKey = 'preference_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'theme_preference',
        'language_preference',
        'date_format',
        'time_format',
        'email_notifications_enabled',
        'push_notifications_enabled',
        'sms_notifications_enabled',
        'additional_preferences',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'email_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'additional_preferences' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
