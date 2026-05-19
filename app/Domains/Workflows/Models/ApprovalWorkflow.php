<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalWorkflow extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'approval_workflows';

    protected $primaryKey = 'workflow_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'initiated_by_user_id',
        'resource_id',
        'resource_type',
        'workflow_type',
        'workflow_stages_json',
        'current_stage_key',
        'current_workflow_status',
        'workflow_initiated_at',
        'workflow_completed_at',
        'workflow_metadata',
    ];

    protected function casts(): array
    {
        return [
            'workflow_stages_json' => 'array',
            'workflow_initiated_at' => 'datetime',
            'workflow_completed_at' => 'datetime',
            'workflow_metadata' => 'array',
        ];
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id', 'id');
    }

    public function resource(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'resource_type', 'resource_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(WorkflowState::class, 'workflow_id', 'workflow_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class, 'workflow_id', 'workflow_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'workflow_id', 'workflow_id');
    }
}
