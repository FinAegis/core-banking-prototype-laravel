<?php

declare(strict_types=1);

namespace App\Domain\Governance\Contracts;

use App\Domain\Governance\Models\Poll;
use App\Models\User;

interface VotingPowerStrategy
{
    /**
     * Calculate the voting power for a user in a specific poll
     */
    public function calculatePower(User $user, Poll $poll): int;
    
    /**
     * Get a human-readable description of this voting strategy
     */
    public function getDescription(): string;
    
    /**
     * Check if a user is eligible to vote in this poll
     */
    public function isEligible(User $user, Poll $poll): bool;
}