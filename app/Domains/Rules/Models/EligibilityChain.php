<?php

declare(strict_types=1);

namespace App\Domains\Rules\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EligibilityChain extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'eligibility_chains';

    protected $primaryKey = 'chain_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'created_by_user_id',
        'chain_step_number',
        'prerequisite_exam_id',
        'condition_type',
        'condition_data',
        'logical_operator',
        'min_score_required',
        'is_satisfied_override_available',
        'override_authorized_by_user_id',
        'chain_metadata',
    ];

    protected function casts(): array
    {
        return [
            'chain_step_number' => 'integer',
            'condition_data' => 'array',
            'min_score_required' => 'decimal:2',
            'is_satisfied_override_available' => 'boolean',
            'chain_metadata' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function prerequisiteExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'prerequisite_exam_id', 'exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function overrideAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_authorized_by_user_id', 'id');
    }
}
