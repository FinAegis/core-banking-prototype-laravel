<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCapability extends Model
{
    protected $table = 'agent_capabilities';

    protected $fillable = [
        'agent_id',
        'capability_id',
        'name',
        'description',
        'version',
        'status',
        'endpoints',
        'parameters',
        'required_permissions',
        'supported_protocols',
        'category',
        'priority',
        'is_public',
        'rate_limits',
        'metadata',
    ];

    protected $casts = [
        'endpoints'            => 'array',
        'parameters'           => 'array',
        'required_permissions' => 'array',
        'supported_protocols'  => 'array',
        'rate_limits'          => 'array',
        'metadata'             => 'array',
        'priority'             => 'integer',
        'is_public'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    protected $attributes = [
        'version'              => '1.0.0',
        'status'               => 'draft',
        'endpoints'            => '[]',
        'parameters'           => '[]',
        'required_permissions' => '[]',
        'supported_protocols'  => '["AP2","A2A"]',
        'rate_limits'          => '[]',
        'metadata'             => '[]',
        'priority'             => 50,
        'is_public'            => true,
    ];

    /**
     * Get the agent that owns this capability.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Check if capability is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if capability supports a protocol.
     */
    public function supportsProtocol(string $protocol): bool
    {
        return in_array($protocol, $this->supported_protocols ?? [], true);
    }
}
