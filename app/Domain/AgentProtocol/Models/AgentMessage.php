<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    use UsesTenantConnection;

    protected $table = 'agent_messages';

    protected $fillable = [
        'message_id',
        'from_agent_id',
        'to_agent_id',
        'message_type',
        'priority',
        'status',
        'payload',
        'headers',
        'correlation_id',
        'reply_to',
        'requires_acknowledgment',
        'acknowledgment_timeout',
        'acknowledged_at',
        'delivered_at',
        'failed_at',
        'retry_count',
        'next_retry_at',
        'metadata',
    ];

    protected $casts = [
        'priority'                => 'integer',
        'payload'                 => 'array',
        'headers'                 => 'array',
        'requires_acknowledgment' => 'boolean',
        'acknowledgment_timeout'  => 'integer',
        'retry_count'             => 'integer',
        'metadata'                => 'array',
        'acknowledged_at'         => 'datetime',
        'delivered_at'            => 'datetime',
        'failed_at'               => 'datetime',
        'next_retry_at'           => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    protected $attributes = [
        'message_type'            => 'direct',
        'priority'                => 50,
        'status'                  => 'pending',
        'headers'                 => '[]',
        'requires_acknowledgment' => false,
        'retry_count'             => 0,
        'metadata'                => '[]',
    ];

    /**
     * Get the sender agent.
     */
    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'from_agent_id', 'agent_id');
    }

    /**
     * Get the recipient agent.
     */
    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'to_agent_id', 'agent_id');
    }

    /**
     * Check if message is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered' && $this->delivered_at !== null;
    }

    /**
     * Check if message is acknowledged.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Check if message failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' && $this->failed_at !== null;
    }

    /**
     * Check if message can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retry_count < 5 && $this->status === 'failed';
    }

    /**
     * Scope for pending messages.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed messages.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for messages requiring acknowledgment.
     */
    public function scopeRequiringAcknowledgment($query)
    {
        return $query->where('requires_acknowledgment', true)
            ->whereNull('acknowledged_at');
    }
}
