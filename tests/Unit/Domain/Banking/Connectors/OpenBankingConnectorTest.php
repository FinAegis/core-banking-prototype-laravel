<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Connectors;

use App\Domain\Banking\Connectors\OpenBankingConnector;
use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankOperationException;
use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankBalance;
use App\Domain\Banking\Models\BankTransaction;
use App\Domain\Banking\Models\BankTransfer;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenBankingConnectorTest extends TestCase
{
    private OpenBankingConnector $connector;

    /** @var array<string, string> */
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->config = [
            'bank_code'      => 'OB_TEST',
            'bank_name'      => 'Test Open Bank',
            'base_url'       => 'https://api.openbanking.test/v1',
            'client_id'      => 'test-client-id',
            'client_secret'  => 'test-client-secret',
            'token_url'      => 'https://api.openbanking.test/v1/oauth2/token',
            'webhook_secret' => 'webhook-secret-key',
        ];

        $this->connector = new OpenBankingConnector($this->config);
    }

    public function test_get_bank_code_returns_configured_code(): void
    {
        $this->assertEquals('OB_TEST', $this->connector->getBankCode());
    }

    public function test_get_bank_name_returns_configured_name(): void
    {
        $this->assertEquals('Test Open Bank', $this->connector->getBankName());
    }

    public function test_authenticate_obtains_access_token(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-access-token-123',
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ], 200),
        ]);

        $this->connector->authenticate();

        // Verify the token endpoint was called with correct params
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openbanking.test/v1/oauth2/token'
                && str_contains($request->body(), 'grant_type=client_credentials')
                && str_contains($request->body(), 'client_id=test-client-id');
        });
    }

    public function test_authenticate_throws_on_failure(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'error'             => 'invalid_client',
                'error_description' => 'Unknown client',
            ], 401),
        ]);

        $this->expectException(BankAuthenticationException::class);
        $this->expectExceptionMessage('Open Banking authentication failed');

        $this->connector->authenticate();
    }

    public function test_authenticate_uses_cached_token(): void
    {
        Cache::put('ob_token:OB_TEST', [
            'access_token' => 'cached-token-xyz',
            'expires_in'   => 3600,
        ], 3600);

        Http::fake();

        $this->connector->authenticate();

        // No HTTP calls should be made when token is cached
        Http::assertNothingSent();
    }

    public function test_get_accounts_returns_collection_of_bank_accounts(): void
    {
        $this->fakeAuthAndRequest();

        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/accounts' => Http::response([
                'accounts' => [
                    [
                        'resourceId'      => 'acc-001',
                        'iban'            => 'DE89370400440532013000',
                        'bban'            => '370400440532013000',
                        'bic'             => 'COBADEFFXXX',
                        'currency'        => 'EUR',
                        'cashAccountType' => 'CACC',
                        'status'          => 'active',
                        'ownerName'       => 'Max Mustermann',
                        'product'         => 'Current Account',
                    ],
                    [
                        'resourceId'      => 'acc-002',
                        'iban'            => 'DE02370400440532013001',
                        'bban'            => '370400440532013001',
                        'bic'             => 'COBADEFFXXX',
                        'currency'        => 'EUR',
                        'cashAccountType' => 'SVGS',
                        'status'          => 'active',
                        'ownerName'       => 'Max Mustermann',
                        'product'         => 'Savings Account',
                    ],
                ],
            ], 200),
        ]);

        $accounts = $this->connector->getAccounts();

        $this->assertInstanceOf(Collection::class, $accounts);
        $this->assertCount(2, $accounts);

        $first = $accounts->first();
        $this->assertInstanceOf(BankAccount::class, $first);
        $this->assertEquals('acc-001', $first->id);
        $this->assertEquals('DE89370400440532013000', $first->iban);
        $this->assertEquals('COBADEFFXXX', $first->swift);
        $this->assertEquals('EUR', $first->currency);
        $this->assertEquals('Max Mustermann', $first->holderName);
    }

    public function test_get_account_returns_single_account(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/accounts/acc-001' => Http::response([
                'account' => [
                    'resourceId'      => 'acc-001',
                    'iban'            => 'DE89370400440532013000',
                    'bban'            => '370400440532013000',
                    'bic'             => 'COBADEFFXXX',
                    'currency'        => 'EUR',
                    'cashAccountType' => 'CACC',
                    'status'          => 'active',
                    'ownerName'       => 'Max Mustermann',
                ],
            ], 200),
        ]);

        $account = $this->connector->getAccount('acc-001');

        $this->assertInstanceOf(BankAccount::class, $account);
        $this->assertEquals('acc-001', $account->id);
        $this->assertEquals('DE89370400440532013000', $account->iban);
    }

    public function test_get_balance_returns_balance_for_specific_currency(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/accounts/acc-001/balances' => Http::response([
                'balances' => [
                    [
                        'balanceType'   => 'closingBooked',
                        'balanceAmount' => [
                            'currency' => 'EUR',
                            'amount'   => '15250.50',
                        ],
                        'referenceDate' => '2026-03-17',
                    ],
                ],
            ], 200),
        ]);

        $balance = $this->connector->getBalance('acc-001', 'EUR');

        $this->assertInstanceOf(BankBalance::class, $balance);
        $this->assertEquals('EUR', $balance->currency);
        $this->assertEquals(15250.50, $balance->available);
        $this->assertEquals(15250.50, $balance->current);
    }

    public function test_get_balance_returns_collection_when_no_currency(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/accounts/acc-001/balances' => Http::response([
                'balances' => [
                    [
                        'balanceType'   => 'closingBooked',
                        'balanceAmount' => ['currency' => 'EUR', 'amount' => '1000.00'],
                    ],
                    [
                        'balanceType'   => 'expected',
                        'balanceAmount' => ['currency' => 'GBP', 'amount' => '500.00'],
                    ],
                ],
            ], 200),
        ]);

        $balances = $this->connector->getBalance('acc-001');

        $this->assertInstanceOf(Collection::class, $balances);
        $this->assertCount(2, $balances);
    }

    public function test_get_transactions_returns_transaction_collection(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/accounts/acc-001/transactions*' => Http::response([
                'transactions' => [
                    'booked' => [
                        [
                            'transactionId'                     => 'tx-001',
                            'transactionAmount'                 => ['currency' => 'EUR', 'amount' => '-50.00'],
                            'bookingDate'                       => '2026-03-15',
                            'valueDate'                         => '2026-03-15',
                            'creditorName'                      => 'Amazon EU',
                            'remittanceInformationUnstructured' => 'Order #12345',
                            'balanceAfterTransaction'           => [
                                'balanceAmount' => ['amount' => '14950.00'],
                            ],
                        ],
                        [
                            'transactionId'                     => 'tx-002',
                            'transactionAmount'                 => ['currency' => 'EUR', 'amount' => '3000.00'],
                            'bookingDate'                       => '2026-03-16',
                            'valueDate'                         => '2026-03-16',
                            'debtorName'                        => 'Employer GmbH',
                            'remittanceInformationUnstructured' => 'Salary March',
                            'balanceAfterTransaction'           => [
                                'balanceAmount' => ['amount' => '17950.00'],
                            ],
                        ],
                    ],
                    'pending' => [
                        [
                            'transactionAmount'                 => ['currency' => 'EUR', 'amount' => '-10.00'],
                            'bookingDate'                       => '2026-03-17',
                            'valueDate'                         => '2026-03-17',
                            'creditorName'                      => 'Netflix',
                            'remittanceInformationUnstructured' => 'Monthly subscription',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $from = new DateTime('2026-03-15');
        $to = new DateTime('2026-03-17');

        $transactions = $this->connector->getTransactions('acc-001', $from, $to);

        $this->assertInstanceOf(Collection::class, $transactions);
        $this->assertCount(3, $transactions);

        $first = $transactions->first();
        $this->assertInstanceOf(BankTransaction::class, $first);
        $this->assertEquals('tx-001', $first->id);
        $this->assertEquals('debit', $first->type);
        $this->assertEquals(-50.0, $first->amount);
        $this->assertEquals('Amazon EU', $first->counterpartyName);
    }

    public function test_initiate_transfer_creates_sepa_payment(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/payments/sepa-credit-transfers' => Http::response([
                'paymentId'         => 'pmt-001',
                'transactionStatus' => 'RCVD',
                '_links'            => [
                    'scaRedirect' => [
                        'href' => 'https://bank.example.com/sca?id=pmt-001',
                    ],
                ],
            ], 200),
        ]);

        $transfer = $this->connector->initiateTransfer([
            'from_account_id' => 'acc-001',
            'to_account_id'   => 'acc-ext-002',
            'from_iban'       => 'DE89370400440532013000',
            'to_iban'         => 'FR7630006000011234567890189',
            'creditor_name'   => 'Jean Dupont',
            'amount'          => 250.50,
            'currency'        => 'EUR',
            'description'     => 'Invoice payment',
            'reference'       => 'INV-2026-001',
        ]);

        $this->assertInstanceOf(BankTransfer::class, $transfer);
        $this->assertEquals('pmt-001', $transfer->id);
        $this->assertEquals('pending', $transfer->status);
        $this->assertEquals(250.50, $transfer->amount);
        $this->assertEquals('EUR', $transfer->currency);
        $this->assertNotNull($transfer->metadata['sca_redirect']);
    }

    public function test_get_transfer_status_maps_psd2_statuses(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/payments/sepa-credit-transfers/pmt-001/status' => Http::response([
                'transactionStatus' => 'ACSC',
            ], 200),
        ]);

        $transfer = $this->connector->getTransferStatus('pmt-001');

        $this->assertInstanceOf(BankTransfer::class, $transfer);
        $this->assertEquals('completed', $transfer->status);
        $this->assertNotNull($transfer->executedAt);
    }

    public function test_create_consent_returns_consent_details(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-abc-123',
                'consentStatus' => 'received',
                '_links'        => [
                    'scaRedirect' => [
                        'href' => 'https://bank.example.com/authorize?consent=consent-abc-123',
                    ],
                ],
            ], 200),
        ]);

        $consent = $this->connector->createConsent(['acc-001']);

        $this->assertEquals('consent-abc-123', $consent['consent_id']);
        $this->assertEquals('received', $consent['status']);
        $this->assertNotNull($consent['redirect_url']);
        $this->assertStringContainsString('consent-abc-123', (string) $consent['redirect_url']);
    }

    public function test_validate_iban_accepts_valid_eu_ibans(): void
    {
        // Valid German IBAN
        $this->assertTrue($this->connector->validateIBAN('DE89 3704 0044 0532 0130 00'));

        // Valid French IBAN
        $this->assertTrue($this->connector->validateIBAN('FR76 3000 6000 0112 3456 7890 189'));
    }

    public function test_validate_iban_rejects_non_eu_ibans(): void
    {
        // Valid US routing number format is not an IBAN
        $this->assertFalse($this->connector->validateIBAN('US123456789'));

        // Too short
        $this->assertFalse($this->connector->validateIBAN('DE12'));
    }

    public function test_get_capabilities_returns_psd2_features(): void
    {
        $capabilities = $this->connector->getCapabilities();

        $this->assertContains('EUR', $capabilities->supportedCurrencies);
        $this->assertContains('GBP', $capabilities->supportedCurrencies);
        $this->assertContains('SEPA', $capabilities->supportedTransferTypes);
        $this->assertContains('SEPA_INSTANT', $capabilities->supportedTransferTypes);
        $this->assertTrue($capabilities->supportsInstantTransfers);
        $this->assertTrue($capabilities->supportsDirectDebits);
        $this->assertContains('psd2', $capabilities->features);
        $this->assertContains('aisp', $capabilities->features);
        $this->assertContains('pisp', $capabilities->features);
    }

    public function test_get_supported_currencies_returns_eu_currencies(): void
    {
        $currencies = $this->connector->getSupportedCurrencies();

        $this->assertContains('EUR', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertContains('SEK', $currencies);
        $this->assertCount(8, $currencies);
    }

    public function test_create_account_throws_unsupported(): void
    {
        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('Account creation is not supported via Open Banking PSD2 APIs');

        $this->connector->createAccount([]);
    }

    public function test_verify_webhook_signature_validates_correctly(): void
    {
        $payload = '{"event":"payment.completed","paymentId":"pmt-001"}';
        $validSignature = hash_hmac('sha256', $payload, 'webhook-secret-key');

        $this->assertTrue(
            $this->connector->verifyWebhookSignature($payload, $validSignature, [])
        );

        $this->assertFalse(
            $this->connector->verifyWebhookSignature($payload, 'invalid-signature', [])
        );
    }

    public function test_get_transfer_limits_returns_correct_limits(): void
    {
        $sepaLimits = $this->connector->getTransferLimits('acc-001', 'SEPA');
        $this->assertEquals(1, $sepaLimits['min']);
        $this->assertEquals(15000000, $sepaLimits['max']);

        $instantLimits = $this->connector->getTransferLimits('acc-001', 'SEPA_INSTANT');
        $this->assertEquals(10000000, $instantLimits['max']);
    }

    /**
     * Helper to set up auth faking.
     */
    private function fakeAuthAndRequest(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
        ]);
    }
}
