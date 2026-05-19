<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBank extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'categories';

    protected $primaryKey = 'category_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'parent_category_id',
        'category_name',
        'category_code',
        'category_description',
        'display_order',
        'hierarchy_level',
        'is_locked',
        'is_active',
        'category_metadata',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'hierarchy_level' => 'integer',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
            'category_metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_category_id', 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_category_id', 'category_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'category_id', 'category_id');
    }
}
