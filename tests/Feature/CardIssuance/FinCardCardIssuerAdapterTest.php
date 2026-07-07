<?php

declare(strict_types=1);

namespace Tests\Feature\CardIssuance;

use App\Domain\CardIssuance\Adapters\FinCardCardIssuerAdapter;
use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Exceptions\UnsupportedCardOperationException;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinCardCardIssuerAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
            'sandbox.finhub.cloud/api/v2.1/admin/*'                                   => Http::response(['success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600]]),
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/freeze'            => Http::response(['success' => true, 'data' => []]),
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/purchase/transaction' => Http::response([
                'success' => true,
                'data'    => ['records' => [['orderNo' => 'tx-1', 'merchantName' => 'ACME', 'amount' => '19.99', 'currency' => 'USD', 'status' => 'settled']]],
            ]),
        ]);
    }

    private function adapter(): FinCardCardIssuerAdapter
    {
        return $this->app->make(FinCardCardIssuerAdapter::class);
    }

    private function card(User $user, string $network = 'discover', string $status = 'active'): Card
    {
        $ch = new Cardholder();
        $ch->user_id = (string) $user->id;
        $ch->first_name = 'Jane';
        $ch->last_name = 'Smith';
        $ch->kyc_status = 'verified';
        $ch->issuer_cardholder_id = 'h-1';
        $ch->save();

        $card = new Card();
        $card->user_id = (string) $user->id;
        $card->cardholder_id = $ch->id;
        $card->issuer_card_token = 'card-1';
        $card->issuer = 'fincard';
        $card->last4 = '4242';
        $card->network = $network;
        $card->status = $status;
        $card->currency = 'USD';
        $card->save();

        return $card;
    }

    public function test_get_name(): void
    {
        $this->assertSame('fincard', $this->adapter()->getName());
    }

    public function test_create_card_and_provisioning_are_unsupported(): void
    {
        $this->expectException(UnsupportedCardOperationException::class);
        $this->adapter()->createCard('1', 'Jane Smith');
    }

    public function test_get_card_maps_db_card_to_virtual_card_with_network_fallback(): void
    {
        $user = User::factory()->create();
        $this->card($user, network: 'discover', status: 'active');

        $vc = $this->adapter()->getCard('card-1');

        $this->assertNotNull($vc);
        $this->assertSame('card-1', $vc->cardToken);
        $this->assertSame('4242', $vc->last4);
        $this->assertSame(CardNetwork::VISA, $vc->network); // discover -> visa (not in enum)
        $this->assertSame(CardStatus::ACTIVE, $vc->status);
        $this->assertSame('Jane Smith', $vc->cardholderName);
    }

    public function test_get_card_returns_null_for_unknown_token(): void
    {
        $this->assertNull($this->adapter()->getCard('nope'));
    }

    public function test_freeze_delegates_and_returns_bool(): void
    {
        $user = User::factory()->create();
        $this->card($user);

        $this->assertTrue($this->adapter()->freezeCard('card-1'));
        $this->assertSame('frozen', Card::query()->where('issuer_card_token', 'card-1')->firstOrFail()->status);
        $this->assertFalse($this->adapter()->freezeCard('unknown'));
    }

    public function test_list_user_cards(): void
    {
        $user = User::factory()->create();
        $this->card($user);

        $cards = $this->adapter()->listUserCards((string) $user->id);

        $this->assertCount(1, $cards);
        $this->assertSame('card-1', $cards[0]->cardToken);
    }

    public function test_get_transactions_maps_records(): void
    {
        $result = $this->adapter()->getTransactions('card-1', 20);

        $this->assertCount(1, $result['transactions']);
        $this->assertSame('tx-1', $result['transactions'][0]->transactionId);
        $this->assertSame('ACME', $result['transactions'][0]->merchantName);
        $this->assertSame(1999, $result['transactions'][0]->amountCents); // bcmath 19.99 -> 1999
    }

    public function test_provider_resolves_fincard_adapter_when_selected(): void
    {
        config(['cardissuance.default_issuer' => 'fincard']);
        $this->app->forgetInstance(CardIssuerInterface::class);

        $this->assertInstanceOf(FinCardCardIssuerAdapter::class, $this->app->make(CardIssuerInterface::class));
    }
}
