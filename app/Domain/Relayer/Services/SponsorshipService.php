<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Manages user-level gas sponsorship eligibility.
 *
 * Grants free transactions to new or referred users,
 * tracks consumption, and checks eligibility.
 */
class SponsorshipService
{
    /**
     * Check if a user is eligible for a sponsored (free) transaction.
     */
    public function isEligible(User $user): bool
    {
        if (! config('relayer.sponsorship.enabled', false)) {
            return false;
        }

        if ($user->sponsored_tx_limit <= 0) {
            return false;
        }

        if ($user->sponsored_tx_used >= $user->sponsored_tx_limit) {
            return false;
        }

        if ($user->free_tx_until !== null && $user->free_tx_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Consume one sponsored transaction for the user.
     *
     * @return bool Whether the consumption was successful
     */
    public function consumeSponsoredTx(User $user): bool
    {
        if (! $this->isEligible($user)) {
            return false;
        }

        $user->increment('sponsored_tx_used');

        Log::info('Sponsored transaction consumed', [
            'user_id'   => $user->id,
            'used'      => $user->sponsored_tx_used,
            'limit'     => $user->sponsored_tx_limit,
            'remaining' => $this->getRemainingFreeTx($user),
        ]);

        return true;
    }

    /**
     * Get the number of remaining free transactions.
     */
    public function getRemainingFreeTx(User $user): int
    {
        if ($user->sponsored_tx_limit <= 0) {
            return 0;
        }

        if ($user->free_tx_until !== null && $user->free_tx_until->isPast()) {
            return 0;
        }

        return max(0, $user->sponsored_tx_limit - $user->sponsored_tx_used);
    }

    /**
     * Grant sponsorship to a user (e.g., on registration or referral completion).
     */
    public function grantSponsorship(User $user, int $txCount, ?int $periodDays = null): void
    {
        $periodDays ??= (int) config('relayer.sponsorship.default_free_period_days', 30);

        $user->update([
            'sponsored_tx_limit' => $user->sponsored_tx_limit + $txCount,
            'free_tx_until'      => now()->addDays($periodDays),
        ]);

        Log::info('Sponsorship granted', [
            'user_id'       => $user->id,
            'tx_count'      => $txCount,
            'period_days'   => $periodDays,
            'new_limit'     => $user->sponsored_tx_limit,
            'free_tx_until' => $user->free_tx_until?->toIso8601String(),
        ]);
    }
}
