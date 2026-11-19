<?php

declare(strict_types=1);

namespace App\Domain\OpenBanking\Services;

use App\Domain\OpenBanking\Enums\ConsentStatus;
use App\Domain\OpenBanking\Models\Consent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class ConsentService
{
    /**
     * Create a new consent request.
     *
     * @param array<string> $permissions
     * @param array<string>|null $accountIds
     */
    public function createConsent(
        string $tppId,
        int $userId,
        array $permissions,
        ?array $accountIds = null,
    ): Consent {
        return Consent::create([
            'tpp_id'              => $tppId,
            'user_id'             => $userId,
            'status'              => ConsentStatus::AWAITING_AUTHORIZATION,
            'permissions'         => $permissions,
            'account_ids'         => $accountIds,
            'expires_at'          => Carbon::now()->addDays((int) config('openbanking.consent_max_days', 90)),
            'frequency_per_day'   => (int) config('openbanking.frequency_per_day', 4),
            'recurring_indicator' => false,
        ]);
    }

    /**
     * Authorize a pending consent (user approves).
     */
    public function authorizeConsent(string $consentId, int $userId): Consent
    {
        $consent = Consent::where('id', $consentId)
            ->where('user_id', $userId)
            ->where('status', ConsentStatus::AWAITING_AUTHORIZATION)
            ->firstOrFail();

        $consent->update([
            'status'        => ConsentStatus::AUTHORIZED,
            'authorized_at' => Carbon::now(),
        ]);

        return $consent->refresh();
    }

    /**
     * Reject a pending consent (user denies).
     */
    public function rejectConsent(string $consentId, int $userId): Consent
    {
        $consent = Consent::where('id', $consentId)
            ->where('user_id', $userId)
            ->where('status', ConsentStatus::AWAITING_AUTHORIZATION)
            ->firstOrFail();

        $consent->update(['status' => ConsentStatus::REJECTED]);

        return $consent->refresh();
    }

    /**
     * Revoke an active consent.
     */
    public function revokeConsent(string $consentId, int $userId): Consent
    {
        $consent = Consent::where('id', $consentId)
            ->where('user_id', $userId)
            ->where('status', ConsentStatus::AUTHORIZED)
            ->firstOrFail();

        $consent->update([
            'status'     => ConsentStatus::REVOKED,
            'revoked_at' => Carbon::now(),
        ]);

        return $consent->refresh();
    }

    /**
     * A consent of the given user; another user's consent is "not found".
     */
    public function getConsent(string $consentId, int $userId): ?Consent
    {
        return Consent::query()
            ->whereKey($consentId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @return Collection<int, Consent>
     */
    public function getActiveConsentsForUser(int $userId): Collection
    {
        return Consent::active()->forUser($userId)->get();
    }

    /**
     * Expire stale consents that have passed their expires_at date.
     */
    public function expireStaleConsents(): int
    {
        return Consent::where('status', ConsentStatus::AUTHORIZED)
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => ConsentStatus::EXPIRED]);
    }
}
