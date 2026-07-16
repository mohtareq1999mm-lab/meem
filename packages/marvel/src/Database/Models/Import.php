<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Marvel\Enums\ImportStatus;

class Import extends Model
{
    protected $table = 'imports';

    protected $fillable = [
        'type',
        'file_path',
        'file_name',
        'images_source',
        'zip_file_path',
        'status',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'errors',
        'created_by',
    ];

    protected $casts = [
        'errors' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', ImportStatus::PENDING);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            ImportStatus::COMPLETED,
            ImportStatus::COMPLETED_WITH_ERRORS,
            ImportStatus::FAILED,
        ]);
    }
}
