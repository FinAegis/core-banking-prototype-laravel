<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'agent_messages';

    protected $fillable = [
        'message_id',
        'sender_agent_id',
        'receiver_agent_id',
        'type',
        'priority',
        'content',
        'metadata',
        'status',
        'acknowledged_at',
        'expires_at',
        'retry_count',
        'last_retry_at',
    ];

    protected $casts = [
        'content' => 'array',
        'metadata' => 'array',
        'acknowledged_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Default attributes.
     */
    protected $attributes = [
        'type' => 'general',
        'priority' => 'normal',
        'status' => 'pending',
        'content' => '{}',
        'metadata' => '{}',
        'retry_count' => 0,
    ];

    /**
     * Scope for pending messages.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for delivered messages.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope for acknowledged messages.
     */
    public function scopeAcknowledged($query)
    {
        return $query->whereNotNull('acknowledged_at');
    }

    /**
     * Scope for expired messages.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Check if message is acknowledged.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Check if message is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark message as acknowledged.
     */
    public function markAsAcknowledged(): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update(['last_retry_at' => now()]);
    }
}