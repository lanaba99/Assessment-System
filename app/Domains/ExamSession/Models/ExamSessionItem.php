<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Models;

use App\Domains\ExamEngine\Models\ExamSection;
use App\Domains\QuestionBank\Models\QuestionVersion;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSessionItem extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'exam_session_items';

    protected $primaryKey = 'session_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'section_id',
        'question_version_id',
        'sequence_number',
        'item_state',
        'delivered_at',
        'first_viewed_at',
        'answered_at',
        'is_flagged',
        'version_lock',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'delivered_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'answered_at' => 'datetime',
            'is_flagged' => 'boolean',
            'version_lock' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CandidateExamStatus::class, 'session_id', 'session_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'section_id', 'section_id');
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id', 'version_id');
    }
}
