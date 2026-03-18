<?php

declare(strict_types=1);

namespace App\Domain\CrossChain\Services\Adapters;

use App\Domain\CrossChain\Contracts\BridgeAdapterInterface;
use App\Domain\CrossChain\Enums\BridgeProvider;
use App\Domain\CrossChain\Enums\BridgeStatus;
use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\CrossChain\ValueObjects\BridgeQuote;
use App\Domain\CrossChain\ValueObjects\BridgeRoute;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Circle CCTP (Cross-Chain Transfer Protocol) bridge adapter.
 *
 * Supports native USDC transfers across EVM chains via Circle's burn-and-mint mechanism.
 * In production, integrates with Circle Attestation Service for message verification.
 * Falls back to demo-mode simulation when attestation endpoint is not configured.
 *
 * CCTP Flow: depositForBurn() on source → Circle attests → receiveMessage() on dest
 */
class CircleCctpBridgeAdapter implements BridgeAdapterInterface
{
    private const SUPPORTED_CHAINS = [
        'ethereum', 'polygon', 'arbitrum', 'base',
    ];

    /** @var array<string, int> CCTP domain IDs per network */
    private const DOMAIN_IDS = [
        'ethereum' => 0,
        'polygon'  => 7,
        'arbitrum' => 3,
        'base'     => 6,
    ];

    public function getProvider(): BridgeProvider
    {
        return BridgeProvider::CIRCLE_CCTP;
    }

    public function estimateFee(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): array {
        // CCTP is near-zero fee (only gas cost for burn tx on source chain)
        $gasFee = $this->getGasFee($sourceChain);

        return [
            'fee'            => $gasFee,
            'fee_currency'   => 'USD',
            'estimated_time' => BridgeProvider::CIRCLE_CCTP->getAverageTransferTime(),
        ];
    }

    public function getQuote(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): BridgeQuote {
        $feeData = $this->estimateFee($sourceChain, $destChain, $token, $amount);
        /** @var numeric-string $numericAmount */
        $numericAmount = $amount;
        /** @var numeric-string $fee */
        $fee = $feeData['fee'];
        $outputAmount = bcsub($numericAmount, $fee, 8);

        $route = new BridgeRoute(
            $sourceChain,
            $destChain,
            $token,
            BridgeProvider::CIRCLE_CCTP,
            $feeData['estimated_time'],
            $feeData['fee'],
        );

        return new BridgeQuote(
            quoteId: 'cctp-' . Str::uuid()->toString(),
            route: $route,
            inputAmount: $amount,
            outputAmount: $outputAmount,
            fee: $feeData['fee'],
            feeCurrency: $feeData['fee_currency'],
            estimatedTimeSeconds: $feeData['estimated_time'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) config('crosschain.fees.quote_ttl_seconds', 300)),
        );
    }

    public function initiateBridge(
        BridgeQuote $quote,
        string $senderAddress,
        string $recipientAddress,
    ): array {
        Log::info('Circle CCTP: Initiating bridge transfer', [
            'source'    => $quote->getSourceChain()->value,
            'dest'      => $quote->getDestChain()->value,
            'token'     => $quote->route->token,
            'amount'    => $quote->inputAmount,
            'sender'    => $senderAddress,
            'recipient' => $recipientAddress,
        ]);

        $attestationApi = config('crosschain.cctp.attestation_api', '');

        if ($attestationApi !== '' && app()->environment('production')) {
            return $this->initiateBridgeViaApi(
                (string) $attestationApi,
                $quote,
                $senderAddress,
                $recipientAddress,
            );
        }

        // Demo mode fallback
        $transactionId = 'cctp-tx-' . Str::uuid()->toString();

        return [
            'transaction_id' => $transactionId,
            'status'         => BridgeStatus::INITIATED,
            'source_tx_hash' => '0x' . Str::random(64),
        ];
    }

    public function getBridgeStatus(string $transactionId): array
    {
        Log::debug('Circle CCTP: Checking bridge status', ['transaction_id' => $transactionId]);

        $attestationApi = config('crosschain.cctp.attestation_api', '');

        if ($attestationApi !== '' && app()->environment('production')) {
            return $this->getAttestationStatus((string) $attestationApi, $transactionId);
        }

        // Demo mode fallback
        return [
            'status'         => BridgeStatus::COMPLETED,
            'source_tx_hash' => '0x' . hash('sha256', $transactionId . 'source'),
            'dest_tx_hash'   => '0x' . hash('sha256', $transactionId . 'dest'),
            'confirmations'  => 65,
        ];
    }

    public function getSupportedRoutes(): array
    {
        $chains = array_map(
            fn (string $chain) => CrossChainNetwork::from($chain),
            self::SUPPORTED_CHAINS,
        );

        $routes = [];

        foreach ($chains as $source) {
            foreach ($chains as $dest) {
                if ($source === $dest) {
                    continue;
                }

                $routes[] = new BridgeRoute(
                    $source,
                    $dest,
                    'USDC',
                    BridgeProvider::CIRCLE_CCTP,
                    BridgeProvider::CIRCLE_CCTP->getAverageTransferTime(),
                    $this->getGasFee($source),
                );
            }
        }

        return $routes;
    }

    public function supportsRoute(CrossChainNetwork $source, CrossChainNetwork $dest, string $token): bool
    {
        return in_array($source->value, self::SUPPORTED_CHAINS)
            && in_array($dest->value, self::SUPPORTED_CHAINS)
            && $source !== $dest
            && $token === 'USDC';
    }

    /**
     * Get estimated gas fee for burn transaction on source chain.
     */
    private function getGasFee(CrossChainNetwork $source): string
    {
        // L2s have much lower gas costs than Ethereum mainnet
        return match ($source) {
            CrossChainNetwork::ETHEREUM => '0.80',
            CrossChainNetwork::POLYGON  => '0.01',
            CrossChainNetwork::ARBITRUM => '0.05',
            CrossChainNetwork::BASE     => '0.02',
            default                     => '0.10',
        };
    }

    /**
     * Initiate CCTP bridge via Circle API (production).
     *
     * Calls TokenMessenger.depositForBurn() on the source chain,
     * then monitors Circle Attestation Service for signed attestation.
     *
     * @return array{transaction_id: string, status: BridgeStatus, source_tx_hash: ?string}
     */
    private function initiateBridgeViaApi(
        string $attestationApi,
        BridgeQuote $quote,
        string $senderAddress,
        string $recipientAddress,
    ): array {
        $sourceDomain = self::DOMAIN_IDS[$quote->getSourceChain()->value] ?? 0;
        $destDomain = self::DOMAIN_IDS[$quote->getDestChain()->value] ?? 0;

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($attestationApi . '/v1/burns', [
                'source_domain'      => $sourceDomain,
                'destination_domain' => $destDomain,
                'amount'             => $quote->inputAmount,
                'burn_token'         => $quote->route->token,
                'sender'             => $senderAddress,
                'recipient'          => $recipientAddress,
            ]);

        if (! $response->successful()) {
            Log::error('Circle CCTP: Burn initiation failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return [
                'transaction_id' => 'cctp-failed-' . Str::uuid()->toString(),
                'status'         => BridgeStatus::FAILED,
                'source_tx_hash' => null,
            ];
        }

        $data = $response->json();

        return [
            'transaction_id' => (string) ($data['message_hash'] ?? $data['id'] ?? Str::uuid()->toString()),
            'status'         => BridgeStatus::INITIATED,
            'source_tx_hash' => (string) ($data['source_tx_hash'] ?? null),
        ];
    }

    /**
     * Query Circle Attestation Service for message attestation status (production).
     *
     * @return array{status: BridgeStatus, source_tx_hash: ?string, dest_tx_hash: ?string, confirmations: int}
     */
    private function getAttestationStatus(string $attestationApi, string $messageHash): array
    {
        $response = Http::timeout(15)
            ->get($attestationApi . '/v1/attestations/' . $messageHash);

        if (! $response->successful()) {
            return [
                'status'         => BridgeStatus::CONFIRMING,
                'source_tx_hash' => null,
                'dest_tx_hash'   => null,
                'confirmations'  => 0,
            ];
        }

        $data = $response->json();
        $attestationStatus = (string) ($data['status'] ?? 'pending');

        $status = match ($attestationStatus) {
            'complete'              => BridgeStatus::COMPLETED,
            'pending_confirmations' => BridgeStatus::CONFIRMING,
            default                 => BridgeStatus::INITIATED,
        };

        return [
            'status'         => $status,
            'source_tx_hash' => (string) ($data['source_tx_hash'] ?? null),
            'dest_tx_hash'   => (string) ($data['destination_tx_hash'] ?? null),
            'confirmations'  => (int) ($data['confirmations'] ?? 0),
        ];
    }
}
