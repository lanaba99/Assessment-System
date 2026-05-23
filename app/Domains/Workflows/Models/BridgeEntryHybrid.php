<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeEntryHybrid extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'bridge_entry_hybrid';

    protected $primaryKey = 'bridge_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'candidate_user_id',
        'correlated_session_id',
        'entry_source_system',
        'paper_assessment_data',
        'digital_assessment_data',
        'merged_assessment_data',
        'merge_status',
        'merge_metadata',
        'paper_data_received_at',
        'digital_data_received_at',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'paper_assessment_data' => 'array',
            'digital_assessment_data' => 'array',
            'merged_assessment_data' => 'array',
            'merge_metadata' => 'array',
            'paper_data_received_at' => 'datetime',
            'digital_data_received_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function correlatedSession(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'correlated_session_id', 'session_id');
    }
}
