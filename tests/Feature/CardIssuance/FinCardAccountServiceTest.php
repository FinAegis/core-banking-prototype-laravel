<?php

declare(strict_types=1);

namespace Tests\Feature\CardIssuance;

use App\Domain\CardIssuance\Events\Broadcast\FinCardAccountFunded;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Services\FinCardAccountService;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinCardAccountServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    private function client(): FinCardClient
    {
        return new FinCardClient(
            baseUrl: 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual',
            tenantId: 't',
            orgId: 'o',
            userId: 'u',
            username: 'x',
            password: 'y',
        );
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

    private function account(User $user, int $balanceCents = 0, string $accountId = 'acc-1'): FinCardAccount
    {
        $account = new FinCardAccount();
        $account->user_id = (string) $user->id;
        $account->fincard_account_id = $accountId;
        $account->currency = 'USD';
        $account->balance_cents = $balanceCents;
        $account->status = 'active';
        $account->save();

        return $account;
    }

    public function test_get_or_create_account_creates_and_persists(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/create' => Http::response([
                'success' => true, 'data' => ['accountId' => 'acc-new'],
            ]),
        ]));

        $user = User::factory()->create();
        $account = (new FinCardAccountService($this->client()))->getOrCreateAccount($user);

        $this->assertSame('acc-new', $account->fincard_account_id);
        $this->assertSame(0, $account->balance_cents);
        $this->assertDatabaseHas('fincard_accounts', ['fincard_account_id' => 'acc-new', 'user_id' => $user->id]);
    }

    public function test_get_or_create_account_reuses_existing_without_calling_fincard(): void
    {
        Http::fake($this->loginFake());
        $user = User::factory()->create();
        $this->account($user, accountId: 'acc-existing');

        $account = (new FinCardAccountService($this->client()))->getOrCreateAccount($user);

        $this->assertSame('acc-existing', $account->fincard_account_id);
        Http::assertNothingSent();
    }

    public function test_get_or_create_deposit_address_persists_with_chain_and_min(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/wallet/v2/create' => Http::response([
                'success' => true,
                'data'    => ['address' => 'TXyz...abc', 'chain' => 'TRON', 'minDepositAmount' => '5.00', 'confirmations' => 38],
            ]),
        ]));

        $user = User::factory()->create();
        $this->account($user, accountId: 'acc-1');

        $address = (new FinCardAccountService($this->client()))->getOrCreateDepositAddress($user, 'USDT_TRC20');

        $this->assertSame('TXyz...abc', $address->address);
        $this->assertSame('TRON', $address->chain);
        $this->assertSame(500, $address->min_deposit_cents);
        $this->assertSame(38, $address->confirmations);
        $this->assertDatabaseHas('fincard_deposit_addresses', ['user_id' => $user->id, 'coin_key' => 'USDT_TRC20']);
    }

    public function test_deposit_webhook_credits_balance_via_bcmath_and_broadcasts(): void
    {
        Event::fake([FinCardAccountFunded::class]);
        $user = User::factory()->create();
        $this->account($user, balanceCents: 1000, accountId: 'acc-1');

        (new FinCardAccountService($this->client()))->applyFundingWebhook('DEPOSIT', [
            'data' => ['accountId' => 'acc-1', 'amount' => '8.86', 'coinKey' => 'USDT_TRC20'],
        ]);

        $fresh = FinCardAccount::query()->where('fincard_account_id', 'acc-1')->firstOrFail();
        $this->assertSame(1886, $fresh->balance_cents); // 1000 + 886
        Event::assertDispatched(FinCardAccountFunded::class, fn ($e): bool => $e->balanceCents === 1886 && $e->creditedCents === 886);
    }

    public function test_deposit_webhook_prefers_authoritative_balance_when_present(): void
    {
        $user = User::factory()->create();
        $this->account($user, balanceCents: 1000, accountId: 'acc-1');

        (new FinCardAccountService($this->client()))->applyFundingWebhook('DEPOSIT', [
            'data' => ['accountId' => 'acc-1', 'amount' => '8.86', 'balance' => '50.00'],
        ]);

        $fresh = FinCardAccount::query()->where('fincard_account_id', 'acc-1')->firstOrFail();
        $this->assertSame(5000, $fresh->balance_cents); // set to authoritative 50.00
    }

    public function test_withdraw_webhook_debits_and_floors_at_zero(): void
    {
        $user = User::factory()->create();
        $this->account($user, balanceCents: 500, accountId: 'acc-1');

        (new FinCardAccountService($this->client()))->applyFundingWebhook('WITHDRAW', [
            'data' => ['accountId' => 'acc-1', 'amount' => '9.00'],
        ]);

        $fresh = FinCardAccount::query()->where('fincard_account_id', 'acc-1')->firstOrFail();
        $this->assertSame(0, $fresh->balance_cents); // max(0, 500 - 900)
    }

    public function test_funding_webhook_for_unknown_account_is_a_noop(): void
    {
        Event::fake([FinCardAccountFunded::class]);

        (new FinCardAccountService($this->client()))->applyFundingWebhook('DEPOSIT', [
            'data' => ['accountId' => 'nope', 'amount' => '5.00'],
        ]);

        Event::assertNotDispatched(FinCardAccountFunded::class);
    }

    public function test_sync_balance_reconciles_from_fincard(): void
    {
        Http::fake(array_merge($this->loginFake(), [
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/single/query' => Http::response([
                'success' => true, 'data' => ['balance' => '12.34'],
            ]),
        ]));

        $user = User::factory()->create();
        $account = $this->account($user, balanceCents: 100, accountId: 'acc-1');

        $synced = (new FinCardAccountService($this->client()))->syncBalance($account);

        $this->assertSame(1234, $synced->balance_cents);
    }
}
