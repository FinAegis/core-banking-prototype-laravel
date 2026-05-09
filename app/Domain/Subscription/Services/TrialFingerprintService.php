<?php

/**
 * TrialFingerprintService — Plan B Backend-Q5 trial-abuse check.
 *
 * - Hashes Stripe `card.fingerprint` with HMAC-SHA256(.., TRIAL_FINGERPRINT_PEPPER).
 * - Eligibility window: 12 months from `last_used_at`. If a fingerprint is stored
 *   AND `last_used_at + 12mo > now()` → NOT eligible (return ERR_SUB_003 with
 *   `eligibleAfter`).
 *
 * Pepper is rotation-cheap (re-hash all rows in one transaction), but rotation is
 * event-triggered only — no scheduled cron, no `pepper_version` column for v1.3.0.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class TrialFingerprintService
{
    public const RETRY_WINDOW_MONTHS = 12;

    /**
     * Stable HMAC-SHA256 hash of a Stripe card fingerprint.
     */
    public function hash(string $fingerprint): string
    {
        $pepper = (string) config('services.stripe.trial_fingerprint_pepper');

        if ($pepper === '') {
            throw new RuntimeException(
                'TRIAL_FINGERPRINT_PEPPER is not configured — refusing to hash with an empty key.'
            );
        }

        return hash_hmac('sha256', $fingerprint, $pepper);
    }

    /**
     * Check trial eligibility. If ineligible, returns the timestamp at which
     * the caller becomes eligible again; null when eligible right now.
     */
    public function eligibleAfter(string $fingerprintHash): ?Carbon
    {
        /** @var TrialCardFingerprint|null $row */
        $row = TrialCardFingerprint::query()->find($fingerprintHash);

        if ($row === null) {
            return null;
        }

        $eligibleAt = $row->last_used_at->copy()->addMonths(self::RETRY_WINDOW_MONTHS);

        return $eligibleAt->isFuture() ? $eligibleAt : null;
    }

    /**
     * Convenience predicate: is this user allowed to start a trial right now?
     */
    public function isEligible(string $fingerprintHash, int $userId): bool
    {
        return $this->eligibleAfter($fingerprintHash) === null;
    }

    /**
     * Record a trial claim. Idempotent: re-claiming the same hash bumps the
     * counter and updates last_used_at.
     */
    public function recordClaim(string $fingerprintHash, User $user, ?string $stripePaymentMethodId = null): void
    {
        /** @var TrialCardFingerprint|null $existing */
        $existing = TrialCardFingerprint::query()->find($fingerprintHash);

        if ($existing === null) {
            TrialCardFingerprint::query()->create([
                'fingerprint_hash'         => $fingerprintHash,
                'first_user_id'            => $user->id,
                'last_user_id'             => $user->id,
                'first_used_at'            => now(),
                'last_used_at'             => now(),
                'trial_user_count'         => 1,
                'stripe_payment_method_id' => $stripePaymentMethodId,
            ]);

            return;
        }

        $existing->forceFill([
            'last_user_id'             => $user->id,
            'last_used_at'             => now(),
            'trial_user_count'         => $existing->trial_user_count + 1,
            'stripe_payment_method_id' => $stripePaymentMethodId ?? $existing->stripe_payment_method_id,
        ])->save();
    }
}
