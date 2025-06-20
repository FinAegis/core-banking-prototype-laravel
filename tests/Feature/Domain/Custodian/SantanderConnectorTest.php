<?php

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Connectors\SantanderConnector;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock successful OAuth response
    Http::fake([
        'https://auth.santander.com/oauth/token' => Http::response([
            'access_token' => 'mock-santander-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
    ]);
});

it('throws exception when API credentials are missing', function () {
    expect(fn() => new SantanderConnector([]))
        ->toThrow(\InvalidArgumentException::class, 'Santander api_key and api_secret are required');
});

it('can be instantiated with valid config', function () {
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    expect($connector)->toBeInstanceOf(SantanderConnector::class);
    expect($connector->getName())->toBe('Santander');
});

it('checks availability via health endpoint', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/health' => Http::response(['status' => 'UP'], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    expect($connector->isAvailable())->toBeTrue();
});

it('returns false when health check fails', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/health' => Http::response(['status' => 'DOWN'], 503),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    expect($connector->isAvailable())->toBeFalse();
});

it('obtains access token with client credentials', function () {
    Http::fake([
        'https://auth.santander.com/oauth/token' => Http::response([
            'access_token' => 'test-santander-token-xyz',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/ACC789/balances' => Http::response([
            'Data' => [
                'Balance' => [
                    ['Currency' => 'GBP', 'Amount' => ['Amount' => '2500.75'], 'Type' => 'InterimAvailable'],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    // This should trigger token acquisition
    $balance = $connector->getBalance('ACC789', 'GBP');
    
    expect($balance)->toBeInstanceOf(Money::class);
    expect($balance->getAmount())->toBe(250075); // £2500.75 in pence
    
    // Verify OAuth request was made
    Http::assertSent(function ($request) {
        return $request->url() === 'https://auth.santander.com/oauth/token' &&
               $request['grant_type'] === 'client_credentials' &&
               $request['client_id'] === 'test-api-key' &&
               $request['client_secret'] === 'test-api-secret';
    });
});

it('retrieves account balance', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456/balances' => Http::response([
            'Data' => [
                'Balance' => [
                    ['Currency' => 'EUR', 'Amount' => ['Amount' => '75000.00'], 'Type' => 'InterimAvailable'],
                    ['Currency' => 'GBP', 'Amount' => ['Amount' => '50000.00'], 'Type' => 'InterimAvailable'],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $balance = $connector->getBalance('40404000123456', 'EUR');
    
    expect($balance)->toBeInstanceOf(Money::class);
    expect($balance->getAmount())->toBe(7500000); // €75,000 in cents
});

it('returns zero balance for non-existent currency', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456/balances' => Http::response([
            'Data' => [
                'Balance' => [
                    ['Currency' => 'EUR', 'Amount' => ['Amount' => '1000.00'], 'Type' => 'InterimAvailable'],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $balance = $connector->getBalance('40404000123456', 'USD');
    
    expect($balance->getAmount())->toBe(0);
});

it('retrieves account information', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456' => Http::response([
            'Data' => [
                'Account' => [[
                    'AccountId' => '40404000123456',
                    'Status' => 'Enabled',
                    'Currency' => 'EUR',
                    'AccountType' => 'Personal',
                    'AccountSubType' => 'CurrentAccount',
                    'Nickname' => 'Main EUR Account',
                    'OpeningDate' => '2021-03-15',
                    'Identification' => '40404000123456',
                    'SchemeName' => 'UK.OBIE.SortCodeAccountNumber',
                ]],
            ],
        ], 200),
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456/balances' => Http::response([
            'Data' => [
                'Balance' => [
                    ['Currency' => 'EUR', 'Amount' => ['Amount' => '125000.00'], 'Type' => 'InterimAvailable'],
                    ['Currency' => 'GBP', 'Amount' => ['Amount' => '85000.00'], 'Type' => 'InterimAvailable'],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $accountInfo = $connector->getAccountInfo('40404000123456');
    
    expect($accountInfo->accountId)->toBe('40404000123456');
    expect($accountInfo->name)->toBe('Main EUR Account');
    expect($accountInfo->status)->toBe('active');
    expect($accountInfo->type)->toBe('CurrentAccount');
    expect($accountInfo->balances)->toBe([
        'EUR' => 12500000, // €125,000 in cents
        'GBP' => 8500000,  // £85,000 in pence
    ]);
    expect($accountInfo->metadata['account_number'])->toBe('40404000123456');
});

it('initiates a domestic payment', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/pisp/domestic-payment-consents' => Http::response([
            'Data' => [
                'ConsentId' => 'CONSENT-123',
                'Status' => 'AwaitingAuthorisation',
            ],
        ], 200),
        'https://api.santander.com/open-banking/v3.1/pisp/domestic-payments' => Http::response([
            'Data' => [
                'DomesticPaymentId' => 'SAN-PAY-456789',
                'ConsentId' => 'CONSENT-123',
                'Status' => 'AcceptedSettlementInProcess',
                'CreationDateTime' => '2023-06-17T10:00:00Z',
                'Initiation' => [
                    'EndToEndIdentification' => 'REF789',
                    'InstructedAmount' => [
                        'Amount' => '2000.00',
                        'Currency' => 'EUR',
                    ],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $transferRequest = new TransferRequest(
        fromAccount: 'ES9121000418450200051332',
        toAccount: 'ES9121000418450200051333',
        amount: new Money(200000), // €2,000
        assetCode: 'EUR',
        reference: 'REF789',
        description: 'Test Santander payment'
    );
    
    $receipt = $connector->initiateTransfer($transferRequest);
    
    expect($receipt->id)->toBe('SAN-PAY-456789');
    expect($receipt->status)->toBe('pending');
    expect($receipt->amount)->toBe(200000);
    expect($receipt->isPending())->toBeTrue();
    expect($receipt->metadata['consent_id'])->toBe('CONSENT-123');
});

it('retrieves transaction status', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/pisp/domestic-payments/SAN-PAY-456789' => Http::response([
            'Data' => [
                'DomesticPaymentId' => 'SAN-PAY-456789',
                'Status' => 'AcceptedSettlementCompleted',
                'CreationDateTime' => '2023-06-17T10:00:00Z',
                'StatusUpdateDateTime' => '2023-06-17T10:05:00Z',
                'Initiation' => [
                    'EndToEndIdentification' => 'REF789',
                    'InstructedAmount' => [
                        'Amount' => '2000.00',
                        'Currency' => 'EUR',
                    ],
                    'DebtorAccount' => [
                        'Identification' => 'ES9121000418450200051332',
                    ],
                    'CreditorAccount' => [
                        'Identification' => 'ES9121000418450200051333',
                    ],
                ],
                'Charges' => [
                    ['Amount' => ['Amount' => '2.50', 'Currency' => 'EUR']],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $receipt = $connector->getTransactionStatus('SAN-PAY-456789');
    
    expect($receipt->status)->toBe('completed');
    expect($receipt->isCompleted())->toBeTrue();
    expect($receipt->completedAt)->not->toBeNull();
    expect($receipt->fee)->toBe(250); // €2.50 in cents
});

it('cannot cancel transactions (Open Banking limitation)', function () {
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $result = $connector->cancelTransaction('SAN-PAY-456789');
    
    expect($result)->toBeFalse();
});

it('validates account existence', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456' => Http::response([
            'Data' => [
                'Account' => [[
                    'AccountId' => '40404000123456',
                    'Status' => 'Enabled',
                ]],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    expect($connector->validateAccount('40404000123456'))->toBeTrue();
});

it('retrieves transaction history', function () {
    Http::fake([
        'https://api.santander.com/open-banking/v3.1/aisp/accounts/40404000123456/transactions?*' => Http::response([
            'Data' => [
                'Transaction' => [
                    [
                        'TransactionId' => 'TXN-SAN-001',
                        'Status' => 'Booked',
                        'BookingDateTime' => '2023-06-17T09:00:00Z',
                        'ValueDateTime' => '2023-06-17T09:00:00Z',
                        'Amount' => ['Amount' => '-1000.00', 'Currency' => 'EUR'],
                        'DebitCreditIndicator' => 'Debit',
                        'TransactionReference' => 'REF-001',
                        'CreditorAccount' => ['Identification' => 'ES9121000418450200051999'],
                    ],
                    [
                        'TransactionId' => 'TXN-SAN-002',
                        'Status' => 'Booked',
                        'BookingDateTime' => '2023-06-16T14:30:00Z',
                        'ValueDateTime' => '2023-06-16T14:30:00Z',
                        'Amount' => ['Amount' => '5000.00', 'Currency' => 'EUR'],
                        'DebitCreditIndicator' => 'Credit',
                        'TransactionReference' => 'REF-002',
                        'DebtorAccount' => ['Identification' => 'ES9121000418450200051888'],
                        'ChargeAmount' => ['Amount' => '5.00', 'Currency' => 'EUR'],
                    ],
                ],
            ],
        ], 200),
    ]);
    
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $history = $connector->getTransactionHistory('40404000123456', 10, 0);
    
    expect($history)->toHaveCount(2);
    expect($history[0]['id'])->toBe('TXN-SAN-001');
    expect($history[0]['amount'])->toBe(100000); // €1,000 in cents
    expect($history[1]['id'])->toBe('TXN-SAN-002');
    expect($history[1]['amount'])->toBe(500000); // €5,000 in cents
    expect($history[1]['fee'])->toBe(500); // €5.00 in cents
});

it('returns supported assets', function () {
    $connector = new SantanderConnector([
        'api_key' => 'test-api-key',
        'api_secret' => 'test-api-secret',
    ]);
    
    $assets = $connector->getSupportedAssets();
    
    expect($assets)->toContain('EUR');
    expect($assets)->toContain('GBP');
    expect($assets)->toContain('USD');
    expect($assets)->toContain('BRL'); // Santander has strong presence in Brazil
    expect($assets)->not->toContain('BTC'); // Santander is fiat-only
});