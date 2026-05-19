<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentChecklistItem extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'assessment_checklist_items';

    protected $primaryKey = 'checklist_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'exam_id',
        'tenant_id',
        'created_by_user_id',
        'item_description',
        'item_assessment_criteria',
        'item_weight_percentage',
        'display_sequence',
        'item_category',
        'item_metadata',
    ];

    protected function casts(): array
    {
        return [
            'item_assessment_criteria' => 'array',
            'item_weight_percentage' => 'integer',
            'display_sequence' => 'integer',
            'item_metadata' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ChecklistResponse::class, 'checklist_item_id', 'checklist_item_id');
    }
}
