<?php

namespace App\Domain\Batch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchJob extends Model
{
    protected $fillable = [
        'uuid',
        'user_uuid',
        'name',
        'type',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'scheduled_at',
        'started_at',
        'completed_at',
        'metadata',
    ];
    
    protected $casts = [
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'failed_items' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    /**
     * Get the items for this batch job
     */
    public function items(): HasMany
    {
        return $this->hasMany(BatchItem::class);
    }
    
    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }
        
        return round(($this->processed_items / $this->total_items) * 100, 1);
    }
    
    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_items === 0) {
            return 0;
        }
        
        $successfulItems = $this->processed_items - $this->failed_items;
        return round(($successfulItems / $this->processed_items) * 100, 1);
    }
    
    /**
     * Check if batch can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'scheduled']);
    }
    
    /**
     * Check if batch can be retried
     */
    public function canBeRetried(): bool
    {
        return $this->status === 'failed' || 
               ($this->status === 'completed' && $this->failed_items > 0);
    }
}