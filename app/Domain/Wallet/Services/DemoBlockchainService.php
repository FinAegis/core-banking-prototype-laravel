<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\TransactionResult;
use Illuminate\Support\Facades\Log;

/**
 * Demo blockchain service that simulates blockchain operations without actual network calls.
 * Used for demonstrating platform capabilities in demo mode.
 */
class DemoBlockchainService implements BlockchainConnector
{
    private string $chain;

    private string $chainId;

    private array $demoBalances = [];

    private array $demoTransactions = [];

    private array $eventSubscriptions = [];

    public function __construct(string $chain = 'ethereum', string $chainId = '1')
    {
        $this->chain = $chain;
        $this->chainId = $chainId;

        // Initialize with some demo balances
        $this->initializeDemoData();
    }

    public function generateAddress(string $publicKey): AddressData
    {
        Log::info('Demo blockchain generating address', [
            'chain'      => $this->chain,
            'public_key' => substr($publicKey, 0, 10) . '...',
        ]);

        // Generate a deterministic demo address from public key
        $demoAddress = $this->generateDemoAddress($publicKey);

        return new AddressData(
            address: $demoAddress,
            publicKey: $publicKey,
            chain: $this->chain,
            metadata: [
                'chain_id'     => $this->chainId,
                'demo_mode'    => true,
                'generated_at' => now()->toIso8601String(),
            ]
        );
    }

    public function getBalance(string $address): BalanceData
    {
        Log::info('Demo blockchain balance request', [
            'address' => $address,
            'chain'   => $this->chain,
        ]);

        // Return demo balance or default
        $balance = $this->demoBalances[$address] ?? $this->getDefaultBalance();

        return new BalanceData(
            address: $address,
            balance: (string) $balance,
            chain: $this->chain,
            symbol: $this->getChainSymbol(),
            decimals: $this->getChainDecimals(),
            metadata: [
                'demo_mode'    => true,
                'last_updated' => now()->toIso8601String(),
            ]
        );
    }

    public function getTokenBalances(string $address): array
    {
        Log::info('Demo blockchain token balances request', [
            'address' => $address,
            'chain'   => $this->chain,
        ]);

        // Return some demo token balances
        return [
            [
                'contract' => '0x' . str_repeat('1', 40),
                'symbol'   => 'USDT',
                'decimals' => 6,
                'balance'  => '1000000000', // 1000 USDT
                'name'     => 'Tether USD',
            ],
            [
                'contract' => '0x' . str_repeat('2', 40),
                'symbol'   => 'USDC',
                'decimals' => 6,
                'balance'  => '500000000', // 500 USDC
                'name'     => 'USD Coin',
            ],
        ];
    }

    public function estimateGas(TransactionData $transaction): GasEstimate
    {
        Log::info('Demo blockchain gas estimation', [
            'from'  => $transaction->from,
            'to'    => $transaction->to,
            'value' => $transaction->value,
        ]);

        // Return realistic gas estimates based on chain
        $gasLimit = $this->chain === 'ethereum' ? '21000' : '250';
        $gasPrice = $this->chain === 'ethereum' ? '20000000000' : '1000'; // 20 gwei or 1000 satoshi
        $estimatedCost = bcmul($gasLimit, $gasPrice);

        return new GasEstimate(
            gasLimit: $gasLimit,
            gasPrice: $gasPrice,
            maxFeePerGas: $gasPrice, // For demo, use same as gas price
            maxPriorityFeePerGas: '1000000000', // 1 gwei priority fee
            estimatedCost: $estimatedCost,
            chain: $this->chain,
            metadata: [
                'demo_mode'    => true,
                'estimated_at' => now()->toIso8601String(),
            ]
        );
    }

    public function broadcastTransaction(SignedTransaction $transaction): TransactionResult
    {
        Log::info('Demo blockchain broadcasting transaction', [
            'hash'  => $transaction->hash,
            'chain' => $this->chain,
        ]);

        // Generate demo transaction hash
        $txHash = '0x' . hash('sha256', $transaction->hash . uniqid());

        // Store transaction for later retrieval
        $this->demoTransactions[$txHash] = [
            'hash'          => $txHash,
            'status'        => 'confirmed',
            'block'         => rand(1000000, 9999999),
            'confirmations' => rand(1, 12),
            'timestamp'     => now(),
            'data'          => $transaction,
        ];

        // Update balances if it's a transfer
        $this->updateDemoBalances($transaction);

        return new TransactionResult(
            hash: $txHash,
            status: 'confirmed',
            blockNumber: $this->demoTransactions[$txHash]['block'],
            metadata: [
                'demo_mode'              => true,
                'chain'                  => $this->chain,
                'confirmations'          => $this->demoTransactions[$txHash]['confirmations'],
                'broadcast_at'           => now()->toIso8601String(),
                'estimated_confirmation' => now()->addSeconds(15)->toIso8601String(),
            ]
        );
    }

    public function getTransaction(string $hash): ?TransactionData
    {
        Log::info('Demo blockchain transaction lookup', [
            'hash'  => $hash,
            'chain' => $this->chain,
        ]);

        if (! isset($this->demoTransactions[$hash])) {
            return null;
        }

        $tx = $this->demoTransactions[$hash];
        $originalData = $tx['data'] ?? null;

        return new TransactionData(
            from: $originalData->from ?? '0x' . str_repeat('0', 40),
            to: $originalData->to ?? '0x' . str_repeat('0', 40),
            value: $originalData->value ?? '0',
            chain: $this->chain,
            data: $originalData->data ?? '0x',
            gasLimit: $originalData->gasLimit ?? '21000',
            gasPrice: $originalData->gasPrice ?? '0',
            nonce: $originalData->nonce ?? 0,
            hash: $hash,
            blockNumber: $tx['block'],
            status: $tx['status'],
            metadata: [
                'demo_mode'     => true,
                'confirmations' => $tx['confirmations'],
            ]
        );
    }

    public function getGasPrices(): array
    {
        Log::info('Demo blockchain gas prices request', ['chain' => $this->chain]);

        if ($this->chain === 'ethereum') {
            return [
                'slow'     => '10000000000', // 10 gwei
                'standard' => '20000000000', // 20 gwei
                'fast'     => '30000000000', // 30 gwei
                'instant'  => '50000000000', // 50 gwei
            ];
        }

        // Bitcoin-style fees
        return [
            'slow'     => '1000', // 1000 satoshi
            'standard' => '2000',
            'fast'     => '5000',
            'instant'  => '10000',
        ];
    }

    public function subscribeToEvents(string $address, callable $callback): void
    {
        Log::info('Demo blockchain event subscription', [
            'address' => $address,
            'chain'   => $this->chain,
        ]);

        $this->eventSubscriptions[$address] = $callback;

        // Simulate an incoming transaction after a delay
        if (config('demo.features.auto_approve', true)) {
            dispatch(function () use ($address, $callback) {
                sleep(rand(5, 15));
                $callback([
                    'type'      => 'incoming_transaction',
                    'address'   => $address,
                    'amount'    => (string) rand(1000000, 10000000),
                    'from'      => '0x' . str_repeat('f', 40),
                    'hash'      => '0x' . hash('sha256', uniqid()),
                    'timestamp' => now()->toIso8601String(),
                ]);
            })->afterResponse();
        }
    }

    public function unsubscribeFromEvents(string $address): void
    {
        Log::info('Demo blockchain event unsubscription', [
            'address' => $address,
            'chain'   => $this->chain,
        ]);

        unset($this->eventSubscriptions[$address]);
    }

    public function getChainId(): string
    {
        return $this->chainId;
    }

    public function isHealthy(): bool
    {
        // Always healthy in demo mode
        return true;
    }

    public function getTransactionStatus(string $hash): TransactionResult
    {
        Log::info('Demo blockchain transaction status', [
            'hash'  => $hash,
            'chain' => $this->chain,
        ]);

        if (isset($this->demoTransactions[$hash])) {
            $tx = $this->demoTransactions[$hash];

            return new TransactionResult(
                hash: $hash,
                status: $tx['status'],
                blockNumber: $tx['block'],
                metadata: [
                    'demo_mode'     => true,
                    'chain'         => $this->chain,
                    'confirmations' => $tx['confirmations'] + rand(1, 5), // Increase confirmations
                ]
            );
        }

        // Return pending for unknown transactions
        return new TransactionResult(
            hash: $hash,
            status: 'pending',
            blockNumber: null,
            metadata: [
                'demo_mode'     => true,
                'chain'         => $this->chain,
                'confirmations' => 0,
            ]
        );
    }

    public function validateAddress(string $address): bool
    {
        // Basic validation based on chain
        if ($this->chain === 'ethereum' || $this->chain === 'polygon') {
            return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
        }

        if ($this->chain === 'bitcoin') {
            // Simplified Bitcoin address validation
            return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) === 1
                || preg_match('/^bc1[a-z0-9]{39,59}$/', $address) === 1;
        }

        return false;
    }

    private function initializeDemoData(): void
    {
        // Initialize some addresses with balances
        $this->demoBalances = [
            '0x' . str_repeat('1', 40) => '1000000000000000000', // 1 ETH
            '0x' . str_repeat('2', 40) => '5000000000000000000', // 5 ETH
            '0x' . str_repeat('3', 40) => '100000000000000000', // 0.1 ETH
            '1' . str_repeat('A', 33)  => '100000000', // 1 BTC
            '3' . str_repeat('B', 33)  => '50000000', // 0.5 BTC
        ];
    }

    private function generateDemoAddress(string $publicKey): string
    {
        // Generate a deterministic but realistic-looking address
        $hash = hash('sha256', $publicKey . $this->chain);

        if ($this->chain === 'ethereum' || $this->chain === 'polygon') {
            return '0x' . substr($hash, 0, 40);
        }

        if ($this->chain === 'bitcoin') {
            // Generate a P2PKH-style address (starting with 1)
            return '1' . substr(strtoupper($hash), 0, 33);
        }

        return substr($hash, 0, 42);
    }

    private function getChainSymbol(): string
    {
        return match ($this->chain) {
            'ethereum' => 'ETH',
            'polygon'  => 'MATIC',
            'bitcoin'  => 'BTC',
            default    => 'DEMO',
        };
    }

    private function getChainDecimals(): int
    {
        return match ($this->chain) {
            'ethereum', 'polygon' => 18,
            'bitcoin' => 8,
            default   => 18,
        };
    }

    private function getDefaultBalance(): string
    {
        return match ($this->chain) {
            'ethereum', 'polygon' => '100000000000000000', // 0.1 ETH/MATIC
            'bitcoin' => '10000000', // 0.1 BTC
            default   => '1000000',
        };
    }

    private function updateDemoBalances(SignedTransaction $transaction): void
    {
        // Simulate balance updates for transfers
        if (isset($transaction->from) && isset($transaction->to) && isset($transaction->value)) {
            $from = $transaction->from;
            $to = $transaction->to;
            $value = $transaction->value;

            // Deduct from sender
            if (isset($this->demoBalances[$from])) {
                $this->demoBalances[$from] = bcsub($this->demoBalances[$from], $value);
            }

            // Add to receiver
            if (! isset($this->demoBalances[$to])) {
                $this->demoBalances[$to] = '0';
            }
            $this->demoBalances[$to] = bcadd($this->demoBalances[$to], $value);
        }
    }
}
