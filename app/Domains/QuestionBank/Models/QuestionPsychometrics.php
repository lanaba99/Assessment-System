<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionPsychometrics extends Model
{
    use HasFactory;
    use UsesUuid;

    protected $table = 'question_psychometrics';

    protected $primaryKey = 'psychometric_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'question_version_id',
        'tenant_id',
        'difficulty_index',
        'discrimination_index',
        'point_biserial',
        'sample_size',
        'correct_count',
        'is_calibrated',
        'calibration_status',
        'calibration_metadata',
        'last_calibrated_at',
    ];

    protected function casts(): array
    {
        return [
            'difficulty_index' => 'decimal:4',
            'discrimination_index' => 'decimal:4',
            'point_biserial' => 'decimal:4',
            'sample_size' => 'integer',
            'correct_count' => 'integer',
            'is_calibrated' => 'boolean',
            'calibration_metadata' => 'array',
            'last_calibrated_at' => 'datetime',
        ];
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id', 'version_id');
    }
}
