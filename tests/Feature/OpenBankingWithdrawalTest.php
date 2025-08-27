<?php

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Services\BankIntegrationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class OpenBankingWithdrawalTest extends DomainTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected Asset $usdAsset;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and account first (without firstOrCreate)
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        // Create asset if it doesn't exist
        $this->usdAsset = Asset::updateOrCreate(
            ['code' => 'USD'],
            [
                'name'      => 'US Dollar',
                'symbol'    => '$',
                'type'      => 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );

        // Add balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 500000, // $5,000
        ]);
    }

    #[Test]
    public function user_can_view_openbanking_withdrawal_page()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('wallet.withdraw.openbanking'));

        $response->assertStatus(200);
        $response->assertViewIs('wallet.withdraw-openbanking');
        $response->assertViewHas('account');
        $response->assertViewHas('balances');
        $response->assertViewHas('availableBanks');
    }

    #[Test]
    public function user_can_initiate_openbanking_withdrawal()
    {
        $this->actingAs($this->user);

        // Mock bank connector
        $mockConnector = Mockery::mock(IBankConnector::class);
        $mockConnector->shouldReceive('getAuthorizationUrl')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn('https://bank.example.com/oauth/authorize?client_id=test&redirect_uri=...');

        // Mock bank integration service
        $mockBankService = Mockery::mock(BankIntegrationService::class);
        $mockBankService->shouldReceive('getConnector')
            ->with('paysera')
            ->andReturn($mockConnector);

        $this->app->instance(BankIntegrationService::class, $mockBankService);

        $response = $this->post(route('wallet.withdraw.openbanking.initiate'), [
            'bank_code' => 'paysera',
            'amount'    => 100.00,
            'currency'  => 'USD',
        ]);

        $response->assertRedirect();
        $response->assertRedirectContains('bank.example.com/oauth/authorize');

        // Check session has withdrawal details
        $this->assertEquals([
            'amount'       => 10000,
            'currency'     => 'USD',
            'bank_code'    => 'paysera',
            'account_uuid' => $this->account->uuid,
        ], Session::get('openbanking_withdrawal'));
    }

    #[Test]
    public function user_cannot_withdraw_more_than_balance()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('wallet.withdraw.openbanking.initiate'), [
            'bank_code' => 'paysera',
            'amount'    => 10000.00, // More than balance
            'currency'  => 'USD',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Insufficient balance.');
    }

    #[Test]
    public function withdrawal_requires_minimum_amount()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('wallet.withdraw.openbanking.initiate'), [
            'bank_code' => 'paysera',
            'amount'    => 5.00, // Less than minimum
            'currency'  => 'USD',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    #[Test]
    public function user_can_view_withdrawal_options_page()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('wallet.withdraw'));

        $response->assertStatus(200);
        $response->assertViewIs('wallet.withdraw-options');
        $response->assertSee('OpenBanking Withdrawal');
        $response->assertSee('Traditional Bank Transfer');
    }

    #[Test]
    public function callback_handles_successful_authorization()
    {
        $this->actingAs($this->user);

        // Set the CSRF token in session first
        $csrfToken = 'test-csrf-token';
        Session::put('_token', $csrfToken);

        // Set up session
        Session::put('openbanking_withdrawal', [
            'amount'       => 10000,
            'currency'     => 'USD',
            'bank_code'    => 'paysera',
            'account_uuid' => $this->account->uuid,
        ]);

        // Mock bank connector
        $mockBankAccount = (object) [
            'id'            => 'bank-account-123',
            'accountNumber' => '1234567890',
            'iban'          => 'DE89370400440532013000',
            'swift'         => 'DEUTDEFF',
            'holderName'    => 'Test User',
        ];

        $mockConnector = Mockery::mock(IBankConnector::class);
        $mockConnector->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->andReturn(['access_token' => 'test-token']);
        $mockConnector->shouldReceive('getBankName')
            ->andReturn('Paysera Bank');
        $mockConnector->shouldReceive('initiatePayment')
            ->once()
            ->andReturn(['status' => 'initiated', 'reference' => 'WTH-123']);

        // Mock bank integration service
        $mockBankService = Mockery::mock(BankIntegrationService::class);
        $mockBankService->shouldReceive('getConnector')
            ->with('paysera')
            ->andReturn($mockConnector);
        $mockBankService->shouldReceive('getUserBankConnections')
            ->andReturn(collect());
        $mockBankService->shouldReceive('connectUserToBank')
            ->once();
        $mockBankService->shouldReceive('getUserBankAccounts')
            ->andReturn(collect([$mockBankAccount]));

        $this->app->instance(BankIntegrationService::class, $mockBankService);

        // Mock payment gateway service
        $mockPaymentGateway = Mockery::mock(\App\Domain\Payment\Services\PaymentGatewayService::class);
        $mockPaymentGateway->shouldReceive('createWithdrawalRequest')
            ->once()
            ->andReturn(['reference' => 'WTH-123', 'status' => 'pending']);

        $this->app->instance(\App\Domain\Payment\Services\PaymentGatewayService::class, $mockPaymentGateway);

        $response = $this->get(route('wallet.withdraw.openbanking.callback', [
            'code'  => 'authorization-code',
            'state' => $csrfToken,
        ]));

        // Debug: Check session error
        if (Session::has('error')) {
            dump('Error: ' . Session::get('error'));
        }

        $response->assertRedirect(route('wallet.index'));
        $response->assertSessionHas('success');
        $this->assertNull(Session::get('openbanking_withdrawal'));
    }

    #[Test]
    public function callback_rejects_invalid_state()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('wallet.withdraw.openbanking.callback', [
            'code'  => 'authorization-code',
            'state' => 'invalid-state',
        ]));

        $response->assertRedirect(route('wallet.withdraw.create'));
        $response->assertSessionHas('error', 'Invalid authorization state.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
