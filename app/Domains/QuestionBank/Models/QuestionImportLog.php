<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class QuestionImportLog extends Model
{
    use AutoFillsTenantId;
    use UsesUuid;

    protected $table = 'question_import_logs';

    protected $primaryKey = 'import_log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'imported_by_user_id',
        'import_source',
        'total_questions_imported',
        'successful_imports',
        'failed_imports',
        'error_details',
        'import_started_at',
        'import_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_details' => 'array',
            'import_started_at' => 'datetime',
            'import_completed_at' => 'datetime',
        ];
    }
}