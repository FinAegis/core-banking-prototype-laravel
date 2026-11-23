# Custodian Integration Guide

## Overview

FinAegis has a framework for connecting to external banks and other custodians. A custodian holds the money outside the platform, while FinAegis keeps the core banking logic. Each custodian is reached through a connector class. Connectors (e.g. Paysera, Santander, Deutsche Bank) are integration adapters for third-party APIs; no partnership or endorsement implied.

The connectors are reference code. FinAegis holds no live connection to any bank. A deployment that wants one needs its own agreement and credentials with that bank. Every bank connector is off by default, and the Zelta production setup enables none of them. The Mock Bank connector is never registered when `APP_ENV=production`.

## Architecture

### Domain Structure

The custodian integration follows Domain-Driven Design and is organized like this:

```
app/Domain/Custodian/
├── Connectors/
│   ├── BaseCustodianConnector.php    # Abstract base class
│   ├── MockBankConnector.php         # Testing connector
│   ├── DemoBankConnector.php         # Demo-mode connector
│   ├── PayseraConnector.php          # Paysera adapter
│   ├── SantanderConnector.php        # Santander adapter
│   ├── DeutscheBankConnector.php     # Deutsche Bank adapter
│   └── FlutterwaveConnector.php      # Flutterwave adapter
├── Contracts/
│   └── ICustodianConnector.php       # Interface definition
├── Services/
│   ├── CustodianRegistry.php         # Connector registration
│   ├── CustodianAccountService.php   # Account management
│   ├── CustodianHealthMonitor.php    # Health checks per connector
│   ├── CircuitBreakerService.php     # Circuit breaker
│   ├── RetryService.php              # Retries with backoff
│   ├── FallbackService.php           # Fallback routing
│   ├── BalanceSynchronizationService.php # Balance sync
│   ├── DailyReconciliationService.php    # Daily reconciliation
│   ├── MultiCustodianTransferService.php # Transfers across custodians
│   ├── WebhookVerificationService.php    # Webhook signatures
│   └── WebhookProcessorService.php       # Webhook processing
└── ValueObjects/
    ├── AccountInfo.php               # Account information
    ├── TransactionReceipt.php        # Transaction details
    └── TransferRequest.php           # Transfer parameters
```

### Key Components

#### 1. **ICustodianConnector Interface**
This interface is the contract that every custodian connector implements:

```php
interface ICustodianConnector
{
    public function getName(): string;
    public function isAvailable(): bool;
    public function getSupportedAssets(): array;
    public function validateAccount(string $accountId): bool;
    public function getAccountInfo(string $accountId): AccountInfo;
    public function getBalance(string $accountId, string $assetCode): Money;
    public function initiateTransfer(TransferRequest $request): TransactionReceipt;
    public function getTransactionStatus(string $transactionId): TransactionReceipt;
    public function cancelTransaction(string $transactionId): bool;
    public function getTransactionHistory(string $accountId, ?int $limit = 100, ?int $offset = 0): array;
}
```

#### 2. **CustodianRegistry**
The registry registers connectors and returns them by name:

```php
$registry = app(CustodianRegistry::class);
$registry->register('paysera', new PayseraConnector($config));
$connector = $registry->get('paysera');
```

#### 3. **CustodianAccountService**
This service manages the links between internal accounts and custodian accounts:

```php
$service = app(CustodianAccountService::class);

// Link internal account to custodian
$custodianAccount = $service->linkAccount(
    $account,
    'paysera',
    'external-account-id',
    ['metadata' => 'value'],
    true // is primary
);

// Get balance from custodian
$balance = $service->getBalance($custodianAccount, 'EUR');

// Initiate transfer
$transactionId = $service->initiateTransfer(
    $fromCustodianAccount,
    $toCustodianAccount,
    new Money(10000), // €100.00
    'EUR',
    'REF-123',
    'Payment description'
);
```

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Default connector
CUSTODIAN_DEFAULT=mock

# Paysera Integration
PAYSERA_ENABLED=true
PAYSERA_CLIENT_ID=your-client-id
PAYSERA_CLIENT_SECRET=your-client-secret
PAYSERA_ENVIRONMENT=sandbox
PAYSERA_WEBHOOK_SECRET=your-webhook-secret

# Mock Bank (local and testing: on unless set to false; demo: off unless set to true;
# production: always off)
CUSTODIAN_MOCK_ENABLED=true

# Santander Integration
SANTANDER_ENABLED=false
SANTANDER_API_KEY=your-api-key
SANTANDER_API_SECRET=your-api-secret
SANTANDER_WEBHOOK_SECRET=your-webhook-secret

# Deutsche Bank Integration
DEUTSCHE_BANK_ENABLED=false
DEUTSCHE_BANK_CLIENT_ID=your-client-id
DEUTSCHE_BANK_CLIENT_SECRET=your-client-secret

# Flutterwave Integration
FLUTTERWAVE_ENABLED=false
FLUTTERWAVE_SECRET_KEY=your-secret-key
```

### Configuration File

The `config/custodians.php` file defines all available connectors. This is a shortened excerpt:

```php
return [
    'default' => env('CUSTODIAN_DEFAULT', 'mock'),
    
    'connectors' => [
        'paysera' => [
            'class' => \App\Domain\Custodian\Connectors\PayseraConnector::class,
            'enabled' => env('PAYSERA_ENABLED', false),
            'name' => 'Paysera',
            'client_id' => env('PAYSERA_CLIENT_ID'),
            'client_secret' => env('PAYSERA_CLIENT_SECRET'),
        ],
        
        'mock' => [
            'class' => \App\Domain\Custodian\Connectors\MockBankConnector::class,
            'enabled' => match (env('APP_ENV', 'production')) {
                'local', 'testing' => (bool) env('CUSTODIAN_MOCK_ENABLED', true),
                'demo'             => (bool) env('CUSTODIAN_MOCK_ENABLED', false),
                default            => false,
            },
            'name' => 'Mock Bank',
        ],
        
        'santander' => [
            'class' => \App\Domain\Custodian\Connectors\SantanderConnector::class,
            'enabled' => env('SANTANDER_ENABLED', false),
            'name' => 'Santander',
            'api_key' => env('SANTANDER_API_KEY'),
            'api_secret' => env('SANTANDER_API_SECRET'),
        ],
    ],
];
```

## Account Mapping

### Database Schema

The `custodian_accounts` table manages the relationship between internal and external accounts:

```sql
CREATE TABLE custodian_accounts (
    id BIGINT PRIMARY KEY,
    uuid UUID UNIQUE,
    account_uuid UUID REFERENCES accounts(uuid),
    custodian_name VARCHAR(255),
    custodian_account_id VARCHAR(255),
    custodian_account_name VARCHAR(255),
    status ENUM('active', 'suspended', 'closed', 'pending'),
    is_primary BOOLEAN DEFAULT FALSE,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

### Account Model Relationship

```php
class Account extends Model
{
    public function custodianAccounts(): HasMany
    {
        return $this->hasMany(CustodianAccount::class, 'account_uuid', 'uuid');
    }
    
    public function primaryCustodianAccount(): ?CustodianAccount
    {
        return $this->custodianAccounts()->where('is_primary', true)->first();
    }
}
```

## Implementation Examples

### Linking Accounts

```php
use App\Domain\Custodian\Services\CustodianAccountService;
use App\Models\Account;

// Get the internal account
$account = Account::find($accountUuid);

// Link to Paysera account
$custodianAccount = app(CustodianAccountService::class)->linkAccount(
    account: $account,
    custodianName: 'paysera',
    custodianAccountId: 'LT123456789012345678',
    metadata: [
        'iban' => 'LT123456789012345678',
        'bic' => 'PAYSLTXX',
    ],
    isPrimary: true
);
```

### Checking Balances

```php
// Get balance for specific asset
$euroBalance = app(CustodianAccountService::class)->getBalance(
    $custodianAccount,
    'EUR'
);

// Get all balances
$allBalances = app(CustodianAccountService::class)->getAllBalances(
    $custodianAccount
);
// Returns: ['EUR' => 150000, 'USD' => 75000]
```

### Initiating Transfers

```php
use App\Domain\Account\DataObjects\Money;

$service = app(CustodianAccountService::class);

// Transfer between accounts at the same custodian
$transactionId = $service->initiateTransfer(
    fromAccount: $fromCustodianAccount,
    toAccount: $toCustodianAccount,
    amount: new Money(50000), // €500.00
    assetCode: 'EUR',
    reference: 'INV-2024-001',
    description: 'Invoice payment'
);

// Check transaction status
$status = $service->getTransactionStatus('paysera', $transactionId);
if ($status['status'] === 'completed') {
    // Transaction successful
}
```

### Transaction History

```php
// Get last 50 transactions
$history = app(CustodianAccountService::class)->getTransactionHistory(
    custodianAccount: $custodianAccount,
    limit: 50,
    offset: 0
);

foreach ($history as $transaction) {
    echo "{$transaction['id']}: {$transaction['amount']} {$transaction['asset_code']}\n";
}
```

## Creating Custom Connectors

To integrate with a new custodian, create a connector class:

```php
namespace App\Domain\Custodian\Connectors;

use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use App\Domain\Account\DataObjects\Money;

class MyBankConnector extends BaseCustodianConnector
{
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        // Initialize API client
        $this->apiKey = $config['api_key'];
    }
    
    public function isAvailable(): bool
    {
        // Check if the custodian API is accessible
        return $this->checkHealth();
    }
    
    public function getSupportedAssets(): array
    {
        return ['USD', 'EUR', 'GBP'];
    }
    
    public function getBalance(string $accountId, string $assetCode): Money
    {
        $response = $this->apiClient->get("/accounts/{$accountId}/balance");
        $balance = $response['balances'][$assetCode] ?? 0;
        
        return new Money($balance);
    }
    
    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $response = $this->apiClient->post('/transfers', [
            'from' => $request->fromAccount,
            'to' => $request->toAccount,
            'amount' => $request->amount->getAmount(),
            'currency' => $request->assetCode,
            'reference' => $request->reference,
        ]);
        
        return new TransactionReceipt(
            id: $response['id'],
            status: $response['status'],
            // ... other fields
        );
    }
    
    // Implement other required methods...
}
```

Register the connector under `connectors` in `config/custodians.php`:

```php
'mybank' => [
    'class' => \App\Domain\Custodian\Connectors\MyBankConnector::class,
    'enabled' => env('MYBANK_ENABLED', false),
    'name' => 'My Bank',
    'api_key' => env('MYBANK_API_KEY'),
],
```

## Security Considerations

1. The Paysera connector authenticates with the OAuth2 client credentials flow.
2. Credentials are kept in environment variables.
3. All API calls use HTTPS.
4. Each transaction is tracked with a unique identifier.
5. Custodian operations are logged, which gives an audit trail.
6. Incoming custodian webhooks are signature-checked. `POST /api/webhooks/custodian/paysera` and `POST /api/webhooks/custodian/santander` use the `webhook.signature` middleware, as described in [WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md).

## Testing

### Unit Tests

```php
test('can link account to custodian', function () {
    $account = Account::factory()->create();
    $service = app(CustodianAccountService::class);
    
    $custodianAccount = $service->linkAccount(
        $account,
        'mock',
        'mock-account-1'
    );
    
    expect($custodianAccount->custodian_name)->toBe('mock');
    expect($custodianAccount->status)->toBe('active');
});
```

### Integration Tests

```php
test('can transfer between custodian accounts', function () {
    Http::fake([
        'https://bank.paysera.com/rest/v1/payments' => Http::response([
            'id' => 'PAY123',
            'status' => 'completed',
        ]),
    ]);
    
    $transfer = app(CustodianAccountService::class)->initiateTransfer(
        $fromAccount,
        $toAccount,
        new Money(10000),
        'EUR',
        'TEST-REF'
    );
    
    expect($transfer)->toBe('PAY123');
});
```

## Monitoring and Maintenance

1. Each connector implements `isAvailable()`, and `CustodianHealthMonitor` uses it for health checks. `GET /api/v2/status` reports the result per connector.
2. `getTransactionStatus()` tracks a transfer.
3. `syncAccountStatus()` refreshes the account status. The scheduled `custodian:sync-balances` command synchronizes balances with the custodians.
4. Every operation throws a descriptive exception when it fails.
5. Circuit breaker, retry and fallback settings live under `CUSTODIAN_CB_*`, `CUSTODIAN_RETRY_*` and `CUSTODIAN_FALLBACK_*`.

## Future Enhancements

Transfers across custodians (`MultiCustodianTransferService`) and custodian webhooks already exist. Possible future work includes automatic currency conversion during transfers, bulk transfers and more connectors, such as Revolut and Wise.