<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Demo Wallet Balance Provider for development and testing.
 *
 * Returns configurable mock balances without making actual RPC calls.
 * NOT for production use.
 */
class DemoWalletBalanceService implements WalletBalanceProviderInterface
{
    /**
     * Token contract addresses by network.
     *
     * @var array<string, array<string, string>>
     */
    private const TOKEN_ADDRESSES = [
        'USDC' => [
            'polygon'  => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'base'     => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
            'arbitrum' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
            'optimism' => '0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85',
            'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ],
        'USDT' => [
            'polygon'  => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
            'ethereum' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'arbitrum' => '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9',
        ],
    ];

    /**
     * Token decimals.
     *
     * @var array<string, int>
     */
    private const TOKEN_DECIMALS = [
        'USDC' => 6,
        'USDT' => 6,
        'WETH' => 18,
        'WBTC' => 8,
    ];

    /**
     * Default balance for demo mode.
     */
    private const DEFAULT_DEMO_BALANCE = '1000.000000';

    /**
     * Cache TTL in seconds.
     */
    private const CACHE_TTL_SECONDS = 30;

    private string $cachePrefix;

    public function __construct()
    {
        $this->cachePrefix = 'demo_wallet_balance:';
    }

    /**
     * Get balance - returns demo balance.
     */
    public function getBalance(
        string $walletAddress,
        string $token,
        SupportedNetwork $network
    ): string {
        $cacheKey = $this->getCacheKey($walletAddress, $token, $network);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (string) $cached;
        }

        // Check configured demo balances
        $configuredBalances = config('relayer.balance_checking.demo_balances', []);
        $balance = $configuredBalances[$walletAddress][$token] ?? self::DEFAULT_DEMO_BALANCE;

        // Cache the balance
        Cache::put($cacheKey, $balance, self::CACHE_TTL_SECONDS);

        Log::debug('Demo balance fetched', [
            'wallet'  => $walletAddress,
            'token'   => $token,
            'network' => $network->value,
            'balance' => $balance,
        ]);

        return $balance;
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasBalance(
        string $walletAddress,
        string $token,
        float $amount,
        SupportedNetwork $network
    ): bool {
        $balance = (float) $this->getBalance($walletAddress, $token, $network);

        $hasSufficient = $balance >= $amount;

        Log::debug('Demo balance check', [
            'wallet'         => $walletAddress,
            'token'          => $token,
            'required'       => $amount,
            'available'      => $balance,
            'has_sufficient' => $hasSufficient,
        ]);

        return $hasSufficient;
    }

    /**
     * Get token contract address.
     */
    public function getTokenAddress(string $token, SupportedNetwork $network): ?string
    {
        return self::TOKEN_ADDRESSES[$token][$network->value] ?? null;
    }

    /**
     * Get token decimals.
     */
    public function getTokenDecimals(string $token): int
    {
        return self::TOKEN_DECIMALS[$token] ?? 18;
    }

    /**
     * Check if token is supported on network.
     */
    public function isTokenSupported(string $token, SupportedNetwork $network): bool
    {
        return isset(self::TOKEN_ADDRESSES[$token][$network->value]);
    }

    /**
     * Invalidate cached balance.
     */
    public function invalidateCache(
        string $walletAddress,
        string $token,
        SupportedNetwork $network
    ): void {
        $cacheKey = $this->getCacheKey($walletAddress, $token, $network);
        Cache::forget($cacheKey);

        Log::debug('Demo balance cache invalidated', [
            'wallet'  => $walletAddress,
            'token'   => $token,
            'network' => $network->value,
        ]);
    }

    /**
     * Get provider name.
     */
    public function getProviderName(): string
    {
        return 'demo';
    }

    /**
     * Set a specific demo balance for testing.
     */
    public function setDemoBalance(
        string $walletAddress,
        string $token,
        SupportedNetwork $network,
        string $balance
    ): void {
        $cacheKey = $this->getCacheKey($walletAddress, $token, $network);
        Cache::put($cacheKey, $balance, self::CACHE_TTL_SECONDS);
    }

    /**
     * Generate cache key.
     */
    private function getCacheKey(
        string $walletAddress,
        string $token,
        SupportedNetwork $network
    ): string {
        return $this->cachePrefix . strtolower($walletAddress) . ':' . $token . ':' . $network->value;
    }
}
