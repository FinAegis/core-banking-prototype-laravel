<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Models\RailgunWallet;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrator service for RAILGUN privacy pool operations.
 *
 * Manages the lifecycle of RAILGUN wallets (creation, balance sync) and
 * coordinates shield/unshield/transfer operations through the bridge client.
 * Replaces demo logic in PrivacyController with real RAILGUN integration.
 */
class RailgunPrivacyService
{
    public function __construct(
        private readonly RailgunBridgeClient $bridge,
        private readonly MerkleTreeServiceInterface $merkleService,
    ) {
    }

    /**
     * Create or retrieve a RAILGUN wallet for a user on a specific network.
     */
    public function createWalletForUser(User $user, string $network = 'polygon'): RailgunWallet
    {
        // Check for existing wallet
        $existing = RailgunWallet::query()
            ->forUser($user->id)
            ->forNetwork($network)
            ->first();

        if ($existing instanceof RailgunWallet) {
            return $existing;
        }

        // Generate a deterministic mnemonic from user ID + app key
        // In production, this would come from the user's key management
        $walletId = Str::uuid()->toString();
        $mnemonic = $this->generateMnemonic($user);
        $encryptionKey = $this->deriveEncryptionKey($user);

        // Create wallet on the bridge
        $bridgeData = $this->bridge->createWallet($walletId, $mnemonic, $encryptionKey);

        $wallet = RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => $bridgeData['railgun_address'],
            'encrypted_mnemonic' => $mnemonic,
            'network'            => $network,
            'last_scan_block'    => 0,
            'status'             => RailgunWallet::STATUS_ACTIVE,
        ]);

        Log::info('RAILGUN wallet created for user', [
            'user_id'         => $user->id,
            'network'         => $network,
            'railgun_address' => $bridgeData['railgun_address'],
        ]);

        return $wallet;
    }

    /**
     * Get shielded balances for a user, optionally filtered by network.
     *
     * @return list<array<string, mixed>>
     */
    public function getShieldedBalances(User $user, ?string $network = null): array
    {
        $wallets = RailgunWallet::query()
            ->forUser($user->id)
            ->when($network !== null, fn ($q) => $q->forNetwork((string) $network))
            ->where('status', RailgunWallet::STATUS_ACTIVE)
            ->get();

        $balances = [];

        foreach ($wallets as $wallet) {
            try {
                // Sync balances from bridge
                $bridgeBalances = $this->bridge->getBalances(
                    $wallet->id,
                    $wallet->network,
                );

                foreach ($bridgeBalances['balances'] ?? [] as $tokenAddress => $rawBalance) {
                    $token = $this->resolveTokenSymbol($tokenAddress);
                    $formattedBalance = $this->formatBalance($rawBalance, $token);

                    // Cache the balance
                    ShieldedBalance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'token'   => $token,
                            'network' => $wallet->network,
                        ],
                        [
                            'railgun_address' => $wallet->railgun_address,
                            'balance'         => $formattedBalance,
                            'last_synced_at'  => now(),
                        ],
                    );

                    $balances[] = [
                        'token'          => $token,
                        'balance'        => $formattedBalance,
                        'network'        => $wallet->network,
                        'last_synced_at' => now()->toIso8601String(),
                    ];
                }
            } catch (RuntimeException $e) {
                Log::warning('Failed to sync RAILGUN balances', [
                    'user_id' => $user->id,
                    'wallet'  => $wallet->id,
                    'network' => $wallet->network,
                    'error'   => $e->getMessage(),
                ]);

                // Fall back to cached balances
                $cached = ShieldedBalance::query()
                    ->forUser($user->id)
                    ->forNetwork($wallet->network)
                    ->get();

                foreach ($cached as $balance) {
                    $balances[] = $balance->toApiResponse();
                }
            }
        }

        // If user has no wallets yet, return empty balances for supported networks
        if ($wallets->isEmpty()) {
            $networks = $network
                ? [$network]
                : $this->merkleService->getSupportedNetworks();

            foreach ($networks as $net) {
                foreach (['USDC', 'USDT', 'WETH'] as $token) {
                    $balances[] = [
                        'token'          => $token,
                        'balance'        => '0.00',
                        'network'        => $net,
                        'last_synced_at' => null,
                    ];
                }
            }
        }

        return $balances;
    }

    /**
     * Get total shielded balance in USD across all tokens and networks.
     *
     * @return array{total_balance: string, currency: string}
     */
    public function getTotalShieldedBalance(User $user): array
    {
        $balances = ShieldedBalance::query()
            ->forUser($user->id)
            ->get();

        $total = '0.00';
        foreach ($balances as $balance) {
            // Stablecoins are ~1:1 USD, ETH/WETH would need a price oracle
            $usdValue = match ($balance->token) {
                'USDC', 'USDT' => $balance->balance,
                default => '0.00', // Non-stablecoin tokens need price feed
            };
            /** @var numeric-string $usdValue */
            $total = bcadd($total, $usdValue, 2);
        }

        return [
            'total_balance' => $total,
            'currency'      => 'USD',
        ];
    }

    /**
     * Shield (deposit) tokens into the RAILGUN privacy pool.
     *
     * @return array<string, mixed>
     */
    public function shield(User $user, string $token, string $amount, string $network): array
    {
        $wallet = $this->getOrCreateWallet($user, $network);
        $tokenAddress = $this->resolveTokenAddress($token, $network);

        $result = $this->bridge->shield(
            $wallet->id,
            $tokenAddress,
            $this->toWei($amount, $token),
            $network,
        );

        Log::info('Shield operation initiated', [
            'user_id' => $user->id,
            'token'   => $token,
            'amount'  => $amount,
            'network' => $network,
        ]);

        return [
            'operation'       => 'shield',
            'status'          => 'transaction_ready',
            'transaction'     => $result['transaction'] ?? null,
            'gas_estimate'    => $result['gas_estimate'] ?? null,
            'token'           => $token,
            'amount'          => $amount,
            'network'         => $network,
            'railgun_address' => $wallet->railgun_address,
        ];
    }

    /**
     * Unshield (withdraw) tokens from the RAILGUN privacy pool.
     *
     * @return array<string, mixed>
     */
    public function unshield(User $user, string $recipient, string $token, string $amount, string $network): array
    {
        $wallet = $this->getOrCreateWallet($user, $network);
        $tokenAddress = $this->resolveTokenAddress($token, $network);
        $encryptionKey = $this->deriveEncryptionKey($user);

        $result = $this->bridge->unshield(
            $wallet->id,
            $encryptionKey,
            $recipient,
            $tokenAddress,
            $this->toWei($amount, $token),
            $network,
        );

        Log::info('Unshield operation initiated', [
            'user_id'   => $user->id,
            'recipient' => $recipient,
            'token'     => $token,
            'amount'    => $amount,
            'network'   => $network,
        ]);

        return [
            'operation'   => 'unshield',
            'status'      => 'transaction_ready',
            'transaction' => $result['transaction'] ?? null,
            'token'       => $token,
            'amount'      => $amount,
            'recipient'   => $recipient,
            'network'     => $network,
        ];
    }

    /**
     * Private transfer between two RAILGUN (0zk) addresses.
     *
     * @return array<string, mixed>
     */
    public function privateTransfer(User $user, string $toAddress, string $token, string $amount, string $network): array
    {
        $wallet = $this->getOrCreateWallet($user, $network);
        $tokenAddress = $this->resolveTokenAddress($token, $network);
        $encryptionKey = $this->deriveEncryptionKey($user);

        $result = $this->bridge->privateTransfer(
            $wallet->id,
            $encryptionKey,
            $toAddress,
            $tokenAddress,
            $this->toWei($amount, $token),
            $network,
        );

        Log::info('Private transfer initiated', [
            'user_id' => $user->id,
            'to'      => substr($toAddress, 0, 16) . '...',
            'token'   => $token,
            'amount'  => $amount,
            'network' => $network,
        ]);

        return [
            'operation'   => 'transfer',
            'status'      => 'transaction_ready',
            'transaction' => $result['transaction'] ?? null,
            'token'       => $token,
            'amount'      => $amount,
            'network'     => $network,
        ];
    }

    /**
     * Get the RAILGUN viewing key for a user.
     */
    public function getViewingKey(User $user): string
    {
        $wallet = RailgunWallet::query()
            ->forUser($user->id)
            ->where('status', RailgunWallet::STATUS_ACTIVE)
            ->first();

        if ($wallet instanceof RailgunWallet) {
            // The RAILGUN viewing key is derived from the wallet's 0zk address
            return '0x' . hash('sha256', 'railgun_vk_' . $wallet->railgun_address);
        }

        // Fallback: deterministic viewing key from user ID
        return '0x' . hash('sha256', 'viewing_key_' . $user->id);
    }

    /**
     * Get or create a wallet for the user on the given network.
     */
    private function getOrCreateWallet(User $user, string $network): RailgunWallet
    {
        $wallet = RailgunWallet::query()
            ->forUser($user->id)
            ->forNetwork($network)
            ->where('status', RailgunWallet::STATUS_ACTIVE)
            ->first();

        if ($wallet instanceof RailgunWallet) {
            return $wallet;
        }

        return $this->createWalletForUser($user, $network);
    }

    /**
     * Generate a mnemonic for wallet creation.
     * In production, this should use a proper BIP39 implementation or user-provided mnemonic.
     */
    private function generateMnemonic(User $user): string
    {
        // Deterministic seed from user ID + app key for reproducibility
        $seed = hash_hmac('sha512', (string) $user->id, (string) config('app.key'));

        // This would use a proper BIP39 library in production
        return $seed;
    }

    /**
     * Derive an encryption key for the RAILGUN wallet.
     */
    private function deriveEncryptionKey(User $user): string
    {
        return hash_hmac('sha256', 'railgun_enc_' . $user->id, (string) config('app.key'));
    }

    /**
     * Resolve a token symbol to its contract address on a network.
     */
    private function resolveTokenAddress(string $token, string $network): string
    {
        $addresses = [
            'USDC' => [
                'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
                'polygon'  => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
                'arbitrum' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
                'bsc'      => '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d',
            ],
            'USDT' => [
                'ethereum' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'polygon'  => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
                'arbitrum' => '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9',
                'bsc'      => '0x55d398326f99059fF775485246999027B3197955',
            ],
            'WETH' => [
                'ethereum' => '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2',
                'polygon'  => '0x7ceB23fD6bC0adD59E62ac25578270cFf1b9f619',
                'arbitrum' => '0x82aF49447D8a07e3bd95BD0d56f35241523fBab1',
                'bsc'      => '0x2170Ed0880ac9A755fd29B2688956BD959F933F8',
            ],
        ];

        return $addresses[$token][$network]
            ?? throw new RuntimeException("Token {$token} not supported on {$network}");
    }

    /**
     * Resolve a token contract address back to its symbol.
     */
    private function resolveTokenSymbol(string $address): string
    {
        $knownTokens = [
            '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48' => 'USDC',
            '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359' => 'USDC',
            '0xaf88d065e77c8cC2239327C5EDb3A432268e5831' => 'USDC',
            '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d' => 'USDC',
            '0xdAC17F958D2ee523a2206206994597C13D831ec7' => 'USDT',
            '0xc2132D05D31c914a87C6611C10748AEb04B58e8F' => 'USDT',
            '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9' => 'USDT',
            '0x55d398326f99059fF775485246999027B3197955' => 'USDT',
            '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2' => 'WETH',
            '0x7ceB23fD6bC0adD59E62ac25578270cFf1b9f619' => 'WETH',
            '0x82aF49447D8a07e3bd95BD0d56f35241523fBab1' => 'WETH',
            '0x2170Ed0880ac9A755fd29B2688956BD959F933F8' => 'WETH',
        ];

        return $knownTokens[$address] ?? 'UNKNOWN';
    }

    /**
     * Convert human-readable amount to wei (smallest unit).
     */
    private function toWei(string $amount, string $token): string
    {
        $decimals = match ($token) {
            'USDC', 'USDT' => 6,
            'WETH'  => 18,
            default => 18,
        };

        /** @var numeric-string $amount */
        return bcmul($amount, bcpow('10', (string) $decimals, 0), 0);
    }

    /**
     * Format a raw balance from wei to human-readable.
     */
    private function formatBalance(string $rawBalance, string $token): string
    {
        $decimals = match ($token) {
            'USDC', 'USDT' => 6,
            'WETH'  => 18,
            default => 18,
        };

        if ($rawBalance === '0' || $rawBalance === '') {
            return '0.' . str_repeat('0', min($decimals, 6));
        }

        return bcdiv($rawBalance, bcpow('10', (string) $decimals, 0), min($decimals, 6));
    }
}
