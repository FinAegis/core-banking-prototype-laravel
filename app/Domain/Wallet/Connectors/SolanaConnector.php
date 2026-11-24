<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\TransactionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Solana blockchain connector.
 *
 * Uses Solana JSON-RPC API for blockchain operations.
 * Supports mainnet-beta, testnet, and devnet.
 */
class SolanaConnector implements BlockchainConnector
{
    public function __construct(
        private readonly string $rpcUrl,
        private readonly string $network = 'mainnet-beta',
    ) {
    }

    public function generateAddress(string $publicKey): AddressData
    {
        // Solana addresses are base58-encoded public keys (32 bytes)
        return new AddressData(
            address: $publicKey,
            publicKey: $publicKey,
            chain: 'solana',
            metadata: [
                'network'      => $this->network,
                'generated_at' => now()->toIso8601String(),
            ]
        );
    }

    public function getBalance(string $address): BalanceData
    {
        try {
            $response = $this->rpcCall('getBalance', [$address]);
            $lamports = (string) ($response['result']['value'] ?? '0');

            return new BalanceData(
                address: $address,
                balance: $lamports,
                chain: 'solana',
                symbol: 'SOL',
                decimals: 9,
                metadata: [
                    'network'      => $this->network,
                    'last_updated' => now()->toIso8601String(),
                ]
            );
        } catch (Throwable $e) {
            Log::error('Solana getBalance failed', ['address' => $address, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getTokenBalances(string $address): array
    {
        try {
            $response = $this->rpcCall('getTokenAccountsByOwner', [
                $address,
                ['programId' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'],
                ['encoding' => 'jsonParsed'],
            ]);

            $accounts = $response['result']['value'] ?? [];

            return array_map(function (array $account): array {
                $info = $account['account']['data']['parsed']['info'] ?? [];

                return [
                    'contract' => $info['mint'] ?? '',
                    'symbol'   => 'SPL',
                    'decimals' => (int) ($info['tokenAmount']['decimals'] ?? 0),
                    'balance'  => $info['tokenAmount']['amount'] ?? '0',
                    'name'     => 'SPL Token',
                ];
            }, $accounts);
        } catch (Throwable $e) {
            Log::error('Solana getTokenBalances failed', ['address' => $address, 'error' => $e->getMessage()]);

            return [];
        }
    }

    public function estimateGas(TransactionData $transaction): GasEstimate
    {
        // Solana fees are fixed per signature (5000 lamports = 0.000005 SOL)
        $feePerSignature = '5000';

        return new GasEstimate(
            gasLimit: '1',
            gasPrice: $feePerSignature,
            maxFeePerGas: $feePerSignature,
            maxPriorityFeePerGas: '0',
            estimatedCost: $feePerSignature,
            chain: 'solana',
            metadata: [
                'network'      => $this->network,
                'fee_type'     => 'per_signature',
                'estimated_at' => now()->toIso8601String(),
            ]
        );
    }

    public function broadcastTransaction(SignedTransaction $transaction): TransactionResult
    {
        try {
            $response = $this->rpcCall('sendTransaction', [
                $transaction->rawTransaction,
                ['encoding' => 'base64', 'preflightCommitment' => 'confirmed'],
            ]);

            $txHash = $response['result'] ?? '';

            return new TransactionResult(
                hash: $txHash,
                status: 'pending',
                blockNumber: null,
                metadata: [
                    'chain'        => 'solana',
                    'network'      => $this->network,
                    'broadcast_at' => now()->toIso8601String(),
                ]
            );
        } catch (Throwable $e) {
            Log::error('Solana broadcastTransaction failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getTransaction(string $hash): ?TransactionData
    {
        try {
            $response = $this->rpcCall('getTransaction', [
                $hash,
                ['encoding' => 'jsonParsed', 'commitment' => 'confirmed'],
            ]);

            $result = $response['result'] ?? null;
            if (! $result) {
                return null;
            }

            $meta = $result['meta'] ?? [];
            $slot = $result['slot'] ?? null;

            return new TransactionData(
                from: '',
                to: '',
                value: '0',
                chain: 'solana',
                hash: $hash,
                blockNumber: $slot,
                status: ($meta['err'] ?? null) === null ? 'confirmed' : 'failed',
                metadata: [
                    'network' => $this->network,
                    'slot'    => $slot,
                    'fee'     => (string) ($meta['fee'] ?? 0),
                ]
            );
        } catch (Throwable $e) {
            Log::error('Solana getTransaction failed', ['hash' => $hash, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, string> */
    public function getGasPrices(): array
    {
        // Solana has fixed fees per signature
        return [
            'slow'     => '5000',
            'standard' => '5000',
            'fast'     => '5000',
            'instant'  => '5000',
        ];
    }

    public function subscribeToEvents(string $address, callable $callback): void
    {
        Log::info('Solana event subscription requested', [
            'address' => $address,
            'network' => $this->network,
        ]);
    }

    public function unsubscribeFromEvents(string $address): void
    {
        Log::info('Solana event unsubscription requested', ['address' => $address]);
    }

    public function getChainId(): string
    {
        return $this->network;
    }

    public function isHealthy(): bool
    {
        try {
            $response = $this->rpcCall('getHealth', []);

            return ($response['result'] ?? '') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    public function getTransactionStatus(string $hash): TransactionResult
    {
        try {
            $response = $this->rpcCall('getSignatureStatuses', [[$hash]]);
            $status = $response['result']['value'][0] ?? null;

            if (! $status) {
                return new TransactionResult(
                    hash: $hash,
                    status: 'pending',
                    blockNumber: null,
                    metadata: ['chain' => 'solana', 'network' => $this->network, 'confirmations' => 0]
                );
            }

            $confirmations = $status['confirmations'] ?? 0;
            $finalized = ($status['confirmationStatus'] ?? '') === 'finalized';

            return new TransactionResult(
                hash: $hash,
                status: ($status['err'] ?? null) !== null ? 'failed' : ($finalized ? 'confirmed' : 'pending'),
                blockNumber: $status['slot'] ?? null,
                metadata: [
                    'chain'              => 'solana',
                    'network'            => $this->network,
                    'confirmations'      => $finalized ? 32 : $confirmations,
                    'confirmationStatus' => $status['confirmationStatus'] ?? 'unknown',
                ]
            );
        } catch (Throwable $e) {
            Log::error('Solana getTransactionStatus failed', ['hash' => $hash, 'error' => $e->getMessage()]);

            return new TransactionResult(
                hash: $hash,
                status: 'unknown',
                blockNumber: null,
                metadata: ['chain' => 'solana', 'error' => $e->getMessage()]
            );
        }
    }

    public function validateAddress(string $address): bool
    {
        // Solana addresses are base58-encoded 32-byte public keys (32-44 chars)
        return (bool) preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
    }

    /** @param array<int, mixed> $params
     * @return array<string, mixed> */
    private function rpcCall(string $method, array $params): array
    {
        $response = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => $method,
            'params'  => $params,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Solana RPC call failed: {$method} - HTTP {$response->status()}");
        }

        return $response->json();
    }
}
