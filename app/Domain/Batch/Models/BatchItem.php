<?php

namespace App\Domain\Batch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchItem extends Model
{
    protected $fillable = [
        'batch_job_id',
        'sequence',
        'type',
        'status',
        'data',
        'result',
        'error_message',
        'processed_at',
        'retry_count',
    ];
    
    protected $casts = [
        'sequence' => 'integer',
        'data' => 'array',
        'result' => 'array',
        'processed_at' => 'datetime',
        'retry_count' => 'integer',
    ];
    
    /**
     * Get the batch job this item belongs to
     */
    public function batchJob(): BelongsTo
    {
        return $this->belongsTo(BatchJob::class);
    }
    
    /**
     * Check if item can be retried
     */
    public function canBeRetried(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }
}