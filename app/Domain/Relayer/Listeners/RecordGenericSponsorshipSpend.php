<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Listeners;

use App\Domain\Relayer\Events\TransactionSponsored;
use App\Domain\Wallet\Services\SponsorshipCostTracker;

/**
 * Charges generic relayer sponsorship (REST POST /api/v1/relayer/sponsor,
 * GraphQL sponsorTransaction) to the shared daily USD sponsorship budget
 * (WALLET_SPONSORSHIP_DAILY_BUDGET_USD).
 *
 * Wallet sends add their real fee at confirmation; relayer-sponsored ops
 * leave no record the pollers reconcile, so without this the USD budget was
 * checked for them but never moved by them. The paymaster's USD fee estimate
 * carried by the event is charged as soon as the op is submitted.
 */
final class RecordGenericSponsorshipSpend
{
    public function __construct(
        private readonly SponsorshipCostTracker $costTracker,
    ) {
    }

    public function handle(TransactionSponsored $event): void
    {
        // The event carries the paymaster's float estimate; convert once at
        // the boundary and keep all arithmetic in bcmath from here on.
        $this->costTracker->recordEstimatedSpendUsd(number_format($event->feeAmount, 6, '.', ''));
    }
}
