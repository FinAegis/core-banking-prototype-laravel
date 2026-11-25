<?php

namespace App\Http\Middleware;

use App\Domain\Asset\Models\Asset;
use App\Support\DecimalString;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TransactionRateLimitMiddleware
{
    /**
     * Transaction-specific rate limits with enhanced security.
     */
    private const TRANSACTION_LIMITS = [
        'deposit' => [
            'limit'             => 10,          // 10 deposits per hour
            'window'            => 3600,       // 1 hour
            'daily_limit'       => 50,    // 50 deposits per day
            'daily_window'      => 86400, // 24 hours
            'amount_limit'      => 100000, // $1000 per hour (in cents)
            'progressive_delay' => true,
        ],
        'withdraw' => [
            'limit'             => 5,           // 5 withdrawals per hour
            'window'            => 3600,       // 1 hour
            'daily_limit'       => 20,    // 20 withdrawals per day
            'daily_window'      => 86400, // 24 hours
            'amount_limit'      => 50000, // $500 per hour (in cents)
            'progressive_delay' => true,
        ],
        'transfer' => [
            'limit'             => 15,          // 15 transfers per hour
            'window'            => 3600,       // 1 hour
            'daily_limit'       => 100,   // 100 transfers per day
            'daily_window'      => 86400, // 24 hours
            'amount_limit'      => 200000, // $2000 per hour (in cents)
            'progressive_delay' => true,
        ],
        'convert' => [
            'limit'             => 20,          // 20 conversions per hour
            'window'            => 3600,       // 1 hour
            'daily_limit'       => 200,   // 200 conversions per day
            'daily_window'      => 86400, // 24 hours
            'amount_limit'      => 500000, // $5000 per hour (in cents)
            'progressive_delay' => false,
        ],
        'x402' => [
            'limit'             => 30,          // 30 x402 payments per hour
            'window'            => 3600,       // 1 hour
            'daily_limit'       => 200,   // 200 x402 payments per day
            'daily_window'      => 86400, // 24 hours
            'amount_limit'      => 1000000, // $10,000 per hour (in cents)
            'progressive_delay' => false,
        ],
        'vote' => [
            'limit'             => 100,         // 100 votes per day
            'window'            => 86400,      // 24 hours
            'daily_limit'       => 100,   // Same as regular limit
            'daily_window'      => 86400,
            'progressive_delay' => false,
        ],
        // amount_limit is one hourly total per transaction type, written in
        // cents of a 2-decimal currency. All assets share that one counter.
        // An amount counts in minor units of the asset the request names,
        // but never in fewer than hundredths of a unit (see extractAmount),
        // so naming another asset can only make the count larger.
        // Mobile wallet payment intent submissions (P2P sends, merchant payments).
        // Count-based only: amount is in token-native units that vary per token
        // (USDC=6dp, SOL=9dp, ETH=18dp, fiat=2dp). Without a spot-price oracle in
        // this layer, a single fiat-cents amount cap cannot be applied correctly
        // — see app/Domain/MobilePayment/Services/PaymentIntentService.php for
        // per-intent business validation. Do NOT add amount_limit here.
        'payment_intent' => [
            'limit'             => 30,    // 30 payment intents per hour
            'window'            => 3600,
            'daily_limit'       => 200,   // 200 payment intents per day
            'daily_window'      => 86400,
            'progressive_delay' => false,
        ],
        // Relayer-backed smart account operations (gasless EVM transactions).
        // Count-based only for the same reason as payment_intent.
        'relayer' => [
            'limit'             => 30,
            'window'            => 3600,
            'daily_limit'       => 200,
            'daily_window'      => 86400,
            'progressive_delay' => false,
        ],
    ];

    /**
     * Handle an incoming request with transaction-specific rate limiting.
     */
    public function handle(Request $request, Closure $next, string $transactionType = 'transfer'): SymfonyResponse
    {
        // Skip rate limiting if disabled
        if (! config('rate_limiting.enabled', true)) {
            return $next($request);
        }

        // Skip rate limiting in testing environment unless explicitly enabled
        if (app()->environment('testing') && ! config('rate_limiting.force_in_tests', false)) {
            return $next($request);
        }

        // Get transaction rate limit configuration
        if (! isset(self::TRANSACTION_LIMITS[$transactionType])) {
            Log::warning('Transaction rate limit: unknown transaction type, falling back to transfer', [
                'transaction_type' => $transactionType,
            ]);
        }
        $config = self::TRANSACTION_LIMITS[$transactionType] ?? self::TRANSACTION_LIMITS['transfer'];

        $userId = $request->user()?->id;
        if (! $userId) {
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
        $amount = null;
        if (isset($config['amount_limit'])) {
            $asset = $this->resolveAsset($request);
            if ($asset === false) {
                return response()->json(
                    [
                        'message' => 'The request names more than one asset.',
                        'errors'  => ['asset_code' => ['The request names more than one asset.']],
                    ],
                    422
                );
            }

            $amount = $this->extractAmount($request, $asset);
        }
        if ($amount !== null) {
            $amountCheck = $this->checkAmountLimit($amount, $userId, $transactionType, $config);
            if ($amountCheck !== true) {
                return $amountCheck;
            }
        }

        // Apply progressive delay if enabled
        if ($config['progressive_delay']) {
            $this->applyProgressiveDelay($userId, $transactionType);
        }

        // Increment counters and proceed
        $this->incrementCounters($userId, $transactionType, $request, $amount);

        $response = $next($request);

        return $this->addTransactionHeaders($response, $userId, $transactionType, $config);
    }

    /**
     * Check rate limit for a specific period.
     */
    private function checkRateLimit(int $userId, string $transactionType, string $period, int $limit, int $window): true|JsonResponse
    {
        $key = "tx_rate_limit:{$userId}:{$transactionType}:{$period}";
        $currentCount = Cache::get($key, 0);

        if ($currentCount >= $limit) {
            Log::warning(
                'Transaction rate limit exceeded',
                [
                    'user_id'          => $userId,
                    'transaction_type' => $transactionType,
                    'period'           => $period,
                    'limit'            => $limit,
                    'current_count'    => $currentCount,
                ]
            );

            // Calculate retry after seconds
            $retryAfter = $window - (time() % $window);

            // Track how many times this user has hit rate limits for progressive delay
            $attemptKey = "tx_rate_limit_attempts:{$userId}:{$transactionType}";
            $attempts = Cache::get($attemptKey, 0);
            Cache::put($attemptKey, $attempts + 1, 300); // Track for 5 minutes

            // Apply progressive delay to retry_after
            if ($attempts > 0) {
                $retryAfter = $retryAfter + ($attempts * 10); // Add 10 seconds for each attempt
            }

            $responseData = [
                'error'         => 'Transaction rate limit exceeded',
                'message'       => "You have exceeded the {$period} limit of {$limit} {$transactionType} transactions.",
                'retry_after'   => $retryAfter,
                'limit_type'    => "{$period}_count",
                'period'        => $period,
                'limit'         => $limit,
                'current_count' => $currentCount,
                'reset_time'    => now()->addSeconds($window)->toISOString(),
            ];

            // Add security notice if suspicious activity detected
            if ($attempts > 5) {
                $responseData['security_notice'] = 'Multiple rate limit violations detected. Your activity has been logged for security review.';
            }

            return response()->json(
                $responseData,
                429,
                [
                    'X-Transaction-RateLimit-Exceeded' => $period,
                    'X-Transaction-Limit'              => $limit,
                    'X-Transaction-Remaining'          => 0,
                    'Retry-After'                      => $window,
                ]
            );
        }

        return true;
    }

    /**
     * Check amount-based rate limits.
     *
     * @param  array{asset: string|null, minor: numeric-string}  $amount
     */
    private function checkAmountLimit(array $amount, int $userId, string $transactionType, array $config): true|JsonResponse
    {
        $key = $this->amountKey($userId, $transactionType);
        $currentAmount = (int) Cache::get($key, 0);
        $limit = (int) $config['amount_limit'];

        if (bccomp(bcadd((string) $currentAmount, $amount['minor'], 0), (string) $limit, 0) > 0) {
            $requested = bccomp($amount['minor'], (string) PHP_INT_MAX, 0) <= 0 ? (int) $amount['minor'] : $amount['minor'];

            Log::warning(
                'Transaction amount limit exceeded',
                [
                    'user_id'          => $userId,
                    'transaction_type' => $transactionType,
                    'asset_code'       => $amount['asset'],
                    'amount'           => $requested,
                    'current_amount'   => $currentAmount,
                    'limit'            => $limit,
                ]
            );

            // Calculate retry after seconds
            $retryAfter = $config['window'] - (time() % $config['window']);

            return response()->json(
                [
                    'error'         => 'Transaction amount limit exceeded',
                    'message'       => 'This transaction would exceed your hourly amount limit.',
                    'retry_after'   => $retryAfter,
                    'limit_type'    => 'hourly_amount',
                    'limit_details' => [
                        // One hourly total for all assets, as integers in minor
                        // units: cents for 2-decimal assets, satoshis for BTC;
                        // never fewer than hundredths of a unit. asset_code is
                        // the asset of this request (null when it names none
                        // or an unknown one).
                        'limit'            => $limit,
                        'limit_unit'       => 'minor_units',
                        'asset_code'       => $amount['asset'],
                        'current_amount'   => $currentAmount,
                        'requested_amount' => $requested,
                        'remaining_amount' => max(0, $limit - $currentAmount),
                    ],
                ],
                429,
                [
                    'X-Transaction-AmountLimit-Exceeded' => 'true',
                    'X-Transaction-AmountLimit'          => $limit,
                    'X-Transaction-AmountRemaining'      => max(0, $limit - $currentAmount),
                ]
            );
        }

        return true;
    }

    /**
     * Apply progressive delay based on recent transaction frequency.
     */
    private function applyProgressiveDelay(int $userId, string $transactionType): void
    {
        $recentKey = "tx_recent:{$userId}:{$transactionType}";
        $recentCount = Cache::get($recentKey, 0);

        // Apply delay based on recent transaction frequency
        if ($recentCount > 3) {
            $delay = min(5, $recentCount - 3); // Max 5 second delay

            // Skip actual delay in testing environment
            if (! app()->environment('testing')) {
                sleep($delay);
            }

            Log::info(
                'Progressive delay applied',
                [
                    'user_id'          => $userId,
                    'transaction_type' => $transactionType,
                    'recent_count'     => $recentCount,
                    'delay_seconds'    => $delay,
                ]
            );
        }

        // Track recent transactions (5-minute window)
        Cache::put($recentKey, $recentCount + 1, 300);
    }

    /**
     * Request fields that name the asset of the amount. Controllers book one
     * of them (deposit, withdraw and transfer: asset_code; bank transfers,
     * GCU trades and agent payments: currency; conversion: from_currency).
     * Every field present must name the same asset.
     */
    private const ASSET_FIELDS = ['asset_code', 'currency', 'from_currency', 'asset', 'from_asset'];

    /**
     * The asset the request names, upper-cased and resolved against the
     * assets table: its code and precision. Null when the request names no
     * asset or an unknown one. False when the fields name different assets.
     *
     * @return array{code: string, precision: int}|false|null
     */
    private function resolveAsset(Request $request): array|false|null
    {
        $codes = [];
        foreach (self::ASSET_FIELDS as $field) {
            $value = $request->input($field);
            if (is_string($value) && trim($value) !== '') {
                $codes[strtoupper(trim($value))] = true;
            }
        }

        if (count($codes) > 1) {
            return false;
        }

        if ($codes === []) {
            return null;
        }

        $code = (string) array_key_first($codes);
        $precision = Asset::query()->where('code', $code)->value('precision');

        return $precision === null ? null : ['code' => $code, 'precision' => (int) $precision];
    }

    /**
     * Extract the transaction amount from the request in minor units of its
     * asset, with bcmath, for the one shared counter. The precision is the
     * asset's, but at least 2: an asset with fewer decimals (or none, or an
     * unknown one) counts in hundredths of a unit, so naming it can never
     * lower the count. An amount with more decimals than that counts as the
     * next whole unit, so the count never comes out low; validating such an
     * amount is the controller's job. Zero and negative amounts are not
     * counted.
     *
     * @param  array{code: string, precision: int}|null  $asset
     * @return array{asset: string|null, minor: numeric-string}|null
     */
    private function extractAmount(Request $request, ?array $asset): ?array
    {
        // Try common amount field names
        $amountFields = ['amount', 'value', 'quantity', 'sum'];

        foreach ($amountFields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $amount = $request->input($field);
            $exact = DecimalString::fromNumeric($amount, 30);
            if ($exact === null) {
                continue;
            }

            if (bccomp($exact, '0', 30) <= 0) {
                return null;
            }

            $precision = max(2, $asset['precision'] ?? 2);

            $minor = DecimalString::toMinorUnits($amount, $precision);
            if ($minor === null) {
                // More decimals than the asset has: round up to the next unit.
                $truncated = DecimalString::toMinorUnits(DecimalString::fromNumeric($amount, $precision), $precision);
                if ($truncated === null) {
                    return null;
                }
                $minor = bcadd($truncated, '1', 0);
            }

            return ['asset' => $asset['code'] ?? null, 'minor' => $minor];
        }

        return null;
    }

    private function amountKey(int $userId, string $transactionType): string
    {
        return "tx_amount_limit:{$userId}:{$transactionType}:hourly";
    }

    /**
     * Increment transaction counters.
     */
    /**
     * @param  array{asset: string|null, minor: numeric-string}|null  $amount
     */
    private function incrementCounters(int $userId, string $transactionType, Request $request, ?array $amount): void
    {
        $config = self::TRANSACTION_LIMITS[$transactionType] ?? self::TRANSACTION_LIMITS['transfer'];

        // Increment hourly counter
        $hourlyKey = "tx_rate_limit:{$userId}:{$transactionType}:hourly";
        Cache::put($hourlyKey, Cache::get($hourlyKey, 0) + 1, $config['window']);

        // Increment daily counter
        $dailyKey = "tx_rate_limit:{$userId}:{$transactionType}:daily";
        Cache::put($dailyKey, Cache::get($dailyKey, 0) + 1, $config['daily_window']);

        // Track amount if applicable. The check above passed, so the amount
        // is at most the limit and fits in an int. Cache::add + increment, so
        // concurrent requests never lose a count.
        if (isset($config['amount_limit']) && $amount !== null) {
            $amountKey = $this->amountKey($userId, $transactionType);
            Cache::add($amountKey, 0, $config['window']);
            Cache::increment($amountKey, (int) $amount['minor']);
        }

        // Log transaction attempt for audit
        Log::info(
            'Transaction rate limit check passed',
            [
                'user_id'          => $userId,
                'transaction_type' => $transactionType,
                'endpoint'         => $request->path(),
                'asset_code'       => $amount['asset'] ?? null,
                'amount'           => $amount['minor'] ?? null,
            ]
        );
    }

    /**
     * Add transaction-specific headers to response.
     */
    private function addTransactionHeaders(SymfonyResponse $response, int $userId, string $transactionType, array $config): SymfonyResponse
    {
        $hourlyKey = "tx_rate_limit:{$userId}:{$transactionType}:hourly";
        $dailyKey = "tx_rate_limit:{$userId}:{$transactionType}:daily";

        $hourlyCount = Cache::get($hourlyKey, 0);
        $dailyCount = Cache::get($dailyKey, 0);

        // Set standard headers expected by tests
        $response->headers->set('X-RateLimit-Transaction-Limit', (string) $config['limit']);
        $response->headers->set('X-RateLimit-Transaction-Remaining', (string) max(0, $config['limit'] - $hourlyCount));
        $response->headers->set('X-RateLimit-Transaction-Reset', (string) now()->addSeconds($config['window'])->timestamp);

        // Also set detailed headers
        $response->headers->set('X-Transaction-Hourly-Limit', (string) $config['limit']);
        $response->headers->set('X-Transaction-Hourly-Remaining', (string) max(0, $config['limit'] - $hourlyCount));
        $response->headers->set('X-Transaction-Daily-Limit', (string) $config['daily_limit']);
        $response->headers->set('X-Transaction-Daily-Remaining', (string) max(0, $config['daily_limit'] - $dailyCount));

        if (isset($config['amount_limit'])) {
            $amountKey = $this->amountKey($userId, $transactionType);
            $currentAmount = (int) Cache::get($amountKey, 0);

            $response->headers->set('X-Transaction-Amount-Limit', (string) $config['amount_limit']);
            $response->headers->set('X-Transaction-Amount-Remaining', (string) max(0, $config['amount_limit'] - $currentAmount));
        }

        return $response;
    }

    /**
     * Count one call of a transaction type against the user's hourly and
     * daily limits, on the same counters this middleware keeps. A GraphQL
     * field that does the work of a rate-limited REST route calls this, so
     * both share one counter. Returns false when a limit is already
     * reached. Follows the same enabled and testing switches as handle().
     */
    public static function consume(int $userId, string $transactionType): bool
    {
        if (! config('rate_limiting.enabled', true)) {
            return true;
        }

        if (app()->environment('testing') && ! config('rate_limiting.force_in_tests', false)) {
            return true;
        }

        $config = self::TRANSACTION_LIMITS[$transactionType] ?? self::TRANSACTION_LIMITS['transfer'];
        $periods = [
            'hourly' => [$config['limit'], $config['window']],
            'daily'  => [$config['daily_limit'], $config['daily_window']],
        ];

        foreach ($periods as $period => [$limit, $window]) {
            $key = "tx_rate_limit:{$userId}:{$transactionType}:{$period}";
            Cache::add($key, 0, $window);

            if ((int) Cache::increment($key) > $limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get transaction rate limit configuration.
     */
    public static function getTransactionLimits(): array
    {
        return self::TRANSACTION_LIMITS;
    }

    /**
     * Check if a transaction type is valid.
     */
    public static function isValidTransactionType(string $type): bool
    {
        return array_key_exists($type, self::TRANSACTION_LIMITS);
    }
}
