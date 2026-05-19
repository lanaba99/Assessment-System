<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrowserLockdownEvent extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'session_integrity_logs';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'candidate_user_id',
        'tenant_id',
        'event_timestamp',
        'event_type',
        'event_payload',
        'integrity_check_type',
        'integrity_status',
        'flag_reason',
        'severity_level',
        'requires_manual_review',
        'log_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'event_payload' => 'array',
            'requires_manual_review' => 'boolean',
            'log_metadata' => 'array',
            'created_at' => 'datetime',
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
