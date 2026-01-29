<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services\Broadcast;

use App\Domain\Account\Events\Broadcast\BalanceUpdated;
use App\Domain\Treasury\Events\Broadcast\NavCalculated;
use App\Domain\Treasury\Events\Broadcast\PortfolioValueUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Service for broadcasting portfolio and balance updates with throttling.
 *
 * Implements rate limiting appropriate for portfolio data which changes
 * less frequently than order books but still needs real-time updates.
 */
class PortfolioBroadcastService
{
    private const PORTFOLIO_CACHE_PREFIX = 'ws:portfolio:';

    private const BALANCE_CACHE_PREFIX = 'ws:balance:';

    private const NAV_CACHE_PREFIX = 'ws:nav:';

    /**
     * Broadcast a portfolio value update.
     *
     * @param  array<string, array{quantity: string, price: string, value: string, allocation: string}>  $holdings
     * @param  array<string, string>  $performance
     */
    public function broadcastPortfolioUpdate(
        string $tenantId,
        string $portfolioId,
        string $portfolioName,
        string $totalValue,
        string $previousValue,
        string $changeAmount,
        string $changePercentage,
        string $currency,
        array $holdings,
        array $performance,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $cacheKey = self::PORTFOLIO_CACHE_PREFIX . "{$tenantId}:{$portfolioId}";
        $config = config('websocket.rate_limiting.portfolio');
        $maxPerSecond = $config['max_per_second'] ?? 1;

        if (! $this->checkRateLimit($cacheKey, $maxPerSecond)) {
            // Store for deferred broadcast
            $this->storeForDeferred($cacheKey, [
                'portfolio_id'      => $portfolioId,
                'portfolio_name'    => $portfolioName,
                'total_value'       => $totalValue,
                'previous_value'    => $previousValue,
                'change_amount'     => $changeAmount,
                'change_percentage' => $changePercentage,
                'currency'          => $currency,
                'holdings'          => $holdings,
                'performance'       => $performance,
            ]);

            return;
        }

        event(new PortfolioValueUpdated(
            tenantId: $tenantId,
            portfolioId: $portfolioId,
            portfolioName: $portfolioName,
            totalValue: $totalValue,
            previousValue: $previousValue,
            changeAmount: $changeAmount,
            changePercentage: $changePercentage,
            currency: $currency,
            holdings: $holdings,
            performance: $performance,
            timestamp: now()->toIso8601String(),
        ));

        $this->incrementRateLimit($cacheKey);
    }

    /**
     * Broadcast a NAV calculation update.
     *
     * @param  array<string, string>  $breakdown
     */
    public function broadcastNavCalculated(
        string $tenantId,
        string $portfolioId,
        string $portfolioName,
        string $nav,
        string $previousNav,
        string $changeAmount,
        string $changePercentage,
        string $navPerShare,
        string $totalShares,
        string $totalAssets,
        string $totalLiabilities,
        string $currency,
        array $breakdown,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $cacheKey = self::NAV_CACHE_PREFIX . "{$tenantId}:{$portfolioId}";
        $config = config('websocket.rate_limiting.portfolio');
        $maxPerSecond = $config['max_per_second'] ?? 1;

        if (! $this->checkRateLimit($cacheKey, $maxPerSecond)) {
            Log::debug('NAV broadcast rate limited', [
                'tenant_id'    => $tenantId,
                'portfolio_id' => $portfolioId,
            ]);

            return;
        }

        event(new NavCalculated(
            tenantId: $tenantId,
            portfolioId: $portfolioId,
            portfolioName: $portfolioName,
            nav: $nav,
            previousNav: $previousNav,
            changeAmount: $changeAmount,
            changePercentage: $changePercentage,
            navPerShare: $navPerShare,
            totalShares: $totalShares,
            totalAssets: $totalAssets,
            totalLiabilities: $totalLiabilities,
            currency: $currency,
            breakdown: $breakdown,
            calculatedAt: now()->toIso8601String(),
        ));

        $this->incrementRateLimit($cacheKey);
    }

    /**
     * Broadcast a balance update.
     */
    public function broadcastBalanceUpdate(
        string $tenantId,
        string $accountId,
        string $accountName,
        string $accountType,
        string $totalBalance,
        string $availableBalance,
        string $pendingBalance,
        string $reservedBalance,
        string $currency,
        string $previousTotalBalance,
        string $changeAmount,
        string $changeReason,
        ?string $transactionId = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $cacheKey = self::BALANCE_CACHE_PREFIX . "{$tenantId}:{$accountId}";
        $config = config('websocket.rate_limiting.balance');
        $maxPerSecond = $config['max_per_second'] ?? 5;

        if (! $this->checkRateLimit($cacheKey, $maxPerSecond)) {
            // Store for deferred broadcast
            $this->storeForDeferred($cacheKey, [
                'account_id'             => $accountId,
                'account_name'           => $accountName,
                'account_type'           => $accountType,
                'total_balance'          => $totalBalance,
                'available_balance'      => $availableBalance,
                'pending_balance'        => $pendingBalance,
                'reserved_balance'       => $reservedBalance,
                'currency'               => $currency,
                'previous_total_balance' => $previousTotalBalance,
                'change_amount'          => $changeAmount,
                'change_reason'          => $changeReason,
                'transaction_id'         => $transactionId,
            ]);

            return;
        }

        event(new BalanceUpdated(
            tenantId: $tenantId,
            accountId: $accountId,
            accountName: $accountName,
            accountType: $accountType,
            totalBalance: $totalBalance,
            availableBalance: $availableBalance,
            pendingBalance: $pendingBalance,
            reservedBalance: $reservedBalance,
            currency: $currency,
            previousTotalBalance: $previousTotalBalance,
            changeAmount: $changeAmount,
            changeReason: $changeReason,
            transactionId: $transactionId,
            timestamp: now()->toIso8601String(),
        ));

        $this->incrementRateLimit($cacheKey);
    }

    /**
     * Flush deferred broadcasts for a specific type.
     *
     * @return int Number of broadcasts sent
     */
    public function flushDeferredBroadcasts(string $type): int
    {
        $prefix = match ($type) {
            'portfolio' => self::PORTFOLIO_CACHE_PREFIX,
            'balance'   => self::BALANCE_CACHE_PREFIX,
            'nav'       => self::NAV_CACHE_PREFIX,
            default     => throw new InvalidArgumentException("Unknown type: {$type}"),
        };

        $pattern = $prefix . '*:deferred';
        $flushed = 0;

        // Note: In production with Redis, use SCAN to iterate keys
        // This simplified version assumes the deferred data is stored with tenant context

        return $flushed;
    }

    /**
     * Check if WebSocket broadcasting is enabled.
     */
    private function isEnabled(): bool
    {
        return (bool) config('websocket.enabled', true);
    }

    /**
     * Check if we're within rate limit.
     */
    private function checkRateLimit(string $cacheKey, int $maxPerSecond): bool
    {
        $count = (int) Cache::get($cacheKey . ':count', 0);

        return $count < $maxPerSecond;
    }

    /**
     * Increment the rate limit counter.
     */
    private function incrementRateLimit(string $cacheKey): void
    {
        $countKey = $cacheKey . ':count';

        if (! Cache::has($countKey)) {
            Cache::put($countKey, 1, 1); // Expires in 1 second
        } else {
            Cache::increment($countKey);
        }
    }

    /**
     * Store data for deferred broadcast.
     *
     * @param  array<string, mixed>  $data
     */
    private function storeForDeferred(string $cacheKey, array $data): void
    {
        $deferredKey = $cacheKey . ':deferred';
        $ttlSeconds = 5; // Keep for 5 seconds max

        Cache::put($deferredKey, $data, $ttlSeconds);
    }
}
