<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class GcuVotingProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'proposed_composition',
        'current_composition',
        'rationale',
        'status',
        'voting_starts_at',
        'voting_ends_at',
        'minimum_participation',
        'minimum_approval',
        'total_gcu_supply',
        'total_votes_cast',
        'votes_for',
        'votes_against',
        'created_by',
        'implemented_at',
        'implementation_details',
    ];

    protected $casts = [
        'proposed_composition' => 'array',
        'current_composition' => 'array',
        'implementation_details' => 'array',
        'voting_starts_at' => 'datetime',
        'voting_ends_at' => 'datetime',
        'implemented_at' => 'datetime',
        'minimum_participation' => 'decimal:2',
        'minimum_approval' => 'decimal:2',
        'total_gcu_supply' => 'decimal:4',
        'total_votes_cast' => 'decimal:4',
        'votes_for' => 'decimal:4',
        'votes_against' => 'decimal:4',
    ];

    /**
     * Get the creator of the proposal
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all votes for this proposal
     */
    public function votes(): HasMany
    {
        return $this->hasMany(GcuVote::class, 'proposal_id');
    }

    /**
     * Check if voting is currently active
     */
    public function isVotingActive(): bool
    {
        return $this->status === 'active' 
            && now()->between($this->voting_starts_at, $this->voting_ends_at);
    }

    /**
     * Check if the proposal has passed
     */
    public function hasPassed(): bool
    {
        if ($this->status !== 'closed') {
            return false;
        }

        $participationRate = ($this->total_votes_cast / $this->total_gcu_supply) * 100;
        if ($participationRate < $this->minimum_participation) {
            return false;
        }

        $approvalRate = ($this->votes_for / $this->total_votes_cast) * 100;
        return $approvalRate >= $this->minimum_approval;
    }

    /**
     * Get the participation rate
     */
    public function getParticipationRateAttribute(): float
    {
        if (!$this->total_gcu_supply || $this->total_gcu_supply == 0) {
            return 0;
        }
        return ($this->total_votes_cast / $this->total_gcu_supply) * 100;
    }

    /**
     * Get the approval rate
     */
    public function getApprovalRateAttribute(): float
    {
        if ($this->total_votes_cast == 0) {
            return 0;
        }
        return ($this->votes_for / $this->total_votes_cast) * 100;
    }

    /**
     * Get time remaining for voting
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->isVotingActive()) {
            return null;
        }
        
        return now()->diffForHumans($this->voting_ends_at, [
            'parts' => 2,
            'short' => true,
        ]);
    }

    /**
     * Scope for active proposals
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('voting_starts_at', '<=', now())
                    ->where('voting_ends_at', '>', now());
    }

    /**
     * Scope for upcoming proposals
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'active')
                    ->where('voting_starts_at', '>', now());
    }

    /**
     * Scope for past proposals
     */
    public function scopePast($query)
    {
        return $query->whereIn('status', ['closed', 'implemented', 'rejected']);
    }
}