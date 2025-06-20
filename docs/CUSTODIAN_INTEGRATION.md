# Custodian Integration Guide

This guide explains how to integrate and use the custodian connectors in the FinAegis platform.

## Overview

The FinAegis platform supports multiple custodian integrations to enable distributed fund management across different financial institutions. This allows for risk distribution, regulatory compliance, and enhanced user choice.

## Supported Custodians

### 1. Paysera
- **Status**: ✅ Implemented
- **Type**: Electronic Money Institution (EMI)
- **Region**: EU/EEA
- **Supported Currencies**: EUR, USD, GBP, CHF, PLN, DKK, NOK, SEK, CZK, HUF, RON, BGN
- **Features**:
  - OAuth2 authentication
  - Real-time balance queries
  - SEPA transfers
  - Multi-currency accounts
  - Webhook notifications

### 2. Deutsche Bank
- **Status**: ✅ Implemented  
- **Type**: Traditional Bank
- **Region**: Global (primary: Germany/EU)
- **Supported Currencies**: EUR, USD, GBP, CHF, JPY, CAD, AUD, NZD, SEK, NOK, DKK, PLN, CZK, HUF
- **Features**:
  - OAuth2 authentication
  - SEPA transfers
  - Instant payments (EUR < €15,000)
  - International wire transfers
  - Corporate banking APIs

### 3. Santander
- **Status**: ✅ Implemented
- **Type**: Traditional Bank
- **Region**: Global (EU, UK, LATAM)
- **Supported Currencies**: EUR, GBP, USD, BRL, MXN, CLP, ARS, PLN, CHF
- **Features**:
  - Open Banking UK standard
  - Payment consent flow
  - Multi-region support
  - Certificate-based authentication

### 4. Mock Bank
- **Status**: ✅ Implemented
- **Type**: Test Connector
- **Region**: N/A
- **Features**: Full API simulation for development and testing

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# Custodian Configuration
CUSTODIAN_DEFAULT=mock

# Paysera
PAYSERA_ENABLED=true
PAYSERA_CLIENT_ID=your_paysera_client_id
PAYSERA_CLIENT_SECRET=your_paysera_client_secret
PAYSERA_ENVIRONMENT=production
PAYSERA_WEBHOOK_SECRET=your_webhook_secret

# Deutsche Bank
DEUTSCHE_BANK_ENABLED=true
DEUTSCHE_BANK_CLIENT_ID=your_db_client_id
DEUTSCHE_BANK_CLIENT_SECRET=your_db_client_secret
DEUTSCHE_BANK_ACCOUNT_ID=your_primary_account_id
DEUTSCHE_BANK_ENVIRONMENT=production
DEUTSCHE_BANK_WEBHOOK_SECRET=your_webhook_secret

# Santander
SANTANDER_ENABLED=true
SANTANDER_API_KEY=your_santander_api_key
SANTANDER_API_SECRET=your_santander_api_secret
SANTANDER_CERTIFICATE_PATH=/path/to/certificate.pem
SANTANDER_ENVIRONMENT=production
SANTANDER_WEBHOOK_SECRET=your_webhook_secret
```

### Configuration File

The custodians are configured in `config/custodians.php`. Each custodian has:
- `class`: The connector implementation class
- `enabled`: Whether the custodian is active
- `name`: Display name
- `description`: Brief description
- Additional custodian-specific settings

## Usage

### 1. Getting a Custodian Connector

```php
use App\Domain\Custodian\Services\CustodianRegistry;

$registry = app(CustodianRegistry::class);
$connector = $registry->getConnector('paysera');
```

### 2. Checking Balance

```php
$balance = $connector->getBalance('account-id', 'EUR');
echo "Balance: €" . ($balance->getAmount() / 100);
```

### 3. Getting Account Information

```php
$accountInfo = $connector->getAccountInfo('account-id');
echo "Account Name: " . $accountInfo->name;
echo "Status: " . $accountInfo->status;
echo "Balances: " . json_encode($accountInfo->balances);
```

### 4. Initiating a Transfer

```php
use App\Domain\Custodian\ValueObjects\TransferRequest;
use App\Domain\Account\DataObjects\Money;

$request = new TransferRequest(
    fromAccount: 'source-account-id',
    toAccount: 'destination-account-id',
    amount: new Money(10000), // €100.00
    assetCode: 'EUR',
    reference: 'REF123456',
    description: 'Payment for invoice #123'
);

$receipt = $connector->initiateTransfer($request);
echo "Transfer ID: " . $receipt->id;
echo "Status: " . $receipt->status;
```

### 5. Checking Transfer Status

```php
$receipt = $connector->getTransactionStatus('transfer-id');
if ($receipt->isCompleted()) {
    echo "Transfer completed at: " . $receipt->completedAt;
}
```

## Balance Synchronization

The platform includes automatic balance synchronization to keep internal records in sync with custodian balances.

### Manual Synchronization

```bash
# Sync all accounts
php artisan custodian:sync-balances

# Sync specific account
php artisan custodian:sync-balances --account=uuid-here

# Sync specific custodian
php artisan custodian:sync-balances --custodian=paysera

# Force sync even if recently synchronized
php artisan custodian:sync-balances --force
```

### Automatic Synchronization

Add to your scheduler in `app/Console/Kernel.php`:

```php
$schedule->command('custodian:sync-balances')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

### Custodian Accounts

Each internal account can be linked to multiple custodian accounts:

```php
use App\Models\CustodianAccount;

// Create custodian account mapping
$custodianAccount = CustodianAccount::create([
    'account_uuid' => $account->uuid,
    'custodian_id' => 'paysera',
    'external_account_id' => 'PAYSERA123456',
    'status' => 'active',
    'is_primary' => true,
]);

// Check sync status
if ($custodianAccount->needsSynchronization()) {
    app(BalanceSynchronizationService::class)
        ->synchronizeAccountBalance($custodianAccount);
}
```

## Webhook Integration

Each custodian can send webhooks for real-time updates:

### Webhook Endpoints

- Paysera: `POST /webhooks/custodian/paysera`
- Deutsche Bank: `POST /webhooks/custodian/deutsche_bank`
- Santander: `POST /webhooks/custodian/santander`

### Webhook Processing

Webhooks are automatically verified and processed:

```php
// In routes/api.php
Route::post('/webhooks/custodian/{custodian}', 'CustodianWebhookController@handle')
    ->name('custodian.webhook');
```

### Webhook Events

- `AccountBalanceUpdated`: Balance changes
- `TransactionStatusUpdated`: Transfer status changes
- `AccountStatusChanged`: Account status updates

## Testing

### Using Mock Connector

For development and testing, use the mock connector:

```php
$connector = $registry->getConnector('mock');
// All operations work with simulated responses
```

### Running Tests

```bash
# Run all custodian tests
./vendor/bin/pest tests/Feature/Domain/Custodian/

# Run specific connector tests
./vendor/bin/pest tests/Feature/Domain/Custodian/PayseraConnectorTest.php
./vendor/bin/pest tests/Feature/Domain/Custodian/DeutscheBankConnectorTest.php
./vendor/bin/pest tests/Feature/Domain/Custodian/SantanderConnectorTest.php
```

## Error Handling

All custodian operations should handle potential failures:

```php
try {
    $balance = $connector->getBalance($accountId, 'EUR');
} catch (\Exception $e) {
    Log::error('Failed to get balance', [
        'custodian' => $connector->getName(),
        'account_id' => $accountId,
        'error' => $e->getMessage(),
    ]);
    
    // Handle error appropriately
}
```

## Security Considerations

1. **API Credentials**: Store all credentials in environment variables
2. **Webhook Verification**: Always verify webhook signatures
3. **SSL/TLS**: All API communications use HTTPS
4. **Certificate Pinning**: For Santander, use certificate-based auth
5. **Rate Limiting**: Respect custodian API rate limits
6. **Audit Logging**: All custodian operations are logged

## Adding New Custodians

To add a new custodian:

1. Create connector class extending `BaseCustodianConnector`
2. Implement all required interface methods
3. Add configuration in `config/custodians.php`
4. Create comprehensive tests
5. Document supported features and limitations

Example:

```php
namespace App\Domain\Custodian\Connectors;

class NewBankConnector extends BaseCustodianConnector
{
    public function getBalance(string $accountId, string $assetCode): Money
    {
        // Implementation
    }
    
    // Implement other required methods...
}
```

## Monitoring

Monitor custodian integrations through:

1. **Admin Dashboard**: View connection status and statistics
2. **Logs**: Check `storage/logs/custodian.log`
3. **Metrics**: Track success rates, response times
4. **Alerts**: Configure alerts for failures

## Support

For custodian-specific support:
- **Paysera**: [support@paysera.com](mailto:support@paysera.com)
- **Deutsche Bank**: Corporate API support portal
- **Santander**: Open Banking support team

For platform integration issues, see the main documentation or create an issue on GitHub.