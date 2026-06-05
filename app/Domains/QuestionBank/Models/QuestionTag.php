<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Shared\Traits\BelongsToTenant;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTag extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use UsesUuid;

    protected $table = 'question_tags';

    protected $primaryKey = 'tag_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    // tenant_id is auto-filled by BelongsToTenant.
    protected $fillable = [
        'question_id',
        'tag_name',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}
