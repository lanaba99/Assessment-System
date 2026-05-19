<?php

declare(strict_types=1);

namespace App\Domains\Penalties\Models;

use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltySanction extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'penalty_sanctions';

    protected $primaryKey = 'sanction_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'candidate_user_id',
        'penalty_rule_id',
        'tenant_id',
        'sanction_applied_at',
        'sanction_reason',
        'sanction_amount',
        'sanction_type',
        'sanction_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sanction_applied_at' => 'datetime',
            'sanction_amount' => 'decimal:4',
            'sanction_metadata' => 'array',
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

    public function penaltyRule(): BelongsTo
    {
        return $this->belongsTo(PenaltyRule::class, 'penalty_rule_id', 'penalty_rule_id');
    }
}
