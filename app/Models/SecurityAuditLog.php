<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use HasFactory;

    protected $table = 'security_audit_logs';

    protected $fillable = [
        'event_type',
        'severity',
        'transaction_id',
        'agent_id',
        'user_id',
        'reason',
        'context',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /**
     * Get the agent associated with this audit log.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the user associated with this audit log (if applicable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for critical events.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for high severity events.
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['critical', 'high']);
    }

    /**
     * Scope for recent events.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for specific agent.
     */
    public function scopeForAgent($query, string $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope for specific transaction.
     */
    public function scopeForTransaction($query, string $transactionId)
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope for security failures.
     */
    public function scopeSecurityFailures($query)
    {
        return $query->where('event_type', 'security_failure');
    }

    /**
     * Get severity color for UI.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high'     => 'orange',
            'medium'   => 'yellow',
            'low'      => 'blue',
            default    => 'gray',
        };
    }

    /**
     * Get formatted context for display.
     */
    public function getFormattedContextAttribute(): string
    {
        $json = json_encode($this->context, JSON_PRETTY_PRINT);

        return $json !== false ? $json : '{}';
    }
}
