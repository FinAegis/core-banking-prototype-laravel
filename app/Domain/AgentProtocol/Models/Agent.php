<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    protected $table = 'agents';

    protected $primaryKey = 'id';

    protected $fillable = [
        'agent_id',
        'did',
        'name',
        'type',
        'status',
        'network_id',
        'organization',
        'endpoints',
        'capabilities',
        'metadata',
        'relay_score',
        'last_activity_at',
    ];

    protected $casts = [
        'endpoints'        => 'array',
        'capabilities'     => 'array',
        'metadata'         => 'array',
        'relay_score'      => 'decimal:2',
        'last_activity_at' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    protected $attributes = [
        'type'         => 'standard',
        'status'       => 'pending',
        'endpoints'    => '[]',
        'capabilities' => '[]',
        'metadata'     => '[]',
        'relay_score'  => 0.00,
    ];

    /**
     * Get the agent's capabilities.
     */
    public function agentCapabilities(): HasMany
    {
        return $this->hasMany(AgentCapability::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the agent's connections.
     */
    public function connections(): HasMany
    {
        return $this->hasMany(AgentConnection::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the agent's messages sent.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'from_agent_id', 'agent_id');
    }

    /**
     * Get the agent's messages received.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'to_agent_id', 'agent_id');
    }

    /**
     * Check if agent is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if agent has a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Get the primary endpoint.
     */
    public function getPrimaryEndpoint(): ?string
    {
        if (isset($this->endpoints['primary'])) {
            return $this->endpoints['primary'];
        }

        if (isset($this->endpoints['api'])) {
            return $this->endpoints['api'];
        }

        return null;
    }

    /**
     * Get endpoint by type.
     */
    public function getEndpoint(string $type): ?string
    {
        return $this->endpoints[$type] ?? null;
    }

    /**
     * Check if agent can relay messages.
     */
    public function canRelay(): bool
    {
        return $this->hasCapability('relay') && $this->relay_score > 0;
    }

    /**
     * Update activity timestamp.
     */
    public function touchActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Scope for active agents.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for agents in a network.
     */
    public function scopeInNetwork($query, string $networkId)
    {
        return $query->where('network_id', $networkId);
    }

    /**
     * Scope for agents in an organization.
     */
    public function scopeInOrganization($query, string $organization)
    {
        return $query->where('organization', $organization);
    }

    /**
     * Scope for agents with capability.
     */
    public function scopeWithCapability($query, string $capability)
    {
        return $query->whereJsonContains('capabilities', $capability);
    }
}
