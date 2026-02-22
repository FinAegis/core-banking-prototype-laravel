<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\DataObjects\MonetizedRouteConfig;
use App\Domain\X402\DataObjects\PaymentRequired;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\DataObjects\ResourceInfo;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class X402PricingService
{
    /**
     * Resolve the monetized route configuration for a request.
     */
    public function getRouteConfig(Request $request): ?MonetizedRouteConfig
    {
        if (! config('x402.enabled', false)) {
            return null;
        }

        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');

        // Database lookup first
        $endpoint = X402MonetizedEndpoint::query()
            ->active()
            ->forRoute($method, $path)
            ->first();

        if ($endpoint !== null) {
            return $endpoint->toMonetizedRouteConfig();
        }

        return null;
    }

    /**
     * Build the PaymentRequired response for a 402 response.
     */
    public function buildPaymentRequired(
        Request $request,
        MonetizedRouteConfig $config,
        ?string $error = null,
    ): PaymentRequired {
        $assetAddress = $this->resolveAssetAddress($config->network, $config->asset);
        $atomicAmount = $this->usdToAtomicUnits($config->price);
        $payTo = config('x402.server.pay_to', '');

        $requirements = new PaymentRequirements(
            scheme: $config->scheme,
            network: $config->network,
            asset: $assetAddress,
            amount: $atomicAmount,
            payTo: $payTo,
            maxTimeoutSeconds: (int) config('x402.server.max_timeout_seconds', 60),
            extra: $this->buildExtra($config->network, $assetAddress, $config->extra),
        );

        $resource = new ResourceInfo(
            url: '/' . ltrim($request->path(), '/'),
            description: $config->description ?: $config->method . ' ' . $config->path,
            mimeType: $config->mimeType,
        );

        return new PaymentRequired(
            x402Version: (int) config('x402.version', 2),
            resource: $resource,
            accepts: [$requirements],
            error: $error,
        );
    }

    /**
     * Convert a USD price string to atomic USDC units.
     *
     * USDC has 6 decimals: $1.00 = 1000000, $0.001 = 1000
     */
    public function usdToAtomicUnits(string $usdPrice): string
    {
        return bcmul($usdPrice, '1000000', 0);
    }

    /**
     * Convert atomic USDC units to a USD float.
     */
    public function atomicToUsd(string $atomicAmount): float
    {
        return (float) bcdiv($atomicAmount, '1000000', 6);
    }

    /**
     * Resolve the token contract address for a network/asset combination.
     */
    private function resolveAssetAddress(string $network, string $asset): string
    {
        $address = config("x402.assets.{$network}.{$asset}");

        if (! $address) {
            Log::warning('x402: No asset address configured', [
                'network' => $network,
                'asset'   => $asset,
            ]);

            return '';
        }

        return (string) $address;
    }

    /**
     * Build the extra field for PaymentRequirements (EIP-712 domain info for USDC).
     *
     * @param array<string, mixed> $configExtra
     * @return array<string, mixed>
     */
    private function buildExtra(string $network, string $assetAddress, array $configExtra = []): array
    {
        if (! empty($configExtra)) {
            return $configExtra;
        }

        // Default EIP-712 domain for USDC
        return [
            'name'    => 'USD Coin',
            'version' => '2',
        ];
    }
}
