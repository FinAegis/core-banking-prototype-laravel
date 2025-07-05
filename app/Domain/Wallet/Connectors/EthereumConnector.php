<?php

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Web3\Web3;
use Web3\Contract;
use kornrunner\Keccak;
use Elliptic\EC;

class EthereumConnector implements BlockchainConnector
{
    protected ?Web3 $web3 = null;
    protected string $rpcUrl;
    protected string $chainId;
    protected array $eventSubscriptions = [];
    
    public function __construct(string $rpcUrl, string $chainId = '1')
    {
        $this->rpcUrl = $rpcUrl;
        $this->chainId = $chainId;
        if (class_exists(Web3::class)) {
            $this->web3 = new Web3($rpcUrl);
        }
    }
    
    public function generateAddress(string $publicKey): AddressData
    {
        // Remove '0x' prefix if present
        $publicKey = str_replace('0x', '', $publicKey);
        
        // Get the Ethereum address from public key
        $publicKeyBin = hex2bin($publicKey);
        $addressBin = substr(Keccak::hash($publicKeyBin, 256, true), -20);
        $address = '0x' . bin2hex($addressBin);
        
        return new AddressData(
            address: $address,
            publicKey: '0x' . $publicKey,
            chain: 'ethereum',
            metadata: ['chain_id' => $this->chainId]
        );
    }
    
    public function getBalance(string $address): BalanceData
    {
        $balance = null;
        $nonce = null;
        
        $this->web3->eth->getBalance($address, function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \Exception('Failed to get balance: ' . $err->getMessage());
            }
            $balance = $result->toString();
        });
        
        $this->web3->eth->getTransactionCount($address, function ($err, $result) use (&$nonce) {
            if ($err !== null) {
                Log::warning('Failed to get nonce: ' . $err->getMessage());
            } else {
                $nonce = hexdec($result);
            }
        });
        
        return new BalanceData(
            address: $address,
            balance: $balance,
            chain: 'ethereum',
            symbol: 'ETH',
            decimals: 18,
            nonce: $nonce,
            metadata: ['chain_id' => $this->chainId]
        );
    }
    
    public function getTokenBalances(string $address): array
    {
        // This would fetch ERC20 token balances
        // For now, return empty array
        // In production, this would iterate through known tokens or use a service like Alchemy
        return [];
    }
    
    public function estimateGas(TransactionData $transaction): GasEstimate
    {
        $gasLimit = null;
        $gasPrice = null;
        
        // Estimate gas limit
        $txParams = [
            'from' => $transaction->from,
            'to' => $transaction->to,
            'value' => '0x' . dechex($transaction->value),
        ];
        
        if ($transaction->data) {
            $txParams['data'] = $transaction->data;
        }
        
        $this->web3->eth->estimateGas($txParams, function ($err, $result) use (&$gasLimit) {
            if ($err !== null) {
                // Default gas limit if estimation fails
                $gasLimit = '21000';
            } else {
                $gasLimit = hexdec($result);
            }
        });
        
        // Get current gas price
        $this->web3->eth->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err !== null) {
                // Default gas price (20 gwei)
                $gasPrice = '20000000000';
            } else {
                $gasPrice = $result->toString();
            }
        });
        
        // Calculate EIP-1559 gas prices
        $maxPriorityFeePerGas = '2000000000'; // 2 gwei
        $maxFeePerGas = bcadd($gasPrice, $maxPriorityFeePerGas);
        
        $estimatedCost = bcmul($gasLimit, $gasPrice);
        
        return new GasEstimate(
            gasLimit: (string)$gasLimit,
            gasPrice: $gasPrice,
            maxFeePerGas: $maxFeePerGas,
            maxPriorityFeePerGas: $maxPriorityFeePerGas,
            estimatedCost: $estimatedCost,
            chain: 'ethereum',
            metadata: ['chain_id' => $this->chainId]
        );
    }
    
    public function broadcastTransaction(SignedTransaction $transaction): TransactionResult
    {
        $hash = null;
        
        $this->web3->eth->sendRawTransaction(
            $transaction->rawTransaction,
            function ($err, $result) use (&$hash) {
                if ($err !== null) {
                    throw new \Exception('Failed to broadcast transaction: ' . $err->getMessage());
                }
                $hash = $result;
            }
        );
        
        return new TransactionResult(
            hash: $hash,
            status: 'pending',
            metadata: [
                'chain_id' => $this->chainId,
                'submitted_at' => now()->toIso8601String()
            ]
        );
    }
    
    public function getTransaction(string $hash): ?TransactionData
    {
        $transaction = null;
        $receipt = null;
        
        $this->web3->eth->getTransactionByHash($hash, function ($err, $result) use (&$transaction) {
            if ($err !== null || $result === null) {
                return;
            }
            $transaction = $result;
        });
        
        if (!$transaction) {
            return null;
        }
        
        $this->web3->eth->getTransactionReceipt($hash, function ($err, $result) use (&$receipt) {
            if ($err !== null) {
                return;
            }
            $receipt = $result;
        });
        
        $status = 'pending';
        $blockNumber = null;
        
        if ($receipt) {
            $status = hexdec($receipt->status) === 1 ? 'confirmed' : 'failed';
            $blockNumber = hexdec($receipt->blockNumber);
        }
        
        return new TransactionData(
            from: $transaction->from,
            to: $transaction->to,
            value: hexdec($transaction->value),
            chain: 'ethereum',
            data: $transaction->input,
            gasLimit: hexdec($transaction->gas),
            gasPrice: hexdec($transaction->gasPrice),
            nonce: hexdec($transaction->nonce),
            hash: $hash,
            blockNumber: $blockNumber,
            status: $status,
            metadata: ['chain_id' => $this->chainId]
        );
    }
    
    public function getGasPrices(): array
    {
        $gasPrice = null;
        
        $this->web3->eth->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err !== null) {
                throw new \Exception('Failed to get gas price: ' . $err->getMessage());
            }
            $gasPrice = $result->toString();
        });
        
        // Calculate different priority levels
        $slow = bcmul($gasPrice, '0.8');
        $standard = $gasPrice;
        $fast = bcmul($gasPrice, '1.2');
        $instant = bcmul($gasPrice, '1.5');
        
        return [
            'slow' => $slow,
            'standard' => $standard,
            'fast' => $fast,
            'instant' => $instant,
            'eip1559' => [
                'base_fee' => $gasPrice,
                'priority_fees' => [
                    'slow' => '1000000000', // 1 gwei
                    'standard' => '2000000000', // 2 gwei
                    'fast' => '3000000000', // 3 gwei
                    'instant' => '5000000000', // 5 gwei
                ]
            ]
        ];
    }
    
    public function subscribeToEvents(string $address, callable $callback): void
    {
        // In production, this would use WebSocket connection
        // For now, store the subscription
        $this->eventSubscriptions[$address] = $callback;
        
        Log::info("Subscribed to events for address: {$address}");
    }
    
    public function unsubscribeFromEvents(string $address): void
    {
        unset($this->eventSubscriptions[$address]);
        
        Log::info("Unsubscribed from events for address: {$address}");
    }
    
    public function getChainId(): string
    {
        return $this->chainId;
    }
    
    public function isHealthy(): bool
    {
        try {
            $syncing = null;
            
            $this->web3->eth->syncing(function ($err, $result) use (&$syncing) {
                if ($err !== null) {
                    throw new \Exception($err->getMessage());
                }
                $syncing = $result;
            });
            
            // If not syncing (false) or synced, consider healthy
            return $syncing === false || (is_object($syncing) && $syncing->currentBlock === $syncing->highestBlock);
        } catch (\Exception $e) {
            Log::error('Ethereum connector health check failed: ' . $e->getMessage());
            return false;
        }
    }
}