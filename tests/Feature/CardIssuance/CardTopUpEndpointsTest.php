<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'            => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'             => 'test-key',
        'cardissuance.providers.enabled'                => 'lepo',
        'cardissuance.providers.default'                => 'lepo',
        'cardissuance.providers.lepo_pilot_only'        => false,
        'cardissuance.providers.lepo_deposit_networks'  => 'arbitrum,polygon',
        'cardissuance.providers.lepo_topups_enabled'    => true,
        'cardissuance.providers.lepo_topup_min'         => '10.00',
        'cardissuance.providers.lepo_topup_max'         => '1000.00',
        'cardissuance.providers.lepo_topup_ttl_minutes' => 60,
    ]);
});

function tuCard(string $status = 'pending', ?string $token = null, ?User $user = null): Card
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'verified_at' => now(), 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0x' . str_repeat('a', 40), 'is_active' => true]);

    return Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => $status,
        'issuer_card_token'        => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa']);
}

/**
 * @return array<string, mixed>
 */
function tuEstimate(string $rate = '0.999', string $fee = '0.05', string $topupFee = '0.25', string $topupFeePercent = '0'): array
{
    return ['success' => true, 'usdAmount' => 25, 'topupfee' => $topupFee, 'topupfeepercent' => $topupFeePercent,
        'usdcToUsd'   => ['baseCurrency' => 'USDC', 'quoteCurrency' => 'USD', 'baseAmount' => '25', 'rfqAmount' => '24.975', 'fee' => $fee, 'feeCurrency' => 'USD', 'rate' => $rate, 'quoteId' => 'q1']];
}

/**
 * @param  array<string, string>  $body
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function tuStart(PHPUnit\Framework\TestCase $test, Card $card, array $body = ['amount' => '25.00', 'network' => 'arbitrum', 'asset' => 'USDC']): Illuminate\Testing\TestResponse
{
    assert($test instanceof Tests\TestCase);

    return $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/topups", $body);
}

it('refuses top-ups while the switch is off', function (): void {
    config(['cardissuance.providers.lepo_topups_enabled' => false]);
    $card = tuCard();
    Http::fake();

    tuStart($this, $card)->assertStatus(503)->assertJsonPath('error.code', 'ERR_CARDS_027');
    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")->assertStatus(503);
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.topups_enabled', false);
    Http::assertNothingSent();
});

it('estimates a top-up with the issuance fee for a new card', function (): void {
    $card = tuCard();
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate())]);

    // 25.00 x 0.999 = 24.975; minus 0.05 conversion fee and 0.25 top-up fee = 24.675; minus the 1.00 issuance fee.
    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertOk()
        ->assertJsonPath('data', [
            'amount'             => '25.00', 'asset' => 'USDC', 'network' => 'arbitrum', 'credited_cents' => 2367, 'fee_cents' => 30,
            'issuance_fee_cents' => 100, 'min_amount' => '10.00', 'max_amount' => '1000.00',
        ]);
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.topups_enabled', true);
});

it('counts the percentage top-up fee as a fraction of the loaded dollars', function (): void {
    // Lepo: topupfeepercent is a fraction (0.015 = 1.5 %) of the amount loaded,
    // rounded to the cent, and it is the whole load fee: topupfee is not added.
    $card = tuCard('active', '777');
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    // 25.00 x 0.999 - 0.05 = 24.925 loaded; 1.5 % of it is 0.373875, so 0.38; the card gets 24.545.
    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertJsonPath('data.credited_cents', 2454)
        ->assertJsonPath('data.fee_cents', 43);
});

it('takes the percentage fee from what is loaded after the issuance fee', function (): void {
    $card = tuCard();
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    // 24.925 - 1.00 issuance = 23.925 loaded; 1.5 % is 0.358875, so 0.36; the card gets 23.565.
    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertJsonPath('data.credited_cents', 2356)
        ->assertJsonPath('data.fee_cents', 41)
        ->assertJsonPath('data.issuance_fee_cents', 100);
});

it('ignores a negative percentage top-up fee', function (): void {
    $card = tuCard('active', '777');
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '-2'))]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertJsonPath('data.credited_cents', 2467)
        ->assertJsonPath('data.fee_cents', 30);
});

it('estimates no issuance fee once the card is open', function (): void {
    $card = tuCard('active', '777');
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate())]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertJsonPath('data.issuance_fee_cents', 0)->assertJsonPath('data.credited_cents', 2467);
});

it('starts a top-up with the deposit address', function (): void {
    $card = tuCard();
    Http::fake();

    $response = tuStart($this, $card)->assertCreated()
        ->assertJsonPath('data.card_id', $card->id)
        ->assertJsonPath('data.status', 'awaiting_deposit')
        ->assertJsonPath('data.source', 'app')
        ->assertJsonPath('data.amount', '25.00')
        ->assertJsonPath('data.network', 'arbitrum')
        ->assertJsonPath('data.deposit_address', '0x' . str_repeat('a', 40))
        ->assertJsonPath('data.tx_hash', null);

    expect($response->json('data.expires_at'))->toBeString();
    Http::assertNothingSent();
});

it('refuses an amount outside the limits', function (string $amount): void {
    $card = tuCard();

    tuStart($this, $card, ['amount' => $amount, 'network' => 'arbitrum', 'asset' => 'USDC'])
        ->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_028')
        ->assertJsonPath('error.min_amount', '10.00')->assertJsonPath('error.max_amount', '1000.00');
})->with(['9.99', '1000.01']);

it('rejects an amount that is not a decimal', function (): void {
    tuStart($this, tuCard(), ['amount' => '1e3', 'network' => 'arbitrum', 'asset' => 'USDC'])->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('refuses an unsupported network', function (): void {
    tuStart($this, tuCard(), ['amount' => '25.00', 'network' => 'polygon', 'asset' => 'USDC'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_029');
    tuStart($this, tuCard(), ['amount' => '25.00', 'network' => 'base', 'asset' => 'USDC'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_029');
});

it('refuses a card that cannot be topped up', function (): void {
    tuStart($this, tuCard('cancelled'))->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_030');
});

it('refuses a second open top-up', function (): void {
    $card = tuCard();
    tuStart($this, $card)->assertCreated();

    tuStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_031');
});

it('records the wallet transaction hash once', function (): void {
    $card = tuCard();
    $id = (string) tuStart($this, $card)->json('data.id');
    $hash = '0x' . str_repeat('b', 64);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => $hash])
        ->assertOk()->assertJsonPath('data.tx_hash', $hash);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => '0x' . str_repeat('c', 64)])
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_032');
});

it('accepts the same bundled transaction hash from two users', function (): void {
    $hash = '0x' . str_repeat('e', 64);
    foreach ([1, 2] as $user) {
        $card = tuCard();
        $id = (string) tuStart($this, $card)->json('data.id');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => $hash])
            ->assertOk()->assertJsonPath('data.tx_hash', $hash);
    }
});

it('keeps card codes and secure pages out of Telescope', function (): void {
    expect(config('telescope.ignore_paths'))->toContain('api/v1/cards/issued/*/3ds/code', 'cards/kyc/*', 'cards/details/*', 'api/v1/webhooks/lepo');
});

it('rejects a malformed transaction hash', function (): void {
    $card = tuCard();
    $id = (string) tuStart($this, $card)->json('data.id');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => 'abc'])
        ->assertStatus(422)->assertJsonValidationErrors(['tx_hash']);
});

it('cancels a top-up that has no deposit', function (): void {
    $card = tuCard();
    $id = (string) tuStart($this, $card)->json('data.id');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/topups/{$id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    tuStart($this, $card)->assertCreated();
});

it('refuses to cancel once a hash is recorded', function (): void {
    $card = tuCard();
    $id = (string) tuStart($this, $card)->json('data.id');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => '0x' . str_repeat('b', 64)]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/topups/{$id}/cancel")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_032');
});

it('hides another user\'s top-up', function (): void {
    $other = tuCard();
    $id = (string) tuStart($this, $other)->json('data.id');
    $card = tuCard();

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/{$id}")->assertNotFound()->assertJsonPath('error.code', 'ERR_CARDS_015');
    $this->getJson("/api/v1/cards/issued/{$other->id}/topups/{$id}")->assertNotFound();
});

it('lists the card\'s top-ups newest first', function (): void {
    $card = tuCard();
    $first = (string) tuStart($this, $card)->json('data.id');
    CardTopUp::query()->whereKey($first)->update(['status' => 'cancelled', 'created_at' => now()->subMinute()]);
    $second = (string) tuStart($this, $card)->json('data.id');

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups")->assertOk()
        ->assertJsonPath('data.items.0.id', $second)->assertJsonPath('data.items.1.id', $first);
});
