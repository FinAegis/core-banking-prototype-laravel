# Zelta Payment SDK

Transparent x402 and MPP payment handling for PHP applications.

## Installation

```bash
composer require finaegis/payment-sdk
```

## Quick Start

```php
use Zelta\ZeltaClient;
use Zelta\DataObjects\PaymentConfig;
use Zelta\Handlers\X402PaymentHandler;

// Create a signer that implements Zelta\Contracts\SignerInterface
$signer = new YourSigner();

$client = new ZeltaClient(
    config: new PaymentConfig(
        baseUrl: 'https://api.zelta.app',
        apiKey: 'zk_live_xxx',
        autoPay: true,
    ),
    payment: new X402PaymentHandler($signer),
);

// Requests that return 402 are automatically paid and retried
$result = $client->get('/v1/premium/data');

echo $result->statusCode; // 200
echo $result->paid;       // true
```

## Documentation

Full documentation is available at [zelta.app/developers](https://zelta.app/developers).

## License

Apache-2.0 -- see [LICENSE](LICENSE) for details.
