<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function idemKey(): string
{
    return (string) Str::uuid();
}

function makeVerifiedCardholder(User $user): void
{
    $c = new Cardholder();
    $c->user_id = (string) $user->id;
    $c->first_name = 'Jane';
    $c->last_name = 'Smith';
    $c->kyc_status = 'verified';
    $c->issuer_cardholder_id = 'h-1';
    $c->save();

    $a = new FinCardAccount();
    $a->user_id = (string) $user->id;
    $a->fincard_account_id = 'acc-1';
    $a->currency = 'USD';
    $a->balance_cents = 50000;
    $a->status = 'active';
    $a->save();
}

function makeFinCardCard(User $user, int $balanceCents = 20000): Card
{
    /** @var Cardholder $ch */
    $ch = Cardholder::query()->where('user_id', $user->id)->first();
    $card = new Card();
    $card->user_id = (string) $user->id;
    $card->cardholder_id = $ch->id;
    $card->issuer_card_token = 'card-1';
    $card->issuer = 'fincard';
    $card->last4 = '4242';
    $card->network = 'visa';
    $card->status = 'active';
    $card->currency = 'USD';
    $card->balance_cents = $balanceCents;
    $card->fincard_account_id = 'acc-1';
    $card->save();

    return $card;
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();

    $this->app->instance(FinCardClient::class, new FinCardClient(
        baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
        tenantId: 't',
        orgId: 'o',
        userId: 'u',
        username: 'x',
        password: 'y',
    ));

    Http::fake([
        'sandbox.finhub.cloud/api/v2.1/admin/*'                             => Http::response(['success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600]]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/openCard'    => Http::response(['success' => true, 'data' => ['cardId' => 'card-new', 'last4' => '1234']]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/info'           => Http::response(['success' => true, 'data' => ['status' => 'Normal', 'last4' => '4242', 'balance' => '200.00']]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/info/sensitive' => Http::response(['success' => true, 'data' => ['pan' => '4242424242424242', 'cvv' => '123', 'expiry' => '05/29']]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/freeze'      => Http::response(['success' => true, 'data' => []]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/deposit'        => Http::response(['success' => true, 'data' => []]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/withdraw'       => Http::response(['success' => true, 'data' => []]),
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

it('refuses to open a card without a verified cardholder (ERR_CARDS_007)', function () {
    $this->withHeader('Idempotency-Key', idemKey())
        ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_CARDS_007');
});

it('opens a card for a verified cardholder', function () {
    makeVerifiedCardholder($this->user);

    $this->withHeader('Idempotency-Key', idemKey())
        ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
        ->assertCreated()
        ->assertJsonPath('data.card_id', 'card-new')
        ->assertJsonPath('data.balance_cents', 20000);

    expect(Card::where('issuer_card_token', 'card-new')->where('user_id', $this->user->id)->exists())->toBeTrue();
});

it('lists and shows the users FinCard cards', function () {
    makeVerifiedCardholder($this->user);
    makeFinCardCard($this->user);

    $this->getJson('/api/v1/cards/fincard')->assertOk()->assertJsonPath('data.0.card_id', 'card-1');
    $this->getJson('/api/v1/cards/fincard/card-1')->assertOk()->assertJsonPath('data.card_id', 'card-1');
});

it('returns ephemeral sensitive details', function () {
    makeVerifiedCardholder($this->user);
    makeFinCardCard($this->user);

    $this->getJson('/api/v1/cards/fincard/card-1/sensitive')
        ->assertOk()
        ->assertJsonPath('data.pan', '4242424242424242')
        ->assertJsonPath('data.cvv', '123');
});

it('freezes a card', function () {
    makeVerifiedCardholder($this->user);
    makeFinCardCard($this->user);

    $this->withHeader('Idempotency-Key', idemKey())
        ->postJson('/api/v1/cards/fincard/card-1/freeze')
        ->assertOk()
        ->assertJsonPath('data.status', 'frozen');
});

it('tops up a card', function () {
    makeVerifiedCardholder($this->user);
    makeFinCardCard($this->user, 20000);

    $this->withHeader('Idempotency-Key', idemKey())
        ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 5000])
        ->assertOk()
        ->assertJsonPath('data.balance_cents', 25000);
});

it('rejects a withdraw exceeding the card balance (ERR_CARDS_017)', function () {
    makeVerifiedCardholder($this->user);
    makeFinCardCard($this->user, 3000);

    $this->withHeader('Idempotency-Key', idemKey())
        ->postJson('/api/v1/cards/fincard/card-1/withdraw', ['amount_cents' => 9000])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_CARDS_017');
});

it('404s an unknown card', function () {
    makeVerifiedCardholder($this->user);

    $this->getJson('/api/v1/cards/fincard/nope')->assertStatus(404)->assertJsonPath('error.code', 'ERR_CARDS_015');
});
