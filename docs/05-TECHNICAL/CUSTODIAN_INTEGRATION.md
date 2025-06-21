# Custodian Integration Guide

## Overview

The FinAegis Core Banking Platform provides a comprehensive custodian integration framework that allows seamless connection with external banking and financial institutions. This enables the platform to leverage existing banking infrastructure while maintaining complete control over the core banking logic.

## Architecture

### Domain Structure

The custodian integration follows Domain-Driven Design principles and is organized as follows:

```
app/Domain/Custodian/
├── Connectors/
│   ├── BaseCustodianConnector.php    # Abstract base class
│   ├── MockBankConnector.php         # Testing connector
│   ├── PayseraConnector.php          # Paysera implementation
│   └── SantanderConnector.php        # Santander implementation
├── Contracts/
│   └── ICustodianConnector.php       # Interface definition
├── Services/
│   ├── CustodianRegistry.php         # Connector registration
│   └── CustodianAccountService.php   # Account management
└── ValueObjects/
    ├── AccountInfo.php               # Account information
    ├── TransactionReceipt.php        # Transaction details
    └── TransferRequest.php           # Transfer parameters
```

### Key Components

#### 1. **ICustodianConnector Interface**
Defines the contract that all custodian connectors must implement:

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
    public function getTransactionHistory(string $accountId, ?int $limit, ?int $offset): array;
}
```

#### 2. **CustodianRegistry**
Manages registration and retrieval of custodian connectors:

```php
$registry = app(CustodianRegistry::class);
$registry->register('paysera', new PayseraConnector($config));
$connector = $registry->get('paysera');
```

#### 3. **CustodianAccountService**
High-level service for managing custodian account relationships:

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
# Paysera Integration
PAYSERA_ENABLED=true
PAYSERA_CLIENT_ID=your-client-id
PAYSERA_CLIENT_SECRET=your-client-secret

# Mock Bank (for testing)
MOCK_BANK_ENABLED=true

# Santander Integration
SANTANDER_ENABLED=false
SANTANDER_API_KEY=your-api-key
SANTANDER_API_SECRET=your-api-secret
```

### Configuration File

The `config/custodians.php` file defines all available custodians:

```php
return [
    'default' => env('DEFAULT_CUSTODIAN', 'mock'),
    
    'custodians' => [
        'paysera' => [
            'class' => \App\Domain\Custodian\Connectors\PayseraConnector::class,
            'enabled' => env('PAYSERA_ENABLED', false),
            'name' => 'Paysera',
            'client_id' => env('PAYSERA_CLIENT_ID'),
            'client_secret' => env('PAYSERA_CLIENT_SECRET'),
        ],
        
        'mock' => [
            'class' => \App\Domain\Custodian\Connectors\MockBankConnector::class,
            'enabled' => env('MOCK_BANK_ENABLED', true),
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

Register the connector in `config/custodians.php`:

```php
'mybank' => [
    'class' => \App\Domain\Custodian\Connectors\MyBankConnector::class,
    'enabled' => env('MYBANK_ENABLED', false),
    'name' => 'My Bank',
    'api_key' => env('MYBANK_API_KEY'),
],
```

## Security Considerations

1. **OAuth2 Authentication**: The Paysera connector uses OAuth2 client credentials flow for secure API access
2. **API Key Storage**: All sensitive credentials are stored in environment variables
3. **SSL/TLS**: All API communications use HTTPS
4. **Transaction Integrity**: Each transaction is tracked with unique identifiers
5. **Audit Trail**: All custodian operations are logged for compliance

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

1. **Health Checks**: Each connector implements `isAvailable()` for monitoring
2. **Transaction Status**: Use `getTransactionStatus()` to track transfers
3. **Account Sync**: Periodically sync account status with `syncAccountStatus()`
4. **Error Handling**: All operations throw descriptive exceptions for debugging

## Future Enhancements

1. **Cross-Custodian Transfers**: Enable transfers between different banks
2. **Webhook Support**: Real-time transaction notifications
3. **Multi-Currency Conversion**: Automatic FX during transfers
4. **Batch Operations**: Bulk transfer capabilities
5. **Additional Connectors**: Revolut, Wise, traditional banks