<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Http\Controllers\Api\CardIssuance\FinCardCardController;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'             => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'              => 'test-key',
        'cardissuance.issuers.lepo.card_type_id_virtual' => '145',
        'cardissuance.providers.enabled'                 => 'lepo,fincard',
        'cardissuance.providers.default'                 => 'lepo',
        'cardissuance.providers.lepo_pilot_only'         => true,
        'cardissuance.providers.lepo_deposit_networks'   => 'arbitrum,polygon',
        'cardissuance.providers.lepo_max_cards'          => 1,
    ]);
});

function issuedUser(): User
{
    $user = User::factory()->create();
    config(['cardissuance.providers.lepo_pilot_users' => $user->uuid]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    return $user;
}

function issuedCardholder(User $user, string $status = 'verified', string $provider = 'lepo', bool $fundingReady = true): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'    => $user->id, 'provider' => $provider, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+37060000000',
        'kyc_status' => $status, 'issuer_cardholder_id' => '12345', 'verified_at' => $status === 'verified' ? now() : null,
    ]);
    $cardholder->save();

    if ($status === 'verified') {
        $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => $provider, 'status' => $fundingReady ? 'active' : 'pending']);
        if ($fundingReady) {
            $account->depositAddresses()->create(['provider' => $provider, 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb', 'is_active' => true]);
        }
    }

    return $cardholder;
}

function issuedCard(Cardholder $cardholder, string $status = 'active', ?string $token = '777'): Card
{
    return Card::create([
        'user_id'           => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'issuer' => $cardholder->provider, 'status' => $status,
        'issuer_card_token' => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa', 'currency' => 'USD',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function issuedLepoCard(array $overrides = []): array
{
    return array_merge([
        'id'     => 777, 'userId' => 12345, 'cardNetwork' => 'Visa', 'cardNumber' => '****-****-****-1234', 'expiryDate' => '2029-12-31',
        'status' => 'ACTIVE', 'balance' => ['available' => '25.00', 'pending' => '0', 'frozen' => '0'], 'currency' => 'USD',
    ], $overrides);
}

function idem(): string
{
    return (string) Str::uuid();
}

it('lists only the user\'s own Lepo cards', function (): void {
    $other = User::factory()->create();
    issuedCard(issuedCardholder($other), 'active', '900');
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake();

    $this->getJson('/api/v1/cards/issued')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $card->id);
});

it('creates a pending card once funding is ready', function (): void {
    $user = issuedUser();
    issuedCardholder($user);
    Http::fake();

    $this->withHeader('Idempotency-Key', idem())->postJson('/api/v1/cards/issued')
        ->assertCreated()
        ->assertJsonPath('data.provider', 'lepo')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.last4', null);
    Http::assertNothingSent();
});

it('refuses a card while the funding account is being set up', function (): void {
    $user = issuedUser();
    issuedCardholder($user, 'verified', 'lepo', false);

    $this->withHeader('Idempotency-Key', idem())->postJson('/api/v1/cards/issued')
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
});

it('refuses a card before the identity check', function (): void {
    $user = issuedUser();
    issuedCardholder($user, 'in_review');

    $this->withHeader('Idempotency-Key', idem())->postJson('/api/v1/cards/issued')
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
});

it('refuses a second card', function (): void {
    $user = issuedUser();
    issuedCard(issuedCardholder($user));

    $this->withHeader('Idempotency-Key', idem())->postJson('/api/v1/cards/issued')
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_026');
});

it('shows a card', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(issuedLepoCard())]);

    $response = $this->getJson("/api/v1/cards/issued/{$card->id}")->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id'            => $card->id, 'provider' => 'lepo', 'status' => 'active', 'last4' => '1234', 'network' => 'visa', 'currency' => 'USD',
        'balance_cents' => 2500, 'frozen_by_support' => false,
    ])->and($response->json('data'))->toHaveKeys(['expires_at', 'created_at'])
        ->and($response->json('data'))->not->toHaveKey('frozen');
});

it('still shows a card when Lepo cannot be reached', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 500)]);

    $this->getJson("/api/v1/cards/issued/{$card->id}")->assertOk()->assertJsonPath('data.status', 'active');
});

it('hides another user\'s card', function (): void {
    $card = issuedCard(issuedCardholder(User::factory()->create()));
    issuedUser();

    $this->getJson("/api/v1/cards/issued/{$card->id}")->assertNotFound()->assertJsonPath('error.code', 'ERR_CARDS_015');
});

it('freezes and unfreezes', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    $frozen = false;
    Http::fake(function (Request $r) use (&$frozen) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/freeze')) {
            $frozen = true;
        }
        if (str_ends_with($path, '/enable')) {
            $frozen = false;
        }

        return $r->method() === 'POST' ? Http::response(['success' => true]) : Http::response(issuedLepoCard(['status' => $frozen ? 'FROZEN' : 'ACTIVE']));
    });

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/freeze")
        ->assertOk()->assertJsonPath('data.status', 'frozen')->assertJsonPath('data.frozen_by_support', false);
    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/unfreeze")
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('refuses to unfreeze a staff freeze', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'frozen');
    CardStaffFreeze::place('777', null);
    Http::fake();

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/unfreeze")
        ->assertStatus(403)->assertJsonPath('error.code', 'ERR_CARDS_021');
    $this->getJson('/api/v1/cards/issued')->assertJsonPath('data.0.frozen_by_support', true);
    Http::assertNothingSent();
});

it('refuses to cancel a card with money', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(issuedLepoCard())]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_025');
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
});

it('refuses to cancel a card with a top-up in progress', function (string $status): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    $topup = new App\Domain\CardIssuance\Models\CardTopUp();
    $topup->forceFill(['user_id' => $user->id, 'card_id' => $card->id, 'provider' => 'lepo', 'source' => 'app', 'asset' => 'USDC', 'network' => 'arbitrum',
        'amount'                 => '25.000000', 'deposit_address' => '0xarb', 'status' => $status, 'expires_at' => now()->addHour()])->save();
    Http::fake(['lepo.test/*' => Http::response(issuedLepoCard(['balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']]))]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_031');
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
})->with(['awaiting_deposit', 'loading', 'needs_attention']);

it('refuses to cancel a card that was never opened while money is moving', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);
    $lock = Illuminate\Support\Facades\Cache::lock("cards:lepo:money:{$user->id}", 60);
    $lock->get();
    Http::fake(['lepo.test/*' => Http::response(['cards' => []])]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")->assertStatus(502);
    expect($card->fresh()?->status)->toBe('pending');
    $lock->release();
});

it('refuses to cancel when Lepo is down', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 500)]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertStatus(502)->assertJsonPath('error.code', 'ERR_CARDS_016');
});

it('cancels a card that was never opened without deleting anything at Lepo', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);
    Http::fake(['lepo.test/api/v2/cards*' => Http::response(['cards' => []])]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
    Http::assertNotSent(fn (Request $r): bool => $r->method() === 'DELETE');
});

it('cancels the Lepo card an unfinished open created', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);
    $deleted = false;
    Http::fake(function (Request $r) use (&$deleted, $card) {
        if ($r->method() === 'DELETE') {
            $deleted = true;

            return Http::response(['success' => true]);
        }
        $lepo = issuedLepoCard(['externalCardId' => $card->id, 'balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']]);

        return str_contains((string) parse_url($r->url(), PHP_URL_PATH), '/cards/777') ? Http::response($lepo) : Http::response(['cards' => [$lepo]]);
    });

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect($deleted)->toBeTrue()->and($card->fresh()?->issuer_card_token)->toBe('777');
});

it('refuses to cancel a card that was never opened when Lepo cannot be asked', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 500)]);

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/cancel")
        ->assertStatus(502)->assertJsonPath('error.code', 'ERR_CARDS_016');
    expect($card->fresh()?->status)->toBe('pending');
});

it('refuses to unfreeze a card that is not frozen', function (string $status, ?string $token): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), $status, $token);
    Http::fake();

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/unfreeze")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
    Http::assertNothingSent();
})->with([
    'active'       => ['active', '777'],
    'never opened' => ['pending', null],
]);

it('refuses to freeze a card that was never opened', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);
    Http::fake();

    $this->withHeader('Idempotency-Key', idem())->postJson("/api/v1/cards/issued/{$card->id}/freeze")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_024');
});

it('pages transactions', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/api/v2/cards/777/transactions*' => Http::response(['transactions' => [['id' => 't1', 'amount' => '-5.00', 'currency' => 'USD']], 'hasMorePages' => true, 'total' => 11])]);

    $this->getJson("/api/v1/cards/issued/{$card->id}/transactions?page=1&limit=5")
        ->assertOk()
        ->assertJsonPath('data.items.0.amount', '5.00')
        ->assertJsonPath('data.items.0.direction', 'debit')
        ->assertJsonPath('data.has_more', true)
        ->assertJsonPath('data.page', 1)
        ->assertJsonPath('data.total', 11);
    // Our pages and Lepo's both count from 0.
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'page=1') && str_contains($r->url(), 'limit=5'));
});

it('keeps FinCard users on FinCard', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    issuedCard(issuedCardholder($user, 'verified', 'fincard'), 'active', 'fc-1');
    Http::fake();

    // FinCard's own response shape: its card id, no Lepo fields.
    $this->getJson('/api/v1/cards/issued')->assertOk()->assertJsonPath('data.0.card_id', 'fc-1')->assertJsonMissingPath('data.0.frozen_by_support');
    $this->getJson('/api/v1/cards/issued/fc-1')->assertOk()->assertJsonPath('data.card_id', 'fc-1');
    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'lepo.test'));
});

it('never hands a Lepo user to FinCard', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user));
    Http::fake(['lepo.test/api/v2/cards/777*' => Http::response(issuedLepoCard())]);
    $finCard = Mockery::mock(FinCardCardController::class);
    $finCard->shouldNotReceive('index', 'show', 'transactions', 'freeze', 'unfreeze', 'cancel', 'sensitive', 'open');
    app()->instance(FinCardCardController::class, $finCard);

    $this->getJson('/api/v1/cards/issued')->assertOk()->assertJsonPath('data.0.id', $card->id);
    $this->getJson("/api/v1/cards/issued/{$card->id}")->assertOk()->assertJsonPath('data.id', $card->id);
});

it('adds the card summary once funding is ready', function (): void {
    $user = issuedUser();
    $card = issuedCard(issuedCardholder($user), 'pending', null);

    $this->getJson('/api/v1/cards/onboarding')
        ->assertJsonPath('data.step', 'ready')
        ->assertJsonPath('data.card', ['id' => $card->id, 'status' => 'pending', 'last4' => null]);
});

it('shows a null card summary when funding is ready but there is no card', function (): void {
    $user = issuedUser();
    issuedCardholder($user);

    $data = $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'ready')->json('data');

    expect($data)->toHaveKey('card')->and($data['card'])->toBeNull();
});

it('shows no card summary before verification', function (): void {
    $user = issuedUser();
    issuedCardholder($user, 'in_review');

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.step', 'kyc_review')->assertJsonMissingPath('data.card');
});
