<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Grading\Models\AnswerEvaluation;
use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Models\CompetencyScore;
use App\Domains\Grading\Models\Grade;
use App\Domains\Identity\Models\User;
use App\Domains\Penalties\Models\PenaltySanction;
use App\Domains\Proctoring\Models\BrowserLockdownEvent;
use App\Domains\Proctoring\Models\DeviceFingerprint;
use App\Domains\Proctoring\Models\ProctorLog;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateExamStatus extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_sessions';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'exam_id',
        'enrollment_id',
        'candidate_user_id',
        'tenant_id',
        'proctor_user_id',
        'session_state',
        'current_question_reference',
        'current_question_index',
        'total_questions_responded',
        'total_questions_flagged',
        'session_progress_json',
        'candidate_device_metadata',
        'device_fingerprint',
        'device_id',
        'device_type',
        'browser_type',
        'operating_system',
        'ip_address',
        'initial_ip_address',
        'gps_latitude',
        'gps_longitude',
        'session_start_location',
        'session_started_at',
        'session_resumed_at',
        'session_ended_at',
        'total_session_duration_seconds',
        'actual_response_time_seconds',
        'completion_method',
        'last_heartbeat_at',
        'heartbeat_metadata',
        'version_lock',
    ];

    protected function casts(): array
    {
        return [
            'current_question_index' => 'integer',
            'total_questions_responded' => 'integer',
            'total_questions_flagged' => 'integer',
            'session_progress_json' => 'array',
            'candidate_device_metadata' => 'array',
            'gps_latitude' => 'decimal:7',
            'gps_longitude' => 'decimal:7',
            'session_started_at' => 'datetime',
            'session_resumed_at' => 'datetime',
            'session_ended_at' => 'datetime',
            'total_session_duration_seconds' => 'integer',
            'actual_response_time_seconds' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'heartbeat_metadata' => 'array',
            'version_lock' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ExamCandidateEligible::class, 'enrollment_id', 'enrollment_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_user_id', 'id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuestionResponse::class, 'session_id', 'session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExamSessionItem::class, 'session_id', 'session_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AnswerEvaluation::class, 'session_id', 'session_id');
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class, 'session_id', 'session_id');
    }

    public function competencyScores(): HasMany
    {
        return $this->hasMany(CompetencyScore::class, 'session_id', 'session_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(AssessmentResult::class, 'session_id', 'session_id');
    }

    public function proctorLogs(): HasMany
    {
        return $this->hasMany(ProctorLog::class, 'session_id', 'session_id');
    }

    public function browserLockdownEvents(): HasMany
    {
        return $this->hasMany(BrowserLockdownEvent::class, 'session_id', 'session_id');
    }

    public function deviceFingerprints(): HasMany
    {
        return $this->hasMany(DeviceFingerprint::class, 'session_id', 'session_id');
    }

    public function penaltySanctions(): HasMany
    {
        return $this->hasMany(PenaltySanction::class, 'session_id', 'session_id');
    }
}
