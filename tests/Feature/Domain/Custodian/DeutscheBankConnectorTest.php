<?php

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Connectors\DeutscheBankConnector;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock successful OAuth response
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'mock-db-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ], 200),
    ]);
});

it('throws exception when client credentials are missing', function () {
    expect(fn() => new DeutscheBankConnector([]))
        ->toThrow(\InvalidArgumentException::class, 'Deutsche Bank client_id and client_secret are required');
});

it('can be instantiated with valid config', function () {
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'account_id' => 'test-account-id',
    ]);
    
    expect($connector)->toBeInstanceOf(DeutscheBankConnector::class);
    expect($connector->getName())->toBe('Deutsche Bank');
});

it('checks availability via health endpoint', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/status' => Http::response(['status' => 'operational'], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    expect($connector->isAvailable())->toBeTrue();
});

it('returns false when health check fails', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/status' => Http::response(['error' => 'Service unavailable'], 503),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    expect($connector->isAvailable())->toBeFalse();
});

it('obtains access token with client credentials', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-db-token-456',
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ], 200),
        'https://api.db.com/v2/accounts/ACC123/balances' => Http::response([
            'balances' => [
                ['currency' => 'EUR', 'amount' => '1500.50'],
            ],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    // This should trigger token acquisition
    $balance = $connector->getBalance('ACC123', 'EUR');
    
    expect($balance)->toBeInstanceOf(Money::class);
    expect($balance->getAmount())->toBe(150050); // €1500.50 in cents
    
    // Verify OAuth request was made
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.db.com/oauth2/token' &&
               $request['grant_type'] === 'client_credentials' &&
               $request['client_id'] === 'test-client-id' &&
               $request['client_secret'] === 'test-client-secret' &&
               $request['scope'] === 'accounts payments sepa instant_payments';
    });
});

it('retrieves account balance', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000/balances' => Http::response([
            'balances' => [
                ['currency' => 'EUR', 'amount' => '25000.00'],
                ['currency' => 'USD', 'amount' => '15000.00'],
            ],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $balance = $connector->getBalance('DE89370400440532013000', 'USD');
    
    expect($balance)->toBeInstanceOf(Money::class);
    expect($balance->getAmount())->toBe(1500000); // $15,000 in cents
});

it('returns zero balance for non-existent currency', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000/balances' => Http::response([
            'balances' => [
                ['currency' => 'EUR', 'amount' => '5000.00'],
            ],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $balance = $connector->getBalance('DE89370400440532013000', 'GBP');
    
    expect($balance->getAmount())->toBe(0);
});

it('retrieves account information', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000' => Http::response([
            'accountId' => 'DE89370400440532013000',
            'accountName' => 'Corporate Account',
            'status' => 'ACTIVE',
            'currency' => 'EUR',
            'accountType' => 'CURRENT',
            'iban' => 'DE89370400440532013000',
            'bic' => 'DEUTDEFF',
            'branch' => 'Frankfurt',
            'openingDate' => '2020-01-15T00:00:00Z',
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000/balances' => Http::response([
            'balances' => [
                ['currency' => 'EUR', 'amount' => '100000.00'],
                ['currency' => 'USD', 'amount' => '50000.00'],
            ],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $accountInfo = $connector->getAccountInfo('DE89370400440532013000');
    
    expect($accountInfo->accountId)->toBe('DE89370400440532013000');
    expect($accountInfo->name)->toBe('Corporate Account');
    expect($accountInfo->status)->toBe('active');
    expect($accountInfo->type)->toBe('CURRENT');
    expect($accountInfo->balances)->toBe([
        'EUR' => 10000000, // €100,000 in cents
        'USD' => 5000000,  // $50,000 in cents
    ]);
    expect($accountInfo->metadata['iban'])->toBe('DE89370400440532013000');
    expect($accountInfo->metadata['bic'])->toBe('DEUTDEFF');
    expect($accountInfo->metadata['branch'])->toBe('Frankfurt');
});

it('initiates a SEPA transfer', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/payments/sepa' => Http::response([
            'paymentId' => 'DB-PAY-123456',
            'transactionStatus' => 'ACCP',
            'acceptanceDateTime' => '2023-06-17T10:00:00Z',
            'endToEndIdentification' => 'REF123',
            'fees' => ['amount' => '1.50'],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $transferRequest = new TransferRequest(
        fromAccount: 'DE89370400440532013000',
        toAccount: 'DE89370400440532013001',
        amount: new Money(2000000), // €20,000 (above instant payment threshold)
        assetCode: 'EUR',
        reference: 'REF123',
        description: 'Test SEPA payment'
    );
    
    $receipt = $connector->initiateTransfer($transferRequest);
    
    expect($receipt->id)->toBe('DB-PAY-123456');
    expect($receipt->status)->toBe('pending');
    expect($receipt->amount)->toBe(2000000);
    expect($receipt->fee)->toBe(150); // €1.50 in cents
    expect($receipt->isPending())->toBeTrue();
    expect($receipt->metadata['transfer_type'])->toBe('SEPA');
});

it('initiates instant payment for small EUR amounts', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/payments/instant' => Http::response([
            'paymentId' => 'DB-INST-789012',
            'transactionStatus' => 'ACCC',
            'acceptanceDateTime' => '2023-06-17T10:00:00Z',
            'completionDateTime' => '2023-06-17T10:00:05Z',
            'endToEndIdentification' => 'REF456',
            'fees' => ['amount' => '0.50'],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $transferRequest = new TransferRequest(
        fromAccount: 'DE89370400440532013000',
        toAccount: 'DE89370400440532013001',
        amount: new Money(50000), // €500 (below instant payment threshold)
        assetCode: 'EUR',
        reference: 'REF456',
        description: 'Test instant payment'
    );
    
    $receipt = $connector->initiateTransfer($transferRequest);
    
    expect($receipt->status)->toBe('completed');
    expect($receipt->metadata['transfer_type'])->toBe('INSTANT');
});

it('retrieves transaction status', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/payments/DB-PAY-123456' => Http::response([
            'paymentId' => 'DB-PAY-123456',
            'transactionStatus' => 'ACCC',
            'debtorAccount' => ['iban' => 'DE89370400440532013000'],
            'creditorAccount' => ['iban' => 'DE89370400440532013001'],
            'instructedAmount' => ['currency' => 'EUR', 'amount' => '1000.00'],
            'fees' => ['amount' => '1.50'],
            'endToEndIdentification' => 'REF123',
            'acceptanceDateTime' => '2023-06-17T10:00:00Z',
            'completionDateTime' => '2023-06-17T10:05:00Z',
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $receipt = $connector->getTransactionStatus('DB-PAY-123456');
    
    expect($receipt->status)->toBe('completed');
    expect($receipt->isCompleted())->toBeTrue();
    expect($receipt->completedAt)->not->toBeNull();
    expect($receipt->amount)->toBe(100000); // €1,000 in cents
});

it('cancels a transaction', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/payments/DB-PAY-123456' => Http::response([], 204),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $result = $connector->cancelTransaction('DB-PAY-123456');
    
    expect($result)->toBeTrue();
});

it('validates account existence', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000' => Http::response([
            'accountId' => 'DE89370400440532013000',
            'status' => 'ACTIVE',
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    expect($connector->validateAccount('DE89370400440532013000'))->toBeTrue();
});

it('retrieves transaction history', function () {
    Http::fake([
        'https://api.db.com/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.db.com/v2/accounts/DE89370400440532013000/transactions?*' => Http::response([
            'transactions' => [
                'booked' => [
                    [
                        'transactionId' => 'TXN001',
                        'debtorAccount' => ['iban' => 'DE89370400440532013000'],
                        'creditorAccount' => ['iban' => 'DE89370400440532013999'],
                        'transactionAmount' => ['currency' => 'EUR', 'amount' => '-500.00'],
                        'bookingDate' => '2023-06-17',
                        'valueDate' => '2023-06-17',
                        'endToEndId' => 'REF001',
                    ],
                    [
                        'transactionId' => 'TXN002',
                        'debtorAccount' => ['iban' => 'DE89370400440532013888'],
                        'creditorAccount' => ['iban' => 'DE89370400440532013000'],
                        'transactionAmount' => ['currency' => 'EUR', 'amount' => '1500.00'],
                        'bookingDate' => '2023-06-16',
                        'valueDate' => '2023-06-16',
                        'mandateId' => 'REF002',
                    ],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $history = $connector->getTransactionHistory('DE89370400440532013000', 10, 0);
    
    expect($history)->toHaveCount(2);
    expect($history[0]['id'])->toBe('TXN001');
    expect($history[0]['amount'])->toBe(-50000); // €500 in cents (negative for debit)
    expect($history[1]['id'])->toBe('TXN002');
    expect($history[1]['amount'])->toBe(150000); // €1,500 in cents
});

it('returns supported assets', function () {
    $connector = new DeutscheBankConnector([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
    ]);
    
    $assets = $connector->getSupportedAssets();
    
    expect($assets)->toContain('EUR');
    expect($assets)->toContain('USD');
    expect($assets)->toContain('GBP');
    expect($assets)->toContain('CHF');
    expect($assets)->not->toContain('BTC'); // Deutsche Bank is fiat-only
});