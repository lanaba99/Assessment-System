<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\AutoFillsTenantId;
use App\Domains\Shared\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAttachment extends Model
{
    use AutoFillsTenantId;
    use HasFactory;
    use UsesUuid;

    protected $table = 'media_assets';

    protected $primaryKey = 'asset_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'question_id',
        'uploaded_by_user_id',
        'asset_type',
        'file_name',
        'file_path',
        'file_url',
        'file_size_bytes',
        'storage_location',
        'mime_type',
        'virus_scan_status',
        'asset_metadata',
        'uploaded_at',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'asset_metadata' => 'array',
            'uploaded_at' => 'datetime',
            'scanned_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id', 'id');
    }
}
