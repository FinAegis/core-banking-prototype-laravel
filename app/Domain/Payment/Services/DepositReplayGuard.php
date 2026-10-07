<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;

/**
 * Credits a given external payment reference at most once.
 *
 * Reuses the `processed_webhook_events` (provider, event_id) unique index
 * that the Stripe, IAP, Bridge and HyperSwitch webhooks already dedupe on:
 * `provider` is the deposit source (see the SOURCE_* constants) and
 * `event_id` the external reference (PaymentIntent id, order id). The
 * insert either wins the unique index or finds the existing row, so two
 * concurrent confirmations of the same reference cannot both credit.
 *
 * The row lives on the default connection; callers credit the ledger after
 * the claim (never inside one transaction spanning both connections) and
 * release the claim when crediting fails, so the user can retry.
 */
final class DepositReplayGuard
{
    public const SOURCE_STRIPE = 'stripe_deposit';

    public const SOURCE_PAYSERA = 'paysera_deposit';

    /**
     * Claim the reference. Returns false when it was already claimed.
     */
    public function claim(string $source, string $externalReference): bool
    {
        $row = ProcessedWebhookEvent::query()->createOrFirst(
            ['provider' => $source, 'event_id' => $externalReference],
            ['event_type' => 'deposit_credit', 'processed_at' => now()],
        );

        return $row->wasRecentlyCreated;
    }

    /**
     * Give a claim back (the credit could not be started).
     */
    public function release(string $source, string $externalReference): void
    {
        ProcessedWebhookEvent::query()
            ->where('provider', $source)
            ->where('event_id', $externalReference)
            ->delete();
    }

    public function isClaimed(string $source, string $externalReference): bool
    {
        return ProcessedWebhookEvent::query()
            ->where('provider', $source)
            ->where('event_id', $externalReference)
            ->exists();
    }
}
