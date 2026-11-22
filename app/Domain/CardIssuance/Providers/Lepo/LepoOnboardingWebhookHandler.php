<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardFundingAccountReady;
use App\Domain\CardIssuance\Events\Broadcast\CardKycStatusChanged;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use Illuminate\Support\Facades\Log;

/**
 * Lepo identity and funding-account webhooks. The payloads only identify the
 * user, so the handler re-reads Lepo's state through LepoCardOnboarding.
 */
final class LepoOnboardingWebhookHandler
{
    public function __construct(private readonly LepoCardOnboarding $onboarding)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handleKyc(array $data, string $eventId, string $type): void
    {
        $cardholder = $this->cardholder($data, $eventId, $type);
        if ($cardholder === null) {
            return;
        }

        $before = $cardholder->kyc_status;
        $this->onboarding->refreshKycStatus($cardholder);

        if ($cardholder->kyc_status !== $before) {
            CardKycStatusChanged::dispatch((int) $cardholder->user_id, 'lepo', (string) $cardholder->kyc_status);
        }

        if ($cardholder->kyc_status === 'verified') {
            $this->syncFunding($this->onboarding->openFundingAccount($cardholder));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handleDepositAddresses(array $data, string $eventId, string $type): void
    {
        $cardholder = $this->cardholder($data, $eventId, $type);
        if ($cardholder === null || $cardholder->kyc_status !== 'verified') {
            return;
        }

        $this->syncFunding($this->onboarding->openFundingAccount($cardholder));
    }

    private function syncFunding(CardFundingAccount $account): void
    {
        if ($this->onboarding->syncDepositAddresses($account) === 0) {
            return;
        }

        /** @var list<string> $networks */
        $networks = $account->depositAddresses()->where('is_active', true)->orderBy('network')->pluck('network')->all();

        CardFundingAccountReady::dispatch((int) $account->user_id, 'lepo', $networks);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cardholder(array $data, string $eventId, string $type): ?Cardholder
    {
        $userId = $data['userId'] ?? null;
        $cardholder = is_scalar($userId) && (string) $userId !== ''
            ? Cardholder::query()->where('provider', 'lepo')->where('issuer_cardholder_id', (string) $userId)->first()
            : null;

        if ($cardholder === null) {
            Log::info('Lepo webhook for an unknown user ignored', ['event_id' => $eventId, 'type' => $type]);
        }

        return $cardholder;
    }
}
