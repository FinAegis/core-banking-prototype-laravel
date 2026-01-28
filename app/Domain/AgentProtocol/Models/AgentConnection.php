<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConnection extends Model
{
    use UsesTenantConnection;

    protected $table = 'agent_connections';

    protected $fillable = [
        'agent_id',
        'connected_agent_id',
        'connection_type',
        'status',
        'latency_ms',
        'bandwidth_mbps',
        'reliability_score',
        'last_contact_at',
        'metadata',
    ];

    protected $casts = [
        'latency_ms'        => 'integer',
        'bandwidth_mbps'    => 'decimal:2',
        'reliability_score' => 'decimal:2',
        'metadata'          => 'array',
        'last_contact_at'   => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    protected $attributes = [
        'connection_type'   => 'direct',
        'status'            => 'pending',
        'latency_ms'        => 0,
        'bandwidth_mbps'    => 0.00,
        'reliability_score' => 0.00,
        'metadata'          => '[]',
    ];

    /**
     * Get the agent that owns this connection.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the connected agent.
     */
    public function connectedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'connected_agent_id', 'agent_id');
    }

    /**
     * Check if connection is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if connection is reliable.
     */
    public function isReliable(): bool
    {
        return $this->reliability_score >= 0.8;
    }
}
