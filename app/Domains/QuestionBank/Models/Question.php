<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Competency\Models\Competency;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'questions';

    protected $primaryKey = 'question_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'created_by_user_id',
        'current_version_id',
        'question_title',
        'question_type',
        'difficulty_level',
        'cognitive_level',
        'is_randomizable',
        'requires_media_attachment',
        'is_deprecated',
        'is_archived',
        'total_usage_count',
        'question_metadata',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'difficulty_level' => 'integer',
            'cognitive_level' => 'integer',
            'is_randomizable' => 'boolean',
            'requires_media_attachment' => 'boolean',
            'is_deprecated' => 'boolean',
            'is_archived' => 'boolean',
            'total_usage_count' => 'integer',
            'question_metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'category_id', 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'current_version_id', 'version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class, 'question_id', 'question_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QuestionAttachment::class, 'question_id', 'question_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(QuestionTag::class, 'question_id', 'question_id');
    }

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Competency::class,
            'question_competency_weights',
            'question_id',
            'competency_id',
            'question_id',
            'competency_id',
        )->withPivot([
            'weight_id',
            'weight_percentage',
            'skill_category',
            'skill_gap_trigger',
            'is_primary_competency',
            'weighting_metadata',
        ])->withTimestamps();
    }
}
