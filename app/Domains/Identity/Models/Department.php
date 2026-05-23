<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'departments';

    protected $primaryKey = 'department_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_department_id',
        'department_name',
        'department_code',
        'department_manager_id',
        'hierarchy_level',
        'department_attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hierarchy_level' => 'integer',
            'is_active' => 'boolean',
            'department_attributes' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_manager_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_department_id', 'department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_department_id', 'department_id');
    }
}
