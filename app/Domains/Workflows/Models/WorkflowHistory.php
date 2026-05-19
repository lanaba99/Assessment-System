<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowHistory extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'workflow_history';

    protected $primaryKey = 'history_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'workflow_id',
        'actor_user_id',
        'action_type',
        'old_state',
        'new_state',
        'transition_metadata',
    ];

    protected function casts(): array
    {
        return [
            'transition_metadata' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id', 'workflow_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }
}
