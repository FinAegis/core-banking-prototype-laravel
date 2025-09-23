<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Database\Factories\AgentIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentIdentity extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return AgentIdentityFactory::new();
    }

    protected $table = 'agent_identities';

    protected $fillable = [
        'agent_id',
        'did',
        'name',
        'type',
        'status',
        'capabilities',
        'reputation_score',
        'wallet_id',
        'metadata',
    ];

    protected $casts = [
        'capabilities'     => 'array',
        'reputation_score' => 'float',
        'metadata'         => 'array',
    ];

    public function wallet(): HasOne
    {
        return $this->hasOne(AgentWallet::class, 'agent_id', 'agent_id');
    }

    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'from_agent_id', 'agent_id');
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'to_agent_id', 'agent_id');
    }

    public function sentEscrows(): HasMany
    {
        return $this->hasMany(Escrow::class, 'sender_agent_id', 'agent_id');
    }

    public function receivedEscrows(): HasMany
    {
        return $this->hasMany(Escrow::class, 'receiver_agent_id', 'agent_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(EscrowDispute::class, 'disputed_by', 'agent_id');
    }

    public function getReputationScoreAttribute($value): float
    {
        return (float) $value;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function getCapabilitiesAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getTrustLevel(): string
    {
        $score = $this->reputation_score;

        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 75) {
            return 'good';
        } elseif ($score >= 50) {
            return 'neutral';
        } elseif ($score >= 25) {
            return 'low';
        } else {
            return 'poor';
        }
    }
}
