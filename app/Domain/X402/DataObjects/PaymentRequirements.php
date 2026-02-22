<?php

declare(strict_types=1);

namespace App\Domain\X402\DataObjects;

/**
 * Describes one acceptable payment option for an x402-protected resource.
 *
 * A server may expose multiple PaymentRequirements (e.g. different networks),
 * and the client picks one it can satisfy.
 *
 * Amounts are expressed in atomic USDC units (6 decimals).
 */
readonly class PaymentRequirements
{
    /**
     * @param string $scheme   Payment scheme (exact | upto).
     * @param string $network  CAIP-2 network identifier (e.g. "eip155:8453").
     * @param string $asset    Asset contract address (e.g. USDC on Base).
     * @param string $amount   Amount in atomic units (6 decimals for USDC).
     * @param string $payTo    Recipient wallet address.
     * @param int    $maxTimeoutSeconds  Maximum time the payment authorization is valid.
     * @param array<string, mixed> $extra  Protocol-specific extensions.
     */
    public function __construct(
        public string $scheme,
        public string $network,
        public string $asset,
        public string $amount,
        public string $payTo,
        public int $maxTimeoutSeconds,
        public array $extra = [],
    ) {
    }

    /**
     * Serialize to a plain array.
     *
     * @return array{scheme: string, network: string, asset: string, amount: string, payTo: string, maxTimeoutSeconds: int, extra: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'scheme'            => $this->scheme,
            'network'           => $this->network,
            'asset'             => $this->asset,
            'amount'            => $this->amount,
            'payTo'             => $this->payTo,
            'maxTimeoutSeconds' => $this->maxTimeoutSeconds,
            'extra'             => $this->extra,
        ];
    }

    /**
     * Hydrate from a plain array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['scheme', 'network', 'asset', 'amount', 'payTo', 'maxTimeoutSeconds'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw \App\Domain\X402\Exceptions\X402InvalidPayloadException::missingField($field);
            }
        }

        // Validate amount is a positive numeric string
        if (bccomp((string) $data['amount'], '0') <= 0) {
            throw \App\Domain\X402\Exceptions\X402InvalidPayloadException::invalidField('amount', 'Amount must be positive');
        }

        return new self(
            scheme: $data['scheme'],
            network: $data['network'],
            asset: $data['asset'],
            amount: $data['amount'],
            payTo: $data['payTo'],
            maxTimeoutSeconds: (int) $data['maxTimeoutSeconds'],
            extra: $data['extra'] ?? [],
        );
    }
}
