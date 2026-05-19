<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'report_definitions';

    protected $primaryKey = 'report_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'report_name',
        'report_type',
        'query_configuration',
        'visual_layout',
    ];

    protected function casts(): array
    {
        return [
            'query_configuration' => 'array',
            'visual_layout' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduledReport::class, 'report_id', 'report_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'report_id', 'report_id');
    }
}
