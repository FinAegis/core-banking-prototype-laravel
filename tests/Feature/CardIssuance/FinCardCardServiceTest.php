<?php

declare(strict_types=1);

namespace Tests\Feature\CardIssuance;

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Services\FinCardAccountService;
use App\Domain\CardIssuance\Services\FinCardCardService;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinCardCardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    private function service(): FinCardCardService
    {
        $client = new FinCardClient(
            baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
            tenantId: 't',
            orgId: 'o',
            userId: 'u',
            username: 'x',
            password: 'y',
        );

        return new FinCardCardService($client, new FinCardAccountService($client));
    }

    /**
     * @return array<string, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function loginFake(): array
    {
        return [
            'sandbox.finhub.cloud/api/v2.1/admin/*' => Http::response([
                'success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600],
            ]),
        ];
    }

    private function cardholder(User $user): Cardholder
    {
        $c = new Cardholder();
        $c->user_id = (string) $user->id;
        $c->first_name = 'Jane';
        $c->last_name = 'Smith';
        $c->kyc_status = 'verified';
        $c->issuer_cardholder_id = 'h-1';
        $c->save();

        return $c;
    }

    private function account(User $user): FinCardAccount
    {
        $a = new FinCardAccount();
        $a->user_id = (string) $user->id;
        $a->fincard_account_id = 'acc-1';
        $a->currency = 'USD';
        $a->balance_cents = 50000;
        $a->status = 'active';
        $a->save();

        return $a;
    }

    private function card(User $user, int $balanceCents = 20000): Card
    {
        $card = new Card();
        $card->user_id = (string) $user->id;
        $card->cardholder_id = $this->cardholder($user)->id;
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

    public function test_open_card_sends_major_amount_and_persists_mapping(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/openCard' => Http::response([
                'success' => true, 'data' => ['cardId' => 'card-new', 'last4' => '1234', 'network' => 'visa'],
            ]),
        ]));

        $user = User::factory()->create();
        $cardholder = $this->cardholder($user);
        $this->account($user);

        $card = $this->service()->openCard($user, $cardholder, 111001, 20000, 'ord-open', ['forwarded_for' => '1.2.3.4']);

        $this->assertSame('card-new', $card->issuer_card_token);
        $this->assertSame('fincard', $card->issuer);
        $this->assertSame(20000, $card->balance_cents);
        $this->assertSame('acc-1', $card->fincard_account_id);
        $this->assertDatabaseHas('cards', ['issuer_card_token' => 'card-new', 'user_id' => $user->id]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/card/v2/openCard')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return ($body['amount'] ?? null) === '200.00' && ($body['accountId'] ?? null) === 'acc-1' && ($body['holderId'] ?? null) === 'h-1';
        });
    }

    public function test_topup_credits_card_balance_via_bcmath_and_broadcasts(): void
    {
        Event::fake([CardStateChanged::class]);
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/deposit' => Http::response(['success' => true, 'data' => []]),
        ]));

        $user = User::factory()->create();
        $card = $this->card($user, 20000);

        $updated = $this->service()->topUp($card, 5000, 'ord-topup');

        $this->assertSame(25000, $updated->balance_cents);
        Event::assertDispatched(CardStateChanged::class, fn ($e): bool => $e->balanceCents === 25000);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/card/deposit')
            && (json_decode($request->body(), true)['amount'] ?? null) === '50.00');
    }

    public function test_withdraw_debits_and_floors_at_zero(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/withdraw' => Http::response(['success' => true, 'data' => []]),
        ]));

        $user = User::factory()->create();
        $card = $this->card($user, 3000);

        $updated = $this->service()->withdraw($card, 9000, 'ord-wd');

        $this->assertSame(0, $updated->balance_cents);
    }

    public function test_freeze_and_cancel_update_status(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/freeze' => Http::response(['success' => true, 'data' => []]),
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/cancel'    => Http::response(['success' => true, 'data' => []]),
        ]));

        $user = User::factory()->create();
        $card = $this->card($user);

        $this->assertSame('frozen', $this->service()->freeze($card, 'ord-fz')->status);
        $this->assertSame('cancelled', $this->service()->cancel($card, 'ord-cx')->status);
    }

    public function test_apply_card_webhook_create_activates(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user);
        $card->status = 'pending';
        $card->save();

        $this->service()->applyCardWebhook('create', ['data' => ['cardId' => 'card-1', 'status' => 'Normal']]);

        $this->assertSame('active', Card::query()->where('issuer_card_token', 'card-1')->firstOrFail()->status);
    }

    public function test_apply_card_webhook_freeze_freezes(): void
    {
        $user = User::factory()->create();
        $this->card($user);

        $this->service()->applyCardWebhook('Freeze', ['data' => ['cardId' => 'card-1']]);

        $this->assertSame('frozen', Card::query()->where('issuer_card_token', 'card-1')->firstOrFail()->status);
    }
}
