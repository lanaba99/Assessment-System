<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistResponse extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'checklist_responses';

    protected $primaryKey = 'response_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'manual_assessment_id',
        'checklist_item_id',
        'item_checked',
        'response_evidence_json',
        'response_notes',
        'evaluator_comment',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'item_checked' => 'boolean',
            'response_evidence_json' => 'array',
            'response_notes' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function manualAssessment(): BelongsTo
    {
        return $this->belongsTo(ManualAssessment::class, 'manual_assessment_id', 'assessment_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentChecklistItem::class, 'checklist_item_id', 'checklist_item_id');
    }
}
