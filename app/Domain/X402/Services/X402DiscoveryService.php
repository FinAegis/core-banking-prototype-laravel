<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Enums\X402Network;

/**
 * X402 discovery service.
 *
 * Generates the .well-known/x402-configuration discovery document
 * for protocol auto-discovery, following the same pattern as MPP.
 */
class X402DiscoveryService
{
    /**
     * Generate the .well-known/x402-configuration discovery document.
     *
     * @return array<string, mixed>
     */
    public function getWellKnownConfiguration(): array
    {
        return [
            'x402_version'       => (int) config('x402.version', 2),
            'issuer'             => config('app.url'),
            'spec_url'           => 'https://x402.org',
            'default_network'    => config('x402.server.default_network', 'eip155:8453'),
            'supported_networks' => collect(X402Network::cases())->map(fn (X402Network $n): array => [
                'id'      => $n->value,
                'name'    => $n->label(),
                'testnet' => $n->isTestnet(),
            ])->values()->all(),
            'supported_assets'  => ['USDC'],
            'supported_schemes' => ['exact'],
            'pay_to'            => (string) config('x402.server.pay_to', ''),
            'facilitator'       => [
                'url'         => config('x402.facilitator.url', 'https://x402.org/facilitator'),
                'self_hosted' => (bool) config('x402.facilitator.self_hosted', false),
            ],
            'endpoints' => [
                'status'          => url('/api/v1/x402/status'),
                'supported'       => url('/api/v1/x402/supported'),
                'endpoints'       => url('/api/v1/x402/endpoints'),
                'payments'        => url('/api/v1/x402/payments'),
                'spending_limits' => url('/api/v1/x402/spending-limits'),
            ],
            'contracts' => (array) config('x402.contracts', []),
        ];
    }
}
