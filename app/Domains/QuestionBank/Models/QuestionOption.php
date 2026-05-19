<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'question_options';

    protected $primaryKey = 'option_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'version_id',
        'option_sequence',
        'option_text',
        'is_correct',
        'option_metadata',
    ];

    protected function casts(): array
    {
        return [
            'option_sequence' => 'integer',
            'is_correct' => 'boolean',
            'option_metadata' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'version_id', 'version_id');
    }
}
