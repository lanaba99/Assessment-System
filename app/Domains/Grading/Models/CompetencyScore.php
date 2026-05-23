<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\Competency\Models\Competency;
use App\Domains\ExamSession\Models\CandidateExamStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyScore extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'competency_scores';

    protected $primaryKey = 'score_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'candidate_user_id',
        'session_id',
        'competency_id',
        'tenant_id',
        'score_achieved',
        'score_target',
        'score_maximum',
        'proficiency_level_achieved',
        'gap_percentage',
        'gap_status',
        'score_metadata',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score_achieved' => 'decimal:2',
            'score_target' => 'decimal:2',
            'score_maximum' => 'decimal:2',
            'proficiency_level_achieved' => 'integer',
            'gap_percentage' => 'decimal:2',
            'score_metadata' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'competency_id');
    }
}
