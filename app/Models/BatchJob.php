<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_uuid',
        'name',
        'type',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'metadata',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'failed_items' => 'integer',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who created the batch job
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the batch job items
     */
    public function items(): HasMany
    {
        return $this->hasMany(BatchJobItem::class, 'batch_job_uuid', 'uuid');
    }

    /**
     * Check if the batch job is complete
     */
    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the batch job has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the completion percentage
     */
    public function getCompletionPercentage(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return round(($this->processed_items / $this->total_items) * 100, 2);
    }
}