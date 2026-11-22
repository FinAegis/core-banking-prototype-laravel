<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardOnboardingUpdated;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardOnboardingSteps;
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

        $before = CardOnboardingSteps::for($cardholder);
        $this->onboarding->refreshKycStatus($cardholder);

        if ($cardholder->kyc_status === 'verified') {
            $this->onboarding->syncDepositAddresses($this->onboarding->openFundingAccount($cardholder));
        }

        $this->announce($cardholder, $before);
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

        $before = CardOnboardingSteps::for($cardholder);
        $this->onboarding->syncDepositAddresses($this->onboarding->openFundingAccount($cardholder));
        $this->announce($cardholder, $before);
    }

    /**
     * One update per webhook, and only when the step the app shows changed.
     */
    private function announce(Cardholder $cardholder, string $before): void
    {
        $after = CardOnboardingSteps::for($cardholder);
        if ($after !== $before) {
            CardOnboardingUpdated::dispatch((int) $cardholder->user_id, 'lepo', $after);
        }
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
