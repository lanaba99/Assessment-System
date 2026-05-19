<?php

declare(strict_types=1);

namespace App\Domains\Proctoring\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProctorLog extends Model
{
    use HasFactory;
    use UsesUuid;

    public const CATEGORY_AIDE_VIOLATION = 'aide_violation';

    protected $table = 'proctoring_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'candidate_user_id',
        'tenant_id',
        'reviewing_proctor_id',
        'event_timestamp',
        'event_type',
        'event_category',
        'event_payload',
        'detection_parameters',
        'severity_level',
        'detection_confidence_score',
        'screenshot_url',
        'video_segment_url',
        'requires_investigation',
        'is_escalated',
        'investigation_status',
        'investigation_notes',
        'created_at',
    ];

    protected $hidden = [
        'detection_parameters',
    ];

    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'event_payload' => 'array',
            'detection_parameters' => 'array',
            'detection_confidence_score' => 'decimal:4',
            'requires_investigation' => 'boolean',
            'is_escalated' => 'boolean',
            'investigation_notes' => 'array',
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

    public function reviewingProctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewing_proctor_id', 'id');
    }
}
