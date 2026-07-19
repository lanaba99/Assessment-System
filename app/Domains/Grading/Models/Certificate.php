<?php

declare(strict_types=1);

namespace App\Domains\Grading\Models;

use App\Domains\ExamEngine\Models\Exam;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use AutoFillsTenantId;
    use UsesUuid;

    protected $table = 'certificates';

    protected $primaryKey = 'certificate_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'candidate_user_id',
        'assessment_result_id',
        'exam_id',
        'certificate_code',
        'qr_code_data',
        'digital_signature',
        'certificate_metadata',
        'issued_at',
        'expires_at',
        'verification_status',
        'additional_credentials',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'certificate_metadata' => 'array',
            'additional_credentials' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id', 'id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class, 'assessment_result_id', 'result_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }
}