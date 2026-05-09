<?php

/**
 * ConsentLogWriter — Plan B deltas Q14.2.
 *
 * Writes one row per Stripe-Web subscription creation capturing the exact consent
 * text shown, the user's IP (hashed) and User-Agent. Used for EU CRD dispute
 * trail; the row text is the snapshot, not the live config — bumping consent
 * text MUST come with `consent_version + 1`.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

final class ConsentLogWriter
{
    /**
     * Maximum tolerated staleness between consent.acceptedAt and request time.
     */
    private const STALENESS_SECONDS = 5 * 60;

    /**
     * @param array{
     *     given: bool,
     *     shownAt: string,
     *     acceptedAt: string,
     *     consentText: string,
     *     version: int
     * } $consent
     */
    public function record(
        User $user,
        array $consent,
        ?int $subscriptionId,
        string $remoteIp,
        ?string $userAgent,
    ): SubscriptionConsentLog {
        if (($consent['given'] ?? false) !== true) {
            throw new RuntimeException('ConsentLogWriter::record called with given=false; caller should reject earlier.');
        }

        $ipHash = hash_hmac('sha256', $remoteIp, (string) config('app.key'));

        /** @var SubscriptionConsentLog $row */
        $row = SubscriptionConsentLog::query()->create([
            'user_id'         => $user->id,
            'subscription_id' => $subscriptionId,
            'consent_text'    => (string) $consent['consentText'],
            'consent_version' => (int) $consent['version'],
            'shown_at'        => $consent['shownAt'],
            'accepted_at'     => $consent['acceptedAt'],
            'ip_hash'         => $ipHash,
            'user_agent'      => $userAgent,
        ]);

        return $row;
    }

    /**
     * Returns true when accepted_at is older than the staleness window.
     */
    public function isStale(string $acceptedAtIso): bool
    {
        try {
            $accepted = Carbon::parse($acceptedAtIso);
        } catch (Throwable) {
            return true;
        }

        return $accepted->diffInSeconds(now(), absolute: true) > self::STALENESS_SECONDS;
    }

    /**
     * Validate the wire shape of the withdrawalConsent payload.
     */
    public function isWellFormed(mixed $consent): bool
    {
        if (! is_array($consent)) {
            return false;
        }

        $required = ['given', 'shownAt', 'acceptedAt', 'consentText', 'version'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $consent)) {
                return false;
            }
        }

        if (($consent['given'] ?? false) !== true) {
            return false;
        }

        if (! is_string($consent['acceptedAt']) || $consent['acceptedAt'] === '') {
            return false;
        }

        if (! is_string($consent['consentText']) || $consent['consentText'] === '') {
            return false;
        }

        if (! is_int($consent['version']) || $consent['version'] < 1) {
            return false;
        }

        return true;
    }

    /**
     * The version actively shown right now. Used by the controller to embed
     * `consent_version` in the Stripe metadata.
     */
    public function activeVersion(): int
    {
        return (int) config('subscription.consent_version', 1);
    }
}
