<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Webhooks;

use App\Domain\CardIssuance\Jobs\AdvanceLepoTopUps;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardWebhookHandler;
use App\Domain\CardIssuance\Providers\Lepo\LepoOnboardingWebhookHandler;
use App\Domain\CardIssuance\Providers\Lepo\LepoThreeDsCodes;
use App\Infrastructure\Lepo\LepoRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Routes verified, deduplicated Lepo webhook events to their handlers.
 *
 * Identity-check and funding-account events go to the onboarding handler;
 * card and top-up handlers follow. Every event is logged by id and type only
 * (never its data, which can carry personal data or a 3-D Secure code).
 */
final class LepoWebhookRouter
{
    /** Event types whose payload carries a one-time 3-D Secure code. */
    private const SECURE_CODE_TYPES = ['card.3ds.otp', 'card.3ds_auth_request'];

    /** Events that can mean a deposit or a card load for a user. */
    private const DEPOSIT_TYPES = ['wallet.topped_up', 'card.topup'];

    /** Identity-check events; their payloads only identify the user. */
    private const KYC_TYPES = ['kyc.verified', 'kyc.update', 'user.kyc.updated', 'kyc.resubmission_required', 'kyc.interlace_submission_failed'];

    public function __construct(
        private readonly LepoOnboardingWebhookHandler $onboarding,
        private readonly LepoCardWebhookHandler $cards,
        private readonly LepoThreeDsCodes $threeDs,
    ) {
    }

    /**
     * Lepo reports money moving for a user: advance that user's top-ups now
     * instead of waiting for the next scheduled run.
     *
     * @param  array<string, mixed>  $data
     */
    private function wakeTopUps(array $data): void
    {
        $userId = $data['userId'] ?? null;
        if (is_scalar($userId) && (string) $userId !== '') {
            AdvanceLepoTopUps::dispatch((string) $userId);
        }
    }

    public static function carriesSecureCode(string $type): bool
    {
        return in_array($type, self::SECURE_CODE_TYPES, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function route(string $type, array $data, string $eventId): void
    {
        Log::info('Lepo webhook received', ['event_id' => $eventId, 'type' => $type]);

        match (true) {
            in_array($type, self::KYC_TYPES, true)               => $this->onboarding->handleKyc($data, $eventId, $type),
            $type === 'account.deposit.address'                  => $this->onboarding->handleDepositAddresses($data, $eventId, $type),
            in_array($type, LepoCardWebhookHandler::TYPES, true) => $this->cards->handle($data, $eventId, $type),
            self::carriesSecureCode($type)                       => $this->threeDs->announce($data),
            in_array($type, self::DEPOSIT_TYPES, true)           => $this->wakeTopUps($data),
            default                                              => null,
        };
    }

    /**
     * Prepares a payload for the queue: card secrets are removed from every
     * event type, and one-time codes from 3-D Secure events.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redact(string $type, array $data): array
    {
        /** @var array<string, mixed> $data */
        $data = LepoRedactor::cardSecrets($data);

        if (! in_array($type, self::SECURE_CODE_TYPES, true)) {
            return $data;
        }

        /** @var array<string, mixed> $withoutCodes */
        $withoutCodes = self::withoutCodes($data);

        return $withoutCodes;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function withoutCodes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/otp|code|passcode/i', $key) === 1) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::withoutCodes($value);
            }
        }

        return $data;
    }
}
