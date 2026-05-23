<?php

declare(strict_types=1);

namespace App\Domains\Rules\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleTemplate extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'rule_templates';

    protected $primaryKey = 'template_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'template_name',
        'template_description',
        'rule_template_definition',
        'action_template_definition',
        'is_global_template',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rule_template_definition' => 'array',
            'action_template_definition' => 'array',
            'is_global_template' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
