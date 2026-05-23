<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Models;

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDashboard extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'group_dashboards';

    protected $primaryKey = 'dashboard_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'cohort_id',
        'created_by_user_id',
        'tenant_id',
        'dashboard_name',
        'dashboard_config',
        'dashboard_widgets',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dashboard_config' => 'array',
            'dashboard_widgets' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id', 'cohort_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
