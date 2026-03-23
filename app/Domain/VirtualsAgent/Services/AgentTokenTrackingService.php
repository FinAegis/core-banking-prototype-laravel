<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\VirtualsAgent\DataObjects\AgentTokenBalance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tracks ERC-20 token balances for Virtuals agent wallets.
 *
 * Queries on-chain balances via eth_call using the ERC-20 balanceOf selector
 * and caches results to reduce RPC usage.
 */
class AgentTokenTrackingService
{
    /**
     * ERC-20 balanceOf(address) function selector.
     */
    private const BALANCE_OF_SELECTOR = '0x70a08231';

    /**
     * Cache TTL for balance queries in seconds.
     */
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly EthRpcClient $rpcClient,
    ) {
    }

    /**
     * Get the ERC-20 token balance for a wallet on a specific chain.
     */
    public function getAgentTokenBalance(
        string $tokenAddress,
        string $walletAddress,
        string $chain = 'base',
    ): AgentTokenBalance {
        $cacheKey = "virtuals_agent:token_balance:{$chain}:{$tokenAddress}:{$walletAddress}";

        /** @var AgentTokenBalance|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached instanceof AgentTokenBalance) {
            return $cached;
        }

        $network = SupportedNetwork::tryFrom($chain);

        if ($network === null) {
            Log::warning('Unsupported network for token tracking', ['chain' => $chain]);

            return new AgentTokenBalance(
                tokenAddress: $tokenAddress,
                tokenSymbol: 'UNKNOWN',
                balance: '0',
                chain: $chain,
            );
        }

        try {
            // Pad the wallet address to 32 bytes for the ABI encoding
            $paddedAddress = str_pad(
                strtolower(ltrim($walletAddress, '0x')),
                64,
                '0',
                STR_PAD_LEFT,
            );

            $callData = self::BALANCE_OF_SELECTOR . $paddedAddress;

            $result = $this->rpcClient->ethCall($network, [
                'to'   => $tokenAddress,
                'data' => $callData,
            ]);

            // Parse the hex result to a decimal string
            $hexBalance = ltrim($result, '0x');
            $balance = $hexBalance !== '' ? gmp_strval(gmp_init($hexBalance, 16)) : '0';

            // Look up token symbol from tracked tokens config
            $trackedTokens = $this->getTrackedTokens();
            $tokenSymbol = 'UNKNOWN';
            foreach ($trackedTokens as $token) {
                if (strtolower($token['address'] ?? '') === strtolower($tokenAddress)) {
                    $tokenSymbol = $token['symbol'] ?? 'UNKNOWN';
                    break;
                }
            }

            $tokenBalance = new AgentTokenBalance(
                tokenAddress: $tokenAddress,
                tokenSymbol: $tokenSymbol,
                balance: $balance,
                chain: $chain,
            );

            Cache::put($cacheKey, $tokenBalance, self::CACHE_TTL);

            return $tokenBalance;
        } catch (Throwable $e) {
            Log::error('Failed to fetch token balance', [
                'token_address'  => $tokenAddress,
                'wallet_address' => $walletAddress,
                'chain'          => $chain,
                'error'          => $e->getMessage(),
            ]);

            return new AgentTokenBalance(
                tokenAddress: $tokenAddress,
                tokenSymbol: 'UNKNOWN',
                balance: '0',
                chain: $chain,
            );
        }
    }

    /**
     * Returns the list of known Virtuals agent tokens from configuration.
     *
     * @return array<int, array{address: string, symbol: string, decimals: int}>
     */
    public function getTrackedTokens(): array
    {
        /** @var array<int, array{address: string, symbol: string, decimals: int}> $tokens */
        $tokens = config('virtuals-agent.tracked_tokens', []);

        if ($tokens === []) {
            Log::warning('No tracked tokens configured — set virtuals-agent.tracked_tokens in config');
        }

        return $tokens;
    }

    /**
     * Get token balances for all tracked tokens for a given wallet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPortfolio(string $walletAddress, string $chain = 'base'): array
    {
        $trackedTokens = $this->getTrackedTokens();
        $portfolio = [];

        foreach ($trackedTokens as $token) {
            $balance = $this->getAgentTokenBalance(
                tokenAddress: $token['address'],
                walletAddress: $walletAddress,
                chain: $chain,
            );

            $portfolio[] = $balance->toArray();
        }

        return $portfolio;
    }
}
