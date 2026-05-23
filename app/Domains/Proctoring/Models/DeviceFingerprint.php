<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceFingerprint extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'device_fingerprints';

    protected $primaryKey = 'fingerprint_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'candidate_user_id',
        'tenant_id',
        'device_fingerprint_hash',
        'device_id_hash',
        'screen_resolution_width',
        'screen_resolution_height',
        'browser_user_agent',
        'browser_language',
        'device_timezone',
        'hardware_metadata',
        'software_metadata',
        'is_jailbroken_or_rooted',
        'is_emulator_detected',
        'fingerprint_verification_status',
        'captured_at',
        'verified_at',
    ];

    protected $hidden = [
        'device_fingerprint_hash',
        'device_id_hash',
        'hardware_metadata',
        'software_metadata',
    ];

    protected function casts(): array
    {
        return [
            'screen_resolution_width' => 'integer',
            'screen_resolution_height' => 'integer',
            'hardware_metadata' => 'array',
            'software_metadata' => 'array',
            'is_jailbroken_or_rooted' => 'boolean',
            'is_emulator_detected' => 'boolean',
            'captured_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }
}
