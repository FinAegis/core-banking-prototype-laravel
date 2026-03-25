<?php

declare(strict_types=1);

namespace Zelta\Handlers;

use Psr\Http\Message\ResponseInterface;
use Zelta\Contracts\PaymentHandlerInterface;
use Zelta\Contracts\SignerInterface;

/**
 * Handles x402 Payment Required responses.
 *
 * Parses the PAYMENT-REQUIRED header, selects the best payment option,
 * signs a transfer authorization, and returns a PAYMENT-SIGNATURE header.
 */
class X402PaymentHandler implements PaymentHandlerInterface
{
    /** @var list<string> */
    private array $preferredNetworks;

    /**
     * @param list<string> $preferredNetworks CAIP-2 network preference order
     */
    public function __construct(
        private readonly SignerInterface $signer,
        array $preferredNetworks = ['eip155:8453', 'solana:mainnet', 'eip155:1'],
    ) {
        $this->preferredNetworks = $preferredNetworks;
    }

    public function canHandle(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 402) {
            return false;
        }

        return $response->hasHeader('X-Payment-Protocol')
            && strtolower($response->getHeaderLine('X-Payment-Protocol')) === 'x402';
    }

    public function handlePaymentRequired(ResponseInterface $response, string $url): array
    {
        $encoded = $response->getHeaderLine('PAYMENT-REQUIRED');
        if ($encoded === '') {
            return [];
        }

        $decoded = json_decode(base64_decode($encoded, true) ?: '', true);
        if (! is_array($decoded)) {
            return [];
        }

        $accepts = $decoded['accepts'] ?? [];
        $selected = $this->selectOption($accepts);
        if ($selected === null) {
            return [];
        }

        $signed = $this->signer->sign(
            network: $selected['network'] ?? '',
            to: $selected['payTo'] ?? '',
            amount: (string) ($selected['amount'] ?? '0'),
            asset: $selected['asset'] ?? '',
            timeout: (int) ($selected['maxTimeoutSeconds'] ?? 60),
            extra: $selected['extra'] ?? [],
        );

        $payload = [
            'x402Version' => $decoded['x402Version'] ?? 2,
            'resource'    => $decoded['resource'] ?? ['url' => $url],
            'accepted'    => $selected,
            'payload'     => $signed,
        ];

        return [
            'PAYMENT-SIGNATURE' => base64_encode((string) json_encode($payload)),
        ];
    }

    /**
     * Select the best payment option from available requirements.
     *
     * @param list<array<string, mixed>> $accepts
     * @return array<string, mixed>|null
     */
    private function selectOption(array $accepts): ?array
    {
        foreach ($this->preferredNetworks as $network) {
            foreach ($accepts as $option) {
                if (($option['network'] ?? '') === $network) {
                    return $option;
                }
            }
        }

        // Fallback: first EVM or Solana option
        foreach ($accepts as $option) {
            $net = $option['network'] ?? '';
            if (str_starts_with($net, 'eip155:') || str_starts_with($net, 'solana:')) {
                return $option;
            }
        }

        return null;
    }
}
