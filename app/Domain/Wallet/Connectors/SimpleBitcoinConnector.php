<?php

namespace App\Domain\Wallet\Connectors;

use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\ValueObjects\AddressData;
use App\Domain\Wallet\ValueObjects\BalanceData;
use App\Domain\Wallet\ValueObjects\GasEstimate;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Domain\Wallet\ValueObjects\TransactionResult;
use Exception;
use Illuminate\Support\Facades\Http;

class SimpleBitcoinConnector implements BlockchainConnector
{
    private string $network;

    private string $apiUrl;

    private ?string $apiKey;

    public function __construct(array $config = [])
    {
        $this->network = $config['network'] ?? 'mainnet';
        $this->apiUrl = $config['api_url'] ?? 'https://api.blockcypher.com/v1/btc/' . $this->network;
        $this->apiKey = $config['api_key'] ?? null;
    }

    public function generateAddress(string $publicKey): AddressData
    {
        // For Bitcoin, we'll use a simplified P2PKH address generation
        // In production, use proper Bitcoin libraries
        $hash = hash('sha256', hex2bin($publicKey));
        $hash = hash('ripemd160', hex2bin($hash));

        // Add network byte (0x00 for mainnet, 0x6f for testnet)
        $networkByte = $this->network === 'mainnet' ? '00' : '6f';
        $hash = $networkByte . $hash;

        // Add checksum
        $checksum = substr(hash('sha256', hash('sha256', hex2bin($hash), true)), 0, 8);
        $hash .= $checksum;

        // Base58 encode
        $address = $this->base58Encode(hex2bin($hash));

        return new AddressData(
            address: $address,
            publicKey: $publicKey,
            chain: 'bitcoin',
            metadata: [
                'type'    => 'P2PKH',
                'network' => $this->network,
            ]
        );
    }

    public function getBalance(string $address): BalanceData
    {
        $response = Http::get("{$this->apiUrl}/addrs/{$address}/balance");

        if (! $response->successful()) {
            throw new Exception('Failed to fetch balance');
        }

        $data = $response->json();
        $balance = (string) ($data['balance'] ?? 0);

        return new BalanceData(
            address: $address,
            balance: $balance,
            chain: 'bitcoin',
            metadata: [
                'unconfirmed_balance' => (string) ($data['unconfirmed_balance'] ?? 0),
                'final_balance'       => (string) ($data['final_balance'] ?? 0),
            ]
        );
    }

    public function getTokenBalances(string $address): array
    {
        // Bitcoin doesn't have tokens
        return [];
    }

    public function estimateGas(TransactionData $transaction): GasEstimate
    {
        // Bitcoin uses fees, not gas. We'll estimate based on transaction size
        $feeRate = $this->getEstimatedFeeRate(); // satoshis per byte
        $txSize = 250; // Estimated transaction size in bytes
        $fee = (string) ($feeRate * $txSize);

        return new GasEstimate(
            gasLimit: '0', // Not applicable for Bitcoin
            gasPrice: (string) $feeRate,
            maxFeePerGas: '0',
            maxPriorityFeePerGas: '0',
            estimatedCost: $fee,
            chain: 'bitcoin',
            metadata: [
                'fee_rate'       => $feeRate,
                'estimated_size' => $txSize,
            ]
        );
    }

    public function broadcastTransaction(SignedTransaction $transaction): TransactionResult
    {
        $response = Http::post(
            "{$this->apiUrl}/txs/push",
            [
                'tx' => $transaction->rawTransaction,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('Failed to broadcast transaction: ' . $response->body());
        }

        $data = $response->json();

        return new TransactionResult(
            hash: $data['tx']['hash'],
            status: 'pending',
            metadata: [
                'network'      => $this->network,
                'submitted_at' => now()->toIso8601String(),
            ]
        );
    }

    public function getTransaction(string $hash): ?TransactionData
    {
        $response = Http::get("{$this->apiUrl}/txs/{$hash}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        $status = 'pending';
        if (($data['confirmations'] ?? 0) > 0) {
            $status = 'confirmed';
        }

        return new TransactionData(
            from: $data['inputs'][0]['addresses'][0] ?? 'unknown',
            to: $data['outputs'][0]['addresses'][0] ?? 'unknown',
            value: (int) ($data['outputs'][0]['value'] ?? 0),
            chain: 'bitcoin',
            data: '',
            gasLimit: 0,
            gasPrice: 0,
            nonce: 0,
            hash: $hash,
            blockNumber: $data['block_height'] ?? null,
            status: $status,
            metadata: [
                'confirmations' => $data['confirmations'] ?? 0,
                'fee'           => $data['fees'] ?? 0,
            ]
        );
    }

    public function getGasPrices(): array
    {
        // Return fee estimates in satoshis per byte
        return [
            'slow'     => $this->getEstimatedFeeRate(0.9),
            'standard' => $this->getEstimatedFeeRate(0.5),
            'fast'     => $this->getEstimatedFeeRate(0.1),
        ];
    }

    public function subscribeToEvents(string $address, callable $callback): void
    {
        // Bitcoin doesn't have native event subscriptions
        // In production, you'd use webhooks or polling
    }

    public function unsubscribeFromEvents(string $address): void
    {
        // No-op for Bitcoin
    }

    public function getChainId(): string
    {
        return $this->network === 'mainnet' ? 'bitcoin' : 'bitcoin-testnet';
    }

    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->apiUrl);

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getTransactionStatus(string $hash): TransactionResult
    {
        $response = Http::get("{$this->apiUrl}/txs/{$hash}");

        if (! $response->successful()) {
            throw new Exception('Failed to fetch transaction status');
        }

        $data = $response->json();

        $status = 'pending';
        if (($data['confirmations'] ?? 0) >= 6) {
            $status = 'confirmed';
        } elseif (($data['confirmations'] ?? 0) > 0) {
            $status = 'confirming';
        }

        return new TransactionResult(
            hash: $hash,
            status: $status,
            metadata: [
                'confirmations' => $data['confirmations'] ?? 0,
                'block_height'  => $data['block_height'] ?? null,
                'fee'           => $data['fees'] ?? 0,
                'time'          => $data['received'] ?? null,
            ]
        );
    }

    public function validateAddress(string $address): bool
    {
        // Basic Bitcoin address validation
        // Check if it's a valid P2PKH, P2SH, or Bech32 address

        // P2PKH addresses start with 1 (mainnet) or m/n (testnet)
        // P2SH addresses start with 3 (mainnet) or 2 (testnet)
        // Bech32 addresses start with bc1 (mainnet) or tb1 (testnet)

        $patterns = [
            'mainnet' => [
                '/^1[a-km-zA-HJ-NP-Z1-9]{25,34}$/', // P2PKH
                '/^3[a-km-zA-HJ-NP-Z1-9]{25,34}$/', // P2SH
                '/^bc1[a-z0-9]{39,59}$/', // Bech32
            ],
            'testnet' => [
                '/^[mn][a-km-zA-HJ-NP-Z1-9]{25,34}$/', // P2PKH
                '/^2[a-km-zA-HJ-NP-Z1-9]{25,34}$/', // P2SH
                '/^tb1[a-z0-9]{39,59}$/', // Bech32
            ],
        ];

        $networkPatterns = $patterns[$this->network] ?? $patterns['mainnet'];

        foreach ($networkPatterns as $pattern) {
            if (preg_match($pattern, $address)) {
                return true;
            }
        }

        return false;
    }

    private function getEstimatedFeeRate(float $priority = 0.5): int
    {
        // In production, fetch from API
        // For now, return conservative estimates (satoshis per byte)
        if ($priority <= 0.1) {
            return 50; // Fast
        } elseif ($priority <= 0.5) {
            return 20; // Standard
        } else {
            return 5; // Slow
        }
    }

    private function base58Encode(string $data): string
    {
        // Simplified base58 encoding
        // In production, use a proper implementation
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $encoded = '';

        $num = gmp_init(bin2hex($data), 16);
        $base = gmp_init(58);

        while (gmp_cmp($num, 0) > 0) {
            $remainder = gmp_mod($num, $base);
            $num = gmp_div($num, $base);
            $encoded = $alphabet[gmp_intval($remainder)] . $encoded;
        }

        // Add leading 1s for each leading zero byte
        for ($i = 0; $i < strlen($data); $i++) {
            if ($data[$i] !== "\x00") {
                break;
            }
            $encoded = '1' . $encoded;
        }

        return $encoded;
    }
}
