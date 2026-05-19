<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubtype extends Model
{
    use HasFactory;

    protected $table = 'user_subtypes';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'super_admin_scope',
        'tenant_admin_organization',
        'evaluator_specialization',
        'examinee_employee_position',
        'is_proctor',
        'examinee_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'is_proctor' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examinee_manager_id', 'id');
    }
}
