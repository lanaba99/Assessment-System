<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowApproval extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'workflow_approvals';

    protected $primaryKey = 'approval_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'workflow_id',
        'approver_user_id',
        'approval_stage_number',
        'approval_status',
        'approval_evidence_json',
        'approval_comments',
        'approved_at',
        'approval_deadline_at',
        'can_reject',
        'can_request_changes',
    ];

    protected function casts(): array
    {
        return [
            'approval_stage_number' => 'integer',
            'approval_evidence_json' => 'array',
            'approval_comments' => 'array',
            'approved_at' => 'datetime',
            'approval_deadline_at' => 'datetime',
            'can_reject' => 'boolean',
            'can_request_changes' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id', 'workflow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id', 'id');
    }
}
