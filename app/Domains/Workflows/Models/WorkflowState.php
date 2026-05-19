<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowState extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'workflow_states';

    protected $primaryKey = 'state_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'state_sequence',
        'state_key',
        'state_name',
        'state_description',
        'required_approvals_json',
        'approval_pattern',
        'state_metadata',
    ];

    protected function casts(): array
    {
        return [
            'state_sequence' => 'integer',
            'required_approvals_json' => 'array',
            'state_metadata' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id', 'workflow_id');
    }
}
