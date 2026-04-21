# Zelta Payment SDK

Transparent x402 and MPP payment handling for PHP applications.

> **Mirror repo notice** — if you're viewing this at `github.com/FinAegis/payment-sdk`, that repo is a read-only split of `packages/zelta-sdk/` from the [FinAegis core banking monorepo](https://github.com/FinAegis/core-banking-prototype-laravel). Please file issues and PRs against the monorepo.

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

Full documentation is available at [finaegis.org/developers](https://finaegis.org/developers).

## License

Apache-2.0 -- see [LICENSE](LICENSE) for details.
