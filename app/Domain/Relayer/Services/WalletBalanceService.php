<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Production Wallet Balance Service.
 *
 * Queries ERC-20 token balances via RPC providers (Alchemy, Infura, or custom nodes).
 * Implements caching to reduce RPC calls.
 *
 * Production Configuration:
 * - BALANCE_PROVIDER: 'alchemy', 'infura', or 'custom'
 * - ALCHEMY_API_KEY: Alchemy API key
 * - INFURA_PROJECT_ID: Infura project ID
 * - CUSTOM_RPC_URL_{NETWORK}: Custom RPC URLs per network
 */
class WalletBalanceService implements WalletBalanceProviderInterface
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
     * ERC-20 balanceOf function selector.
     */
    private const BALANCE_OF_SELECTOR = '0x70a08231';

    private string $provider;

    private int $cacheTtl;

    private string $cachePrefix;

    public function __construct()
    {
        $this->provider = (string) config('relayer.balance_checking.provider', 'alchemy');
        $this->cacheTtl = (int) config('relayer.balance_checking.cache_ttl_seconds', 30);
        $this->cachePrefix = 'wallet_balance:';
    }

    /**
     * Get balance by querying ERC-20 contract.
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

        $tokenAddress = $this->getTokenAddress($token, $network);
        if ($tokenAddress === null) {
            throw new RuntimeException("Token {$token} not supported on {$network->value}");
        }

        $balance = $this->queryBalanceFromRpc($walletAddress, $tokenAddress, $network);

        // Convert from wei to token decimals
        $decimals = $this->getTokenDecimals($token);
        $formattedBalance = $this->formatBalance($balance, $decimals);

        // Cache the balance
        Cache::put($cacheKey, $formattedBalance, $this->cacheTtl);

        Log::debug('Balance fetched from RPC', [
            'wallet'   => $walletAddress,
            'token'    => $token,
            'network'  => $network->value,
            'balance'  => $formattedBalance,
            'provider' => $this->provider,
        ]);

        return $formattedBalance;
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

        return $balance >= $amount;
    }

    /**
     * Get token contract address.
     */
    public function getTokenAddress(string $token, SupportedNetwork $network): ?string
    {
        // Check config first, then fallback to constants
        $configured = config("relayer.balance_checking.tokens.{$token}.{$network->value}");
        if ($configured !== null) {
            return (string) $configured;
        }

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
        return $this->getTokenAddress($token, $network) !== null;
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
    }

    /**
     * Get provider name.
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * Query balance from RPC provider.
     */
    private function queryBalanceFromRpc(
        string $walletAddress,
        string $tokenAddress,
        SupportedNetwork $network
    ): string {
        $rpcUrl = $this->getRpcUrl($network);

        // Build eth_call data: balanceOf(address)
        // Function selector (4 bytes) + address padded to 32 bytes
        $paddedAddress = str_pad(substr(strtolower($walletAddress), 2), 64, '0', STR_PAD_LEFT);
        $callData = self::BALANCE_OF_SELECTOR . $paddedAddress;

        $payload = [
            'jsonrpc' => '2.0',
            'method'  => 'eth_call',
            'params'  => [
                [
                    'to'   => $tokenAddress,
                    'data' => $callData,
                ],
                'latest',
            ],
            'id' => 1,
        ];

        try {
            $response = Http::timeout(10)
                ->post($rpcUrl, $payload)
                ->throw()
                ->json();

            if (isset($response['error'])) {
                throw new RuntimeException('RPC error: ' . ($response['error']['message'] ?? 'Unknown'));
            }

            $result = $response['result'] ?? '0x0';

            // Remove 0x prefix and convert from hex
            return $this->hexToDecimal($result);
        } catch (Throwable $e) {
            Log::error('Balance RPC call failed', [
                'wallet'  => $walletAddress,
                'token'   => $tokenAddress,
                'network' => $network->value,
                'error'   => $e->getMessage(),
            ]);
            throw new RuntimeException('Failed to fetch balance: ' . $e->getMessage());
        }
    }

    /**
     * Get RPC URL for a network.
     */
    private function getRpcUrl(SupportedNetwork $network): string
    {
        return match ($this->provider) {
            'alchemy' => $this->getAlchemyUrl($network),
            'infura'  => $this->getInfuraUrl($network),
            'custom'  => $this->getCustomUrl($network),
            default   => throw new RuntimeException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Get Alchemy RPC URL.
     */
    private function getAlchemyUrl(SupportedNetwork $network): string
    {
        $apiKey = config('relayer.balance_checking.alchemy_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('ALCHEMY_API_KEY not configured');
        }

        $subdomain = match ($network) {
            SupportedNetwork::POLYGON  => 'polygon-mainnet',
            SupportedNetwork::BASE     => 'base-mainnet',
            SupportedNetwork::ARBITRUM => 'arb-mainnet',
            SupportedNetwork::OPTIMISM => 'opt-mainnet',
            SupportedNetwork::ETHEREUM => 'eth-mainnet',
        };

        return "https://{$subdomain}.g.alchemy.com/v2/{$apiKey}";
    }

    /**
     * Get Infura RPC URL.
     */
    private function getInfuraUrl(SupportedNetwork $network): string
    {
        $projectId = config('relayer.balance_checking.infura_project_id');
        if (empty($projectId)) {
            throw new RuntimeException('INFURA_PROJECT_ID not configured');
        }

        $subdomain = match ($network) {
            SupportedNetwork::POLYGON  => 'polygon-mainnet',
            SupportedNetwork::ARBITRUM => 'arbitrum-mainnet',
            SupportedNetwork::OPTIMISM => 'optimism-mainnet',
            SupportedNetwork::ETHEREUM => 'mainnet',
            SupportedNetwork::BASE     => throw new RuntimeException('Base not supported by Infura'),
        };

        return "https://{$subdomain}.infura.io/v3/{$projectId}";
    }

    /**
     * Get custom RPC URL.
     */
    private function getCustomUrl(SupportedNetwork $network): string
    {
        $url = config("relayer.balance_checking.custom_rpc.{$network->value}");
        if (empty($url)) {
            throw new RuntimeException("Custom RPC URL not configured for {$network->value}");
        }

        return (string) $url;
    }

    /**
     * Convert hex string to decimal string.
     */
    private function hexToDecimal(string $hex): string
    {
        // Remove 0x prefix
        $hex = ltrim($hex, '0x');

        // Handle empty or zero
        if ($hex === '' || $hex === '0') {
            return '0';
        }

        // Use bcmath for large numbers
        $decimal = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $decimal = bcmul($decimal, '16');
            $decimal = bcadd($decimal, (string) hexdec($hex[$i]));
        }

        return $decimal;
    }

    /**
     * Format balance from wei to decimal.
     */
    private function formatBalance(string $weiBalance, int $decimals): string
    {
        if ($weiBalance === '0') {
            return '0.' . str_repeat('0', $decimals);
        }

        $divisor = bcpow('10', (string) $decimals);
        $balance = bcdiv($weiBalance, $divisor, $decimals);

        return $balance;
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
