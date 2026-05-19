<?php

declare(strict_types=1);

namespace App\Domains\ExamEngine\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSection extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_sections';

    protected $primaryKey = 'section_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'exam_id',
        'section_name',
        'section_code',
        'section_sequence',
        'questions_in_section',
        'time_limit_minutes',
        'branching_logic',
        'section_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'section_sequence' => 'integer',
            'questions_in_section' => 'integer',
            'time_limit_minutes' => 'integer',
            'branching_logic' => 'array',
            'section_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }
}
