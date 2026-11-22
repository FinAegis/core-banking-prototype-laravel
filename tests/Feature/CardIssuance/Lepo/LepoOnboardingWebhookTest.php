<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardOnboardingUpdated;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'           => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'            => 'test-key',
        'cardissuance.providers.lepo_deposit_networks' => 'arbitrum,polygon',
    ]);
    Event::fake([CardOnboardingUpdated::class]);
});

function webhookCardholder(string $status = 'in_review'): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'    => User::factory()->create()->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'kyc_status' => $status, 'issuer_cardholder_id' => '12345', 'verification_data' => ['questionnaire' => []],
    ]);
    $cardholder->save();

    return $cardholder;
}

/**
 * @return array<string, mixed>
 */
function addressesResponse(): array
{
    return ['userId' => 12345, 'addresses' => [['address' => '0xarb', 'currency' => 'USDC', 'network' => 'ARBITRUM', 'isActive' => true]]];
}

it('verifies the cardholder and opens the funding account', function (): void {
    Http::fake([
        'lepo.test/api/v2/users/12345/kyc/detailed-status'      => Http::response(['cardIssuerKycApproved' => true, 'interlace' => ['status' => 'approved', 'approved' => true]]),
        'lepo.test/api/v2/users/create-interlace-account/12345' => Http::response(['success' => true, 'accountId' => 'acc-1']),
        'lepo.test/api/v2/users/12345/crypto-addresses'         => Http::response(addressesResponse()),
    ]);
    $cardholder = webhookCardholder();

    app(LepoWebhookRouter::class)->route('kyc.verified', ['userId' => 12345, 'status' => 'approved'], 'evt_1');

    $account = CardFundingAccount::query()->where('cardholder_id', $cardholder->id)->firstOrFail();
    expect($cardholder->fresh()?->kyc_status)->toBe('verified')
        ->and($account->status)->toBe('active')
        ->and($account->depositAddresses()->pluck('address')->all())->toBe(['0xarb']);
    // One update for the whole move: in review → ready to fund.
    Event::assertDispatchedTimes(CardOnboardingUpdated::class, 1);
    Event::assertDispatched(CardOnboardingUpdated::class, fn (CardOnboardingUpdated $e): bool => $e->step === 'ready' && $e->provider === 'lepo'
        && $e->broadcastAs() === 'cards.onboarding.updated' && $e->broadcastWith() === ['provider' => 'lepo', 'step' => 'ready']);
});

it('marks action required on a resubmission event', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/detailed-status' => Http::response(['cardIssuerKycApproved' => false, 'interlace' => ['status' => 'action_required', 'requiredAction' => 'RESUBMIT']])]);
    $cardholder = webhookCardholder();

    app(LepoWebhookRouter::class)->route('kyc.resubmission_required', ['userId' => 12345], 'evt_2');

    expect($cardholder->fresh()?->kyc_status)->toBe('action_required')
        ->and(CardFundingAccount::query()->count())->toBe(0);
    Event::assertDispatched(CardOnboardingUpdated::class, fn (CardOnboardingUpdated $e): bool => $e->step === 'kyc_action_required');
});

it('does not announce an unchanged status', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/detailed-status' => Http::response(['interlace' => ['status' => 'pending']])]);
    webhookCardholder('in_review');

    app(LepoWebhookRouter::class)->route('user.kyc.updated', ['userId' => 12345, 'status' => 'pending'], 'evt_3');

    Event::assertNotDispatched(CardOnboardingUpdated::class);
});

it('ignores kyc events for unknown users', function (): void {
    Http::fake();

    app(LepoWebhookRouter::class)->route('kyc.verified', ['userId' => 99999, 'status' => 'approved'], 'evt_4');

    Http::assertNothingSent();
    Event::assertNotDispatched(CardOnboardingUpdated::class);
});

it('stores deposit addresses and announces the funding account', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/crypto-addresses' => Http::response(addressesResponse())]);
    $cardholder = webhookCardholder('verified');
    CardFundingAccount::create(['user_id' => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);

    app(LepoWebhookRouter::class)->route('account.deposit.address', ['userId' => 12345, 'status' => 'created'], 'evt_5');

    Event::assertDispatched(CardOnboardingUpdated::class, fn (CardOnboardingUpdated $e): bool => $e->step === 'ready');
});
