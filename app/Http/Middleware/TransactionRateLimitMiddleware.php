<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TransactionRateLimitMiddleware
{
    /**
     * Transaction-specific rate limits with enhanced security
     */
    private const TRANSACTION_LIMITS = [
        'deposit' => [
            'limit' => 10,          // 10 deposits per hour
            'window' => 3600,       // 1 hour
            'daily_limit' => 50,    // 50 deposits per day
            'daily_window' => 86400, // 24 hours
            'amount_limit' => 100000, // $1000 per hour (in cents)
            'progressive_delay' => true,
        ],
        'withdraw' => [
            'limit' => 5,           // 5 withdrawals per hour
            'window' => 3600,       // 1 hour
            'daily_limit' => 20,    // 20 withdrawals per day
            'daily_window' => 86400, // 24 hours
            'amount_limit' => 50000, // $500 per hour (in cents)
            'progressive_delay' => true,
        ],
        'transfer' => [
            'limit' => 15,          // 15 transfers per hour
            'window' => 3600,       // 1 hour
            'daily_limit' => 100,   // 100 transfers per day
            'daily_window' => 86400, // 24 hours
            'amount_limit' => 200000, // $2000 per hour (in cents)
            'progressive_delay' => true,
        ],
        'convert' => [
            'limit' => 20,          // 20 conversions per hour
            'window' => 3600,       // 1 hour
            'daily_limit' => 200,   // 200 conversions per day
            'daily_window' => 86400, // 24 hours
            'amount_limit' => 500000, // $5000 per hour (in cents)
            'progressive_delay' => false,
        ],
        'vote' => [
            'limit' => 100,         // 100 votes per day
            'window' => 86400,      // 24 hours
            'daily_limit' => 100,   // Same as regular limit
            'daily_window' => 86400,
            'progressive_delay' => false,
        ]
    ];

    /**
     * Handle an incoming request with transaction-specific rate limiting
     */
    public function handle(Request $request, Closure $next, string $transactionType = 'transfer'): SymfonyResponse
    {
        // Skip rate limiting if disabled
        if (!config('rate_limiting.enabled', true)) {
            return $next($request);
        }
        
        // Skip rate limiting in testing environment unless explicitly enabled
        if (app()->environment('testing') && !config('rate_limiting.force_in_tests', false)) {
            return $next($request);
        }
        
        // Get transaction rate limit configuration
        $config = self::TRANSACTION_LIMITS[$transactionType] ?? self::TRANSACTION_LIMITS['transfer'];
        
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['error' => 'Authentication required for transaction rate limiting'], 401);
        }

        // Check hourly limits
        $hourlyCheck = $this->checkRateLimit($userId, $transactionType, 'hourly', $config['limit'], $config['window']);
        if ($hourlyCheck !== true) {
            return $hourlyCheck;
        }

        // Check daily limits
        $dailyCheck = $this->checkRateLimit($userId, $transactionType, 'daily', $config['daily_limit'], $config['daily_window']);
        if ($dailyCheck !== true) {
            return $dailyCheck;
        }

        // Check amount-based limits if applicable
        if (isset($config['amount_limit'])) {
            $amountCheck = $this->checkAmountLimit($request, $userId, $transactionType, $config);
            if ($amountCheck !== true) {
                return $amountCheck;
            }
        }

        // Apply progressive delay if enabled
        if ($config['progressive_delay']) {
            $this->applyProgressiveDelay($userId, $transactionType);
        }

        // Increment counters and proceed
        $this->incrementCounters($userId, $transactionType, $request);
        
        $response = $next($request);
        
        return $this->addTransactionHeaders($response, $userId, $transactionType, $config);
    }

    /**
     * Check rate limit for a specific period
     */
    private function checkRateLimit(int $userId, string $transactionType, string $period, int $limit, int $window): true|JsonResponse
    {
        $key = "tx_rate_limit:{$userId}:{$transactionType}:{$period}";
        $currentCount = Cache::get($key, 0);

        if ($currentCount >= $limit) {
            Log::warning('Transaction rate limit exceeded', [
                'user_id' => $userId,
                'transaction_type' => $transactionType,
                'period' => $period,
                'limit' => $limit,
                'current_count' => $currentCount,
            ]);

            return response()->json([
                'error' => 'Transaction rate limit exceeded',
                'message' => "You have exceeded the {$period} limit of {$limit} {$transactionType} transactions.",
                'period' => $period,
                'limit' => $limit,
                'current_count' => $currentCount,
                'reset_time' => now()->addSeconds($window)->toISOString(),
            ], 429, [
                'X-Transaction-RateLimit-Exceeded' => $period,
                'X-Transaction-Limit' => $limit,
                'X-Transaction-Remaining' => 0,
                'Retry-After' => $window,
            ]);
        }

        return true;
    }

    /**
     * Check amount-based rate limits
     */
    private function checkAmountLimit(Request $request, int $userId, string $transactionType, array $config): true|JsonResponse
    {
        $amount = $this->extractAmount($request);
        if ($amount === null) {
            return true; // No amount to check
        }

        $key = "tx_amount_limit:{$userId}:{$transactionType}:hourly";
        $currentAmount = Cache::get($key, 0);

        if (($currentAmount + $amount) > $config['amount_limit']) {
            Log::warning('Transaction amount limit exceeded', [
                'user_id' => $userId,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'current_amount' => $currentAmount,
                'limit' => $config['amount_limit'],
            ]);

            return response()->json([
                'error' => 'Transaction amount limit exceeded',
                'message' => "This transaction would exceed your hourly amount limit.",
                'amount_limit' => $config['amount_limit'],
                'current_amount' => $currentAmount,
                'requested_amount' => $amount,
                'remaining_amount' => max(0, $config['amount_limit'] - $currentAmount),
            ], 429, [
                'X-Transaction-AmountLimit-Exceeded' => 'true',
                'X-Transaction-AmountLimit' => $config['amount_limit'],
                'X-Transaction-AmountRemaining' => max(0, $config['amount_limit'] - $currentAmount),
            ]);
        }

        return true;
    }

    /**
     * Apply progressive delay based on recent transaction frequency
     */
    private function applyProgressiveDelay(int $userId, string $transactionType): void
    {
        $recentKey = "tx_recent:{$userId}:{$transactionType}";
        $recentCount = Cache::get($recentKey, 0);

        // Apply delay based on recent transaction frequency
        if ($recentCount > 3) {
            $delay = min(5, $recentCount - 3); // Max 5 second delay
            sleep($delay);
            
            Log::info('Progressive delay applied', [
                'user_id' => $userId,
                'transaction_type' => $transactionType,
                'recent_count' => $recentCount,
                'delay_seconds' => $delay,
            ]);
        }

        // Track recent transactions (5-minute window)
        Cache::put($recentKey, $recentCount + 1, 300);
    }

    /**
     * Extract transaction amount from request
     */
    private function extractAmount(Request $request): ?int
    {
        // Try common amount field names
        $amountFields = ['amount', 'value', 'quantity', 'sum'];
        
        foreach ($amountFields as $field) {
            if ($request->has($field)) {
                $amount = $request->input($field);
                
                // Convert to integer (cents) if it's a decimal
                if (is_numeric($amount)) {
                    return (int)($amount * 100);
                }
            }
        }

        return null;
    }

    /**
     * Increment transaction counters
     */
    private function incrementCounters(int $userId, string $transactionType, Request $request): void
    {
        $config = self::TRANSACTION_LIMITS[$transactionType];
        
        // Increment hourly counter
        $hourlyKey = "tx_rate_limit:{$userId}:{$transactionType}:hourly";
        Cache::put($hourlyKey, Cache::get($hourlyKey, 0) + 1, $config['window']);

        // Increment daily counter
        $dailyKey = "tx_rate_limit:{$userId}:{$transactionType}:daily";
        Cache::put($dailyKey, Cache::get($dailyKey, 0) + 1, $config['daily_window']);

        // Track amount if applicable
        if (isset($config['amount_limit'])) {
            $amount = $this->extractAmount($request);
            if ($amount !== null) {
                $amountKey = "tx_amount_limit:{$userId}:{$transactionType}:hourly";
                Cache::put($amountKey, Cache::get($amountKey, 0) + $amount, $config['window']);
            }
        }

        // Log transaction attempt for audit
        Log::info('Transaction rate limit check passed', [
            'user_id' => $userId,
            'transaction_type' => $transactionType,
            'endpoint' => $request->path(),
            'amount' => $this->extractAmount($request),
        ]);
    }

    /**
     * Add transaction-specific headers to response
     */
    private function addTransactionHeaders(SymfonyResponse $response, int $userId, string $transactionType, array $config): SymfonyResponse
    {
        $hourlyKey = "tx_rate_limit:{$userId}:{$transactionType}:hourly";
        $dailyKey = "tx_rate_limit:{$userId}:{$transactionType}:daily";
        
        $hourlyCount = Cache::get($hourlyKey, 0);
        $dailyCount = Cache::get($dailyKey, 0);

        $response->headers->set('X-Transaction-Hourly-Limit', $config['limit']);
        $response->headers->set('X-Transaction-Hourly-Remaining', max(0, $config['limit'] - $hourlyCount));
        $response->headers->set('X-Transaction-Daily-Limit', $config['daily_limit']);
        $response->headers->set('X-Transaction-Daily-Remaining', max(0, $config['daily_limit'] - $dailyCount));

        if (isset($config['amount_limit'])) {
            $amountKey = "tx_amount_limit:{$userId}:{$transactionType}:hourly";
            $currentAmount = Cache::get($amountKey, 0);
            
            $response->headers->set('X-Transaction-Amount-Limit', $config['amount_limit']);
            $response->headers->set('X-Transaction-Amount-Remaining', max(0, $config['amount_limit'] - $currentAmount));
        }

        return $response;
    }

    /**
     * Get transaction rate limit configuration
     */
    public static function getTransactionLimits(): array
    {
        return self::TRANSACTION_LIMITS;
    }

    /**
     * Check if a transaction type is valid
     */
    public static function isValidTransactionType(string $type): bool
    {
        return array_key_exists($type, self::TRANSACTION_LIMITS);
    }
}