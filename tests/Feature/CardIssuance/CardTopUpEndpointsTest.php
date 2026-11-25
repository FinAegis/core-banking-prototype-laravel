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
    Http::preventStrayRequests();
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
    // Starting a top-up reads the live quote for its minimum. A quote faked
    // by the test itself comes first and wins.
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate())]);

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

    $response = tuStart($this, $card)->assertCreated()
        ->assertJsonPath('data.card_id', $card->id)
        ->assertJsonPath('data.status', 'awaiting_deposit')
        ->assertJsonPath('data.source', 'app')
        ->assertJsonPath('data.amount', '25.00')
        ->assertJsonPath('data.network', 'arbitrum')
        ->assertJsonPath('data.deposit_address', '0x' . str_repeat('a', 40))
        ->assertJsonPath('data.tx_hash', null);

    expect($response->json('data.expires_at'))->toBeString();
    // Only the quote for the minimum is read; no money moves.
    Http::assertSentCount(1);
    Http::assertSent(fn (Illuminate\Http\Client\Request $r): bool => str_contains($r->url(), '/cards/quantum-topup/estimate'));
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

it('asks a first top-up for enough to keep 5 USD on the card after every fee', function (): void {
    // With a low configured floor the fees decide the minimum. The card must keep
    // 5.00 after the first 1.00 monthly fee, so 6.00 must arrive after the 1.5 % load
    // fee: 6.10 loaded (fee 0.10). Add the 1.00 issuance fee and the 0.05 conversion
    // fee: 7.15 USD, at 0.999 USD per USDC 7.157157..., rounded up to 7.16 USDC.
    config(['cardissuance.providers.lepo_topup_min' => '1.00']);
    $card = tuCard();
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertOk()->assertJsonPath('data.min_amount', '7.16');
});

it('asks a later top-up only for the conversion fee, the load fee and 5 USD', function (): void {
    // 5.08 loaded less 0.08 (1.5 %, rounded up) leaves 5.00; plus the 0.05
    // conversion fee is 5.13 USD, at 0.999 USD per USDC 5.135135..., so 5.14 USDC.
    config(['cardissuance.providers.lepo_topup_min' => '1.00']);
    $card = tuCard('active', '777');
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertOk()->assertJsonPath('data.min_amount', '5.14');
});

it('never offers a minimum below the configured floor', function (): void {
    $card = tuCard();
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/topups/estimate?amount=25.00&network=arbitrum")
        ->assertOk()->assertJsonPath('data.min_amount', '10.00');
});

it('waits at least an hour for a deposit, whatever the configured time', function (): void {
    // A Polygon deposit takes about 25 minutes to complete, then it is converted.
    config(['cardissuance.providers.lepo_topup_ttl_minutes' => 5]);
    $card = tuCard();
    $before = now()->startOfSecond();

    $expires = Illuminate\Support\Carbon::parse((string) tuStart($this, $card)->json('data.expires_at'));

    expect($expires->greaterThanOrEqualTo($before->copy()->addMinutes(60)))->toBeTrue();
});

it('gives a top-up an hour from the moment the user reports the send', function (): void {
    $card = tuCard();
    $id = (string) tuStart($this, $card)->json('data.id');
    CardTopUp::query()->whereKey($id)->update(['expires_at' => now()->addMinutes(2)]);
    $before = now()->startOfSecond();

    $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->patchJson("/api/v1/cards/issued/{$card->id}/topups/{$id}", ['tx_hash' => '0x' . str_repeat('b', 64)])->assertOk();

    expect(Illuminate\Support\Carbon::parse((string) $response->json('data.expires_at'))->greaterThanOrEqualTo($before->copy()->addMinutes(60)))->toBeTrue();
});

it('asks only for native USDC on the networks the top-up offers', function (): void {
    // Bridged USDC.e (Polygon 0x2791...) is not credited by the card partner.
    $native = ['polygon' => [137, '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359'], 'arbitrum' => [42161, '0xaf88d065e77c8cc2239327c5edb3a432268e5831']];
    $offered = App\Domain\CardIssuance\Providers\CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks');

    expect($offered)->toBe(['arbitrum', 'polygon']);
    foreach ($offered as $network) {
        expect(App\Domain\Wallet\Constants\EvmTokens::NETWORK_CHAIN_IDS[$network])->toBe($native[$network][0])
            ->and(App\Domain\Wallet\Constants\EvmTokens::contractFor('USDC', $network))->toBe($native[$network][1]);
    }
    expect(array_map('strtolower', App\Domain\Wallet\Constants\EvmTokens::USDC))->not->toContain('0x2791bca1f2de4661ed88a30c99a7a9449aa84174');

    // The top-up only takes the asset USDC.
    tuStart($this, tuCard(), ['amount' => '25.00', 'network' => 'arbitrum', 'asset' => 'USDC.e'])->assertStatus(422)->assertJsonValidationErrors(['asset']);
});

it('refuses to start a top-up below the same fee-based minimum the estimate shows', function (string $status, ?string $token, string $amount, ?string $minimum): void {
    // The test quote: 0.999 USD per USDC, 0.05 conversion fee, 1.5 % load fee.
    // First top-up minimum 7.16, later top-up minimum 5.14 (see the estimate tests).
    config(['cardissuance.providers.lepo_topup_min' => '1.00']);
    $card = tuCard($status, $token);
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(tuEstimate(topupFeePercent: '0.015'))]);

    $response = tuStart($this, $card, ['amount' => $amount, 'network' => 'arbitrum', 'asset' => 'USDC']);

    if ($minimum === null) {
        $response->assertCreated()->assertJsonPath('data.amount', $amount);

        return;
    }
    $response->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_028')
        ->assertJsonPath('error.min_amount', $minimum)->assertJsonPath('error.max_amount', '1000.00');
    expect(CardTopUp::query()->where('card_id', $card->id)->count())->toBe(0);
})->with([
    'first, 7.15 refused'  => ['pending', null, '7.15', '7.16'],
    'first, 7.16 accepted' => ['pending', null, '7.16', null],
    'later, 5.13 refused'  => ['active', '777', '5.13', '5.14'],
    'later, 5.14 accepted' => ['active', '777', '5.14', null],
]);

it('refuses to start a top-up when the quote cannot be read', function (mixed $quote): void {
    $card = tuCard();
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => $quote]);

    tuStart($this, $card)->assertStatus(502)->assertJsonPath('error.code', 'ERR_CARDS_016');
    expect(CardTopUp::query()->where('card_id', $card->id)->count())->toBe(0);
})->with([
    'provider down' => [fn () => Http::response(['message' => 'unavailable'], 503)],
    'no rate'       => [fn () => Http::response(['success' => true, 'usdcToUsd' => ['fee' => '0.05']])],
]);
