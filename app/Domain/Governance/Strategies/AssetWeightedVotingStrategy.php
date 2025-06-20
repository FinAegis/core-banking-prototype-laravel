<?php

declare(strict_types=1);

namespace App\Domain\Governance\Strategies;

use App\Domain\Governance\Contracts\VotingPowerStrategy;
use App\Domain\Governance\Models\Poll;
use App\Models\User;
use App\Models\Account;

class AssetWeightedVotingStrategy implements VotingPowerStrategy
{
    /**
     * Calculate voting power based on user's primary asset holdings
     */
    public function calculatePower(User $user, Poll $poll): int
    {
        // Get all user accounts
        $accounts = Account::where('user_uuid', $user->uuid)->get();
        
        // Sum up primary asset holdings across all accounts
        $primaryAsset = config('baskets.primary_code', 'PRIMARY');
        $totalBalance = 0;
        
        foreach ($accounts as $account) {
            // Check if account has primary asset balance
            $balance = $account->getBalance($primaryAsset);
            $totalBalance += $balance;
        }
        
        // Convert balance to voting power
        // 1 unit = 1 voting power (in cents, so divide by 100)
        $votingPower = intval($totalBalance / 100);
        
        // Return actual voting power (0 if no holdings)
        return $votingPower;
    }
    
    /**
     * Get description of this voting strategy
     */
    public function getDescription(): string
    {
        return 'Voting power is proportional to primary asset holdings. 1 unit = 1 vote.';
    }
    
    /**
     * Validate if user is eligible to vote
     */
    public function isEligible(User $user, Poll $poll): bool
    {
        // User must have at least some primary asset to vote
        $primaryAsset = config('baskets.primary_code', 'PRIMARY');
        $accounts = Account::where('user_uuid', $user->uuid)->get();
        
        foreach ($accounts as $account) {
            if ($account->getBalance($primaryAsset) > 0) {
                return true;
            }
        }
        
        return false;
    }
}