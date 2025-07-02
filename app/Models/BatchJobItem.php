<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchJobItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_job_uuid',
        'sequence',
        'status',
        'data',
        'result',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'data' => 'array',
        'result' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the batch job this item belongs to
     */
    public function batchJob(): BelongsTo
    {
        return $this->belongsTo(BatchJob::class, 'batch_job_uuid', 'uuid');
    }

    /**
     * Check if the item is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the item is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the item is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the item has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}