<?php

declare(strict_types=1);

namespace App\Domains\Competency\Models;

use App\Domains\Identity\Models\User;
use App\Domains\QuestionBank\Models\Question;
use App\Domains\Shared\Traits\BelongsToTenant;
use App\Domains\Shared\Traits\UsesUuid;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A node in the competency tree (table: `competencies`).
 *
 * Mirrors the QuestionBank Category "gold standard": tenant-scoped via the
 * BelongsToTenant global scope, soft-deletable, UUID-keyed, with a
 * self-referencing parent/children hierarchy.
 */
class Competency extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;
    use UsesUuid;

    public const TYPE_KNOWLEDGE = 'knowledge';
    public const TYPE_SKILL = 'skill';
    public const TYPE_ABILITY = 'ability';

    protected $table = 'competencies';

    protected $primaryKey = 'competency_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * tenant_id (auto-filled) and created_by_user_id are server-controlled and
     * never mass-assignable; the repository writes them via forceCreate.
     */
    protected $fillable = [
        'parent_competency_id',
        'competency_name',
        'competency_code',
        'competency_type',
        'competency_category',
        'description',
        'competency_attributes',
        'hierarchy_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hierarchy_level' => 'integer',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'proficiency_level_count' => 'integer',
            'competency_attributes' => 'array',
        ];
    }

    /**
     * Explicit factory binding: the model lives outside App\Models, so the
     * default factory-name guesser cannot find CompetencyFactory.
     */
    protected static function newFactory(): CompetencyFactory
    {
        return CompetencyFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_competency_id', 'competency_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_competency_id', 'competency_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
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
