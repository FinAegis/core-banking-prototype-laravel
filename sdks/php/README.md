# FinAegis PHP SDK

Official PHP SDK for the FinAegis API.

## Requirements

- PHP 8.0 or higher
- Composer

## Installation

```bash
composer require finaegis/php-sdk
```

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use FinAegis\Client;

// Initialize the client
$client = new Client('your-api-key', [
    'environment' => 'sandbox'  // or 'production'
]);

// List accounts
$accounts = $client->accounts->list();
foreach ($accounts->data as $account) {
    echo $account->getName() . ": $" . ($account->getBalance() / 100) . "\n";
}

// Create a new account
$account = $client->accounts->create(
    'user-uuid',
    'My Savings Account',
    10000  // Initial balance in cents
);

// Make a transfer
$transfer = $client->transfers->create(
    'from-account-uuid',
    'to-account-uuid',
    5000,  // Amount in cents
    'USD',
    'Payment for services'
);
```

## Configuration

### Basic Configuration

```php
$client = new Client('your-api-key', [
    'environment' => 'production',  // 'production' | 'sandbox' | 'local'
    'timeout' => 30,               // Request timeout in seconds
    'max_retries' => 3,            // Number of retries for failed requests
    'base_url' => null,            // Custom API base URL (optional)
    'logger' => $logger            // PSR-3 compatible logger (optional)
]);
```

### Environment Variables

You can set your API key via environment variable:

```bash
export FINAEGIS_API_KEY='your-api-key'
```

Then initialize without passing the key:

```php
$client = new Client('');  // Will use FINAEGIS_API_KEY env var
```

## Resources

### Accounts

```php
// List all accounts
$accounts = $client->accounts->list($page = 1, $perPage = 20);

// Get account details
$account = $client->accounts->get('account-uuid');

// Create account
$account = $client->accounts->create(
    'user-uuid',
    'Account Name',
    10000,  // Initial balance in cents
    'USD'   // Asset code
);

// Update account
$account = $client->accounts->update('account-uuid', [
    'name' => 'New Account Name'
]);

// Get account balances
$balances = $client->accounts->getBalances('account-uuid');

// Deposit funds
$transaction = $client->accounts->deposit(
    'account-uuid',
    10000,  // Amount in cents
    'USD',
    'Deposit reference'
);

// Withdraw funds
$transaction = $client->accounts->withdraw(
    'account-uuid',
    5000,   // Amount in cents
    'USD',
    'Withdrawal reference'
);

// Freeze/unfreeze account
$client->accounts->freeze('account-uuid', 'Suspicious activity');
$client->accounts->unfreeze('account-uuid', 'Investigation completed');

// Close account
$client->accounts->close('account-uuid', 'Customer request');

// Get transaction history
$transactions = $client->accounts->getTransactions('account-uuid');

// Get transfers
$transfers = $client->accounts->getTransfers('account-uuid');
```

### Transfers

```php
// Create a transfer
$transfer = $client->transfers->create(
    'from-account-uuid',
    'to-account-uuid',
    10000,  // Amount in cents
    'USD',
    'Invoice #123'
);

// Get transfer details
$transfer = $client->transfers->get('transfer-uuid');

// List transfers
$transfers = $client->transfers->list();
```

### Exchange Rates

```php
// List all exchange rates
$rates = $client->exchangeRates->list();

// Get specific exchange rate
$rate = $client->exchangeRates->get('USD', 'EUR');
echo "1 USD = {$rate->getRate()} EUR\n";

// Convert currency
$conversion = $client->exchangeRates->convert('USD', 'EUR', 100);
echo "\${$conversion['from_amount']} = €{$conversion['to_amount']}\n";

// Refresh rates
$result = $client->exchangeRates->refresh();
```

### GCU (Global Currency Unit)

```php
// Get GCU information
$gcu = $client->gcu->getInfo();

// Get real-time composition
$composition = $client->gcu->getComposition();
foreach ($composition->getComposition() as $asset) {
    echo "{$asset['asset_code']}: {$asset['percentage_of_basket']}%\n";
}

// Get value history
$history = $client->gcu->getValueHistory('7d', 'daily');

// Get active governance polls
$polls = $client->gcu->getActivePolls();

// Get supported banks
$banks = $client->gcu->getSupportedBanks();
```

### Webhooks

```php
// Create a webhook
$webhook = $client->webhooks->create(
    'Transaction Updates',
    'https://your-app.com/webhooks',
    ['transaction.created', 'transaction.completed'],
    ['X-Custom-Header' => 'value'],  // Optional headers
    'your-webhook-secret'             // Optional secret
);

// List webhooks
$webhooks = $client->webhooks->list();

// Get webhook details
$webhook = $client->webhooks->get('webhook-uuid');

// Update webhook
$webhook = $client->webhooks->update('webhook-uuid', [
    'name' => 'New Name',
    'is_active' => false
]);

// Delete webhook
$client->webhooks->delete('webhook-uuid');

// Get delivery history
$deliveries = $client->webhooks->getDeliveries('webhook-uuid');

// Get available events
$events = $client->webhooks->getEvents();

// Verify webhook signature (static method)
$isValid = \FinAegis\Resources\Webhooks::verifySignature(
    $payload,
    $signature,
    $secret
);
```

### Baskets

```php
// Get basket information
$basket = $client->baskets->get('GCU');

// Get basket value history
$history = $client->baskets->getHistory('GCU', '30d', 'daily');

// Create custom basket
$basket = $client->baskets->create(
    'MYBASKET',
    'My Custom Basket',
    ['USD' => 0.5, 'EUR' => 0.3, 'GBP' => 0.2]
);

// Compose basket tokens
$result = $client->baskets->compose('account-uuid', 'GCU', 1000);

// Decompose basket tokens
$result = $client->baskets->decompose('account-uuid', 'GCU', 500);
```

## Error Handling

```php
use FinAegis\Exceptions\{
    FinAegisException,
    ValidationException,
    AuthenticationException,
    AuthorizationException,
    NotFoundException,
    RateLimitException,
    NetworkException
};

try {
    $account = $client->accounts->get('invalid-uuid');
} catch (NotFoundException $e) {
    echo "Account not found: " . $e->getMessage() . "\n";
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    print_r($e->getErrors());
} catch (RateLimitException $e) {
    echo "Rate limit exceeded. Retry after {$e->getRetryAfter()} seconds\n";
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
} catch (NetworkException $e) {
    echo "Network error: " . $e->getMessage() . "\n";
} catch (FinAegisException $e) {
    echo "API error: " . $e->getMessage() . "\n";
    echo "Status code: " . $e->getStatusCode() . "\n";
}
```

## Working with Models

All API responses are wrapped in model objects that provide convenient getters:

```php
// Account model
$account = $client->accounts->get('account-uuid');
echo $account->getUuid() . "\n";
echo $account->getName() . "\n";
echo $account->getBalance() . "\n";
echo $account->getStatus() . "\n";

if ($account->isActive()) {
    echo "Account is active\n";
} elseif ($account->isFrozen()) {
    echo "Account is frozen\n";
} elseif ($account->isClosed()) {
    echo "Account is closed\n";
}

// Access raw attributes
$rawData = $account->toArray();
$jsonString = $account->toJson();
```

## Pagination

```php
// Paginated responses
$accounts = $client->accounts->list($page = 1, $perPage = 50);

echo "Page {$accounts->currentPage} of {$accounts->lastPage}\n";
echo "Total accounts: {$accounts->total}\n";

// Check if more pages exist
if ($accounts->hasMorePages()) {
    $nextPage = $client->accounts->list($accounts->currentPage + 1, $perPage);
}

// Iterate through all pages
$page = 1;
$allAccounts = [];

do {
    $response = $client->accounts->list($page, 50);
    foreach ($response->data as $account) {
        $allAccounts[] = $account;
    }
    $page++;
} while ($page <= $response->lastPage);

echo "Total accounts collected: " . count($allAccounts) . "\n";
```

## Advanced Usage

### Custom Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('finaegis');
$logger->pushHandler(new StreamHandler('finaegis.log', Logger::DEBUG));

$client = new Client('your-api-key', [
    'environment' => 'production',
    'logger' => $logger
]);
```

### Custom Requests

```php
// Make custom API requests
$response = $client->request('GET', '/custom-endpoint', [
    'query' => ['key' => 'value']
]);

$response = $client->request('POST', '/custom-endpoint', [
    'json' => ['data' => 'value']
]);
```

### Webhook Signature Verification

```php
// In your webhook handler
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FINAEGIS_SIGNATURE'] ?? '';

if (!\FinAegis\Resources\Webhooks::verifySignature($payload, $signature, 'your-secret')) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook
$data = json_decode($payload, true);
echo "Received {$data['event']} event\n";
```

## Examples

### Complete Payment Flow

```php
function processPayment($client, $fromAccountId, $toAccountId, $amountUsd) {
    try {
        // Convert dollars to cents
        $amountCents = (int)($amountUsd * 100);
        
        // Check sender balance
        $balances = $client->accounts->getBalances($fromAccountId);
        $usdBalance = null;
        
        foreach ($balances['balances'] as $balance) {
            if ($balance['asset_code'] === 'USD') {
                $usdBalance = $balance;
                break;
            }
        }
        
        if (!$usdBalance || $usdBalance['available_balance'] < $amountCents) {
            throw new \Exception("Insufficient balance");
        }
        
        // Create transfer
        $transfer = $client->transfers->create(
            $fromAccountId,
            $toAccountId,
            $amountCents,
            'USD',
            "Payment on " . date('Y-m-d H:i:s')
        );
        
        echo "Transfer completed: {$transfer->getUuid()}\n";
        return $transfer;
        
    } catch (\Exception $e) {
        echo "Payment failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}
```

### Monitor Account Activity

```php
function monitorAccountActivity($client, $accountId) {
    // Get recent transactions
    $transactions = $client->accounts->getTransactions($accountId, 1, 10);
    
    echo "Recent transactions for account {$accountId}:\n";
    foreach ($transactions->data as $tx) {
        $sign = $tx->getType() === 'deposit' ? '+' : '-';
        $amount = $tx->getAmount() / 100;
        echo "{$tx->created_at}: {$sign}\${$amount} - {$tx->getStatus()}\n";
    }
    
    // Get recent transfers
    $transfers = $client->accounts->getTransfers($accountId, 1, 10);
    
    echo "\nRecent transfers:\n";
    foreach ($transfers->data as $transfer) {
        $amount = $transfer->getAmount() / 100;
        if ($transfer->getFromAccount() === $accountId) {
            echo "{$transfer->created_at}: Sent \${$amount} to {$transfer->getToAccount()}\n";
        } else {
            echo "{$transfer->created_at}: Received \${$amount} from {$transfer->getFromAccount()}\n";
        }
    }
}
```

### Currency Exchange

```php
function exchangeCurrency($client, $accountId, $fromCurrency, $toCurrency, $amount) {
    // Get exchange rate
    $rate = $client->exchangeRates->get($fromCurrency, $toCurrency);
    echo "Exchange rate: 1 {$fromCurrency} = {$rate->getRate()} {$toCurrency}\n";
    
    // Calculate conversion
    $conversion = $client->exchangeRates->convert($fromCurrency, $toCurrency, $amount);
    echo "Converting {$amount} {$fromCurrency} = {$conversion['to_amount']} {$toCurrency}\n";
    
    // Perform the exchange (assuming you have an exchange endpoint)
    // This is just an example - actual implementation may vary
    return $conversion;
}
```

## Testing

```bash
# Run tests
composer test

# Run static analysis
composer phpstan

# Run code style checks
composer phpcs

# Fix code style issues
composer phpcbf
```

## License

MIT