<?php

declare(strict_types=1);

namespace App\Domains\Cohorts\Models;

use App\Domains\ExamSession\Models\ExamCandidateEligible;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cohort extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'cohorts';

    protected $primaryKey = 'cohort_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_cohort_id',
        'created_by_user_id',
        'cohort_name',
        'cohort_code',
        'cohort_type',
        'cohort_description',
        'hierarchy_level',
        'cohort_attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hierarchy_level' => 'integer',
            'cohort_attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_cohort_id', 'cohort_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_cohort_id', 'cohort_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CohortMember::class, 'cohort_id', 'cohort_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'cohort_members',
            'cohort_id',
            'user_id',
            'cohort_id',
            'id',
        )->withPivot([
            'member_id',
            'tenant_id',
            'membership_role',
            'added_at',
            'removed_at',
            'is_active_member',
        ]);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ExamCandidateEligible::class, 'cohort_id', 'cohort_id');
    }
}
