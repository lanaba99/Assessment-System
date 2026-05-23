<?php

declare(strict_types=1);

namespace App\Domains\Competency\Models;

use App\Domains\ExamEngine\Models\ExamConfig;
use App\Domains\Grading\Models\CompetencyScore;
use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    use HasFactory;
    use UsesUuid;

    public const TYPE_KNOWLEDGE = 'knowledge';
    public const TYPE_SKILL = 'skill';
    public const TYPE_ABILITY = 'ability';

    protected $table = 'competencies';

    protected $primaryKey = 'competency_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'competency_name',
        'competency_code',
        'competency_type',
        'competency_category',
        'description',
        'competency_attributes',
        'is_mandatory',
        'is_active',
        'proficiency_level_count',
    ];

    protected function casts(): array
    {
        return [
            'competency_attributes' => 'array',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'proficiency_level_count' => 'integer',
        ];
    }

    /**
     * Parent reference is persisted in competency_attributes JSON because
     * the schema does not (yet) have a dedicated FK column. Read/write
     * exclusively through this accessor so the storage detail stays here.
     */
    protected function parentCompetencyId(): Attribute
    {
        return Attribute::make(
            get: function (mixed $_, array $attributes): ?string {
                $raw = $attributes['competency_attributes'] ?? null;
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $parent = is_array($decoded) ? ($decoded['parent_competency_id'] ?? null) : null;

                return is_string($parent) ? $parent : null;
            },
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CompetencyLevel::class, 'competency_id', 'competency_id');
    }

    public function examConfigs(): HasMany
    {
        return $this->hasMany(ExamConfig::class, 'competency_id', 'competency_id');
    }

    public function competencyScores(): HasMany
    {
        return $this->hasMany(CompetencyScore::class, 'competency_id', 'competency_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(
            Question::class,
            'question_competency_weights',
            'competency_id',
            'question_id',
            'competency_id',
            'question_id',
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
