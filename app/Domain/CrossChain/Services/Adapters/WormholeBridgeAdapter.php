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
 * Wormhole V2 Portal Token Bridge adapter.
 *
 * In production, integrates with Wormhole Guardian RPC and Token Bridge contracts
 * for VAA (Verified Action Approval) submission and cross-chain token transfers.
 * Falls back to demo-mode simulation when RPC endpoint is not configured.
 */
class WormholeBridgeAdapter implements BridgeAdapterInterface
{
    private const SUPPORTED_CHAINS = [
        'ethereum', 'polygon', 'bsc', 'arbitrum', 'optimism', 'base', 'solana',
    ];

    /** @var array<string, int> Wormhole chain IDs per network */
    private const CHAIN_IDS = [
        'ethereum' => 2,
        'polygon'  => 5,
        'bsc'      => 4,
        'arbitrum' => 23,
        'optimism' => 24,
        'base'     => 30,
        'solana'   => 1,
    ];

    public function getProvider(): BridgeProvider
    {
        return BridgeProvider::WORMHOLE;
    }

    public function estimateFee(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): array {
        $baseFee = $this->getBaseFee($sourceChain, $destChain);
        $relayerFee = $this->getRelayerFee($amount);
        $totalFee = bcadd($baseFee, $relayerFee, 8);

        return [
            'fee'            => $totalFee,
            'fee_currency'   => 'USD',
            'estimated_time' => BridgeProvider::WORMHOLE->getAverageTransferTime(),
        ];
    }

    public function getQuote(
        CrossChainNetwork $sourceChain,
        CrossChainNetwork $destChain,
        string $token,
        string $amount,
    ): BridgeQuote {
        $feeData = $this->estimateFee($sourceChain, $destChain, $token, $amount);
        $outputAmount = bcsub($amount, $feeData['fee'], 8);

        $route = new BridgeRoute(
            $sourceChain,
            $destChain,
            $token,
            BridgeProvider::WORMHOLE,
            $feeData['estimated_time'],
            $feeData['fee'],
        );

        return new BridgeQuote(
            quoteId: 'wormhole-' . Str::uuid()->toString(),
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
        Log::info('Wormhole: Initiating bridge transfer', [
            'source'    => $quote->getSourceChain()->value,
            'dest'      => $quote->getDestChain()->value,
            'token'     => $quote->route->token,
            'amount'    => $quote->inputAmount,
            'sender'    => $senderAddress,
            'recipient' => $recipientAddress,
        ]);

        $guardianRpc = config('crosschain.wormhole.guardian_rpc', '');

        if ($guardianRpc !== '' && app()->environment('production')) {
            return $this->initiateBridgeViaRpc(
                (string) $guardianRpc,
                $quote,
                $senderAddress,
                $recipientAddress,
            );
        }

        // Demo mode fallback
        $transactionId = 'wormhole-tx-' . Str::uuid()->toString();

        return [
            'transaction_id' => $transactionId,
            'status'         => BridgeStatus::INITIATED,
            'source_tx_hash' => '0x' . Str::random(64),
        ];
    }

    public function getBridgeStatus(string $transactionId): array
    {
        Log::debug('Wormhole: Checking bridge status', ['transaction_id' => $transactionId]);

        $guardianRpc = config('crosschain.wormhole.guardian_rpc', '');

        if ($guardianRpc !== '' && app()->environment('production')) {
            return $this->getStatusViaRpc((string) $guardianRpc, $transactionId);
        }

        // Demo mode fallback
        return [
            'status'         => BridgeStatus::COMPLETED,
            'source_tx_hash' => '0x' . hash('sha256', $transactionId . 'source'),
            'dest_tx_hash'   => '0x' . hash('sha256', $transactionId . 'dest'),
            'confirmations'  => 15,
        ];
    }

    public function getSupportedRoutes(): array
    {
        $chains = array_map(
            fn (string $chain) => CrossChainNetwork::from($chain),
            self::SUPPORTED_CHAINS,
        );

        $routes = [];
        $tokens = ['USDC', 'USDT', 'WETH', 'WBTC'];

        foreach ($chains as $source) {
            foreach ($chains as $dest) {
                if ($source === $dest) {
                    continue;
                }
                foreach ($tokens as $token) {
                    $routes[] = new BridgeRoute(
                        $source,
                        $dest,
                        $token,
                        BridgeProvider::WORMHOLE,
                        BridgeProvider::WORMHOLE->getAverageTransferTime(),
                        $this->getBaseFee($source, $dest),
                    );
                }
            }
        }

        return $routes;
    }

    public function supportsRoute(CrossChainNetwork $source, CrossChainNetwork $dest, string $token): bool
    {
        return in_array($source->value, self::SUPPORTED_CHAINS)
            && in_array($dest->value, self::SUPPORTED_CHAINS)
            && $source !== $dest;
    }

    private function getBaseFee(CrossChainNetwork $source, CrossChainNetwork $dest): string
    {
        // Wormhole fees vary by chain pair
        $isL2 = in_array($source->value, ['arbitrum', 'optimism', 'base'])
            || in_array($dest->value, ['arbitrum', 'optimism', 'base']);

        return $isL2 ? '0.50' : '2.00';
    }

    private function getRelayerFee(string $amount): string
    {
        // 0.05% relayer fee
        return bcmul($amount, '0.0005', 8);
    }

    /**
     * Initiate bridge transfer via Wormhole Guardian RPC (production).
     *
     * Submits a token transfer to the Wormhole Token Bridge contract,
     * which emits a VAA that guardians will sign for cross-chain delivery.
     *
     * @return array{transaction_id: string, status: BridgeStatus, source_tx_hash: ?string}
     */
    private function initiateBridgeViaRpc(
        string $guardianRpc,
        BridgeQuote $quote,
        string $senderAddress,
        string $recipientAddress,
    ): array {
        $sourceChainId = self::CHAIN_IDS[$quote->getSourceChain()->value] ?? 0;
        $destChainId = self::CHAIN_IDS[$quote->getDestChain()->value] ?? 0;

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($guardianRpc . '/v1/token_bridge/transfer', [
                'source_chain_id' => $sourceChainId,
                'target_chain_id' => $destChainId,
                'token'           => $quote->route->token,
                'amount'          => $quote->inputAmount,
                'sender'          => $senderAddress,
                'recipient'       => $recipientAddress,
                'relayer_fee'     => $quote->fee,
            ]);

        if (! $response->successful()) {
            Log::error('Wormhole RPC: Bridge initiation failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return [
                'transaction_id' => 'wormhole-failed-' . Str::uuid()->toString(),
                'status'         => BridgeStatus::FAILED,
                'source_tx_hash' => null,
            ];
        }

        $data = $response->json();

        return [
            'transaction_id' => (string) ($data['sequence'] ?? $data['transaction_id'] ?? Str::uuid()->toString()),
            'status'         => BridgeStatus::INITIATED,
            'source_tx_hash' => (string) ($data['tx_hash'] ?? null),
        ];
    }

    /**
     * Query Wormhole Guardian RPC for VAA status (production).
     *
     * @return array{status: BridgeStatus, source_tx_hash: ?string, dest_tx_hash: ?string, confirmations: int}
     */
    private function getStatusViaRpc(string $guardianRpc, string $transactionId): array
    {
        $response = Http::timeout(15)
            ->get($guardianRpc . '/v1/signed_vaa/' . $transactionId);

        if (! $response->successful()) {
            Log::warning('Wormhole RPC: Status check failed', [
                'transaction_id' => $transactionId,
                'status'         => $response->status(),
            ]);

            return [
                'status'         => BridgeStatus::CONFIRMING,
                'source_tx_hash' => null,
                'dest_tx_hash'   => null,
                'confirmations'  => 0,
            ];
        }

        $data = $response->json();
        $vaaBytes = $data['vaa_bytes'] ?? null;

        // If VAA is signed, the transfer is confirmed on source chain
        $status = $vaaBytes !== null ? BridgeStatus::COMPLETED : BridgeStatus::CONFIRMING;
        $confirmations = (int) ($data['guardian_signatures'] ?? 0);

        return [
            'status'         => $status,
            'source_tx_hash' => (string) ($data['source_tx_hash'] ?? null),
            'dest_tx_hash'   => (string) ($data['dest_tx_hash'] ?? null),
            'confirmations'  => $confirmations,
        ];
    }
}
