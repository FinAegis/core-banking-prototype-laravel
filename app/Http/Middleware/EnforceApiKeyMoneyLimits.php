<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Models\ApiKey;
use App\Support\DecimalString;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Money controls for requests authenticated by a user API key.
 *
 * Every v2 route that moves money carries this middleware, after the
 * `idempotency` middleware (so a replayed request never reaches it and is
 * not counted twice) and after the write-permission check. Requests signed
 * in any other way (Sanctum app tokens, sessions) pass straight through.
 *
 * For an API key it checks, in order:
 *
 *  1. Account: the account money moves from must belong to the key's owner
 *     and, when the key is pinned to accounts, be one of them
 *     (HTTP 403 API_KEY_ACCOUNT_NOT_ALLOWED).
 *  2. Per-transfer limit: the request amount, in hundredths of its unit
 *     rounded up, must not exceed the key's transfer limit
 *     (HTTP 422 API_KEY_TRANSFER_LIMIT_EXCEEDED).
 *  3. Daily limit: the amount is reserved on the key's counter for the UTC
 *     day with Cache::add + Cache::increment before the money action runs,
 *     and rolled back at once when the total goes over the daily limit
 *     (HTTP 422 API_KEY_DAILY_LIMIT_EXCEEDED). The reservation is released
 *     again when the route does not answer 2xx.
 *
 * The route parameter says where the source account and the amount are:
 *
 *  - transfer: body from_account_uuid / from_account, amount in major units
 *  - account:  path {uuid}, amount in major units (deposit, withdraw)
 *  - basket:   path {uuid}, amount in minor units of basket_code
 *  - bank:     an external bank account; refused for a pinned key, because
 *              the source is not one of the owner's accounts
 */
final class EnforceApiKeyMoneyLimits
{
    public const TRANSFER = 'transfer';

    public const ACCOUNT = 'account';

    public const BASKET = 'basket';

    public const BANK = 'bank';

    public const CODE_ACCOUNT_NOT_ALLOWED = 'API_KEY_ACCOUNT_NOT_ALLOWED';

    public const CODE_TRANSFER_LIMIT_EXCEEDED = 'API_KEY_TRANSFER_LIMIT_EXCEEDED';

    public const CODE_DAILY_LIMIT_EXCEEDED = 'API_KEY_DAILY_LIMIT_EXCEEDED';

    /**
     * Body fields TransferController::store accepts for the source account.
     */
    private const TRANSFER_SOURCE_FIELDS = ['from_account_uuid', 'from_account'];

    /**
     * Basket assets are created with four decimals (BasketAsset::toAsset()).
     */
    private const DEFAULT_BASKET_PRECISION = 4;

    /**
     * Route middleware string for a money route, e.g.
     * EnforceApiKeyMoneyLimits::on(EnforceApiKeyMoneyLimits::TRANSFER).
     */
    public static function on(string $operation): string
    {
        return self::class . ':' . $operation;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $operation = self::TRANSFER): Response
    {
        if (! in_array($operation, [self::TRANSFER, self::ACCOUNT, self::BASKET, self::BANK], true)) {
            throw new LogicException("Unknown API key money operation [{$operation}].");
        }

        $apiKey = $request->attributes->get(AuthenticateApiOrSanctum::API_KEY_ATTRIBUTE);

        if (! $apiKey instanceof ApiKey) {
            $bearer = $request->bearerToken();

            // An API key that reached a money route without being verified
            // by auth.api_or_sanctum: refuse rather than skip the limits.
            if ($bearer !== null && str_starts_with($bearer, 'fak_')) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Invalid API key'], 401);
            }

            return $next($request);
        }

        if (! $this->sourceAccountsAllowed($request, $apiKey, $operation)) {
            Log::warning('API key money request refused: account not allowed', [
                'api_key_id' => $apiKey->id,
                'user_uuid'  => $apiKey->user_uuid,
                'operation'  => $operation,
                'path'       => $request->path(),
            ]);

            return $this->refuse(
                self::CODE_ACCOUNT_NOT_ALLOWED,
                'This API key is not allowed to move money from this account.',
                403,
            );
        }

        $amount = $this->amountInHundredths($request, $operation);

        if ($amount === null) {
            // No amount, or zero or negative: the route's own validation
            // refuses it, and nothing is counted.
            return $next($request);
        }

        $transferLimit = $apiKey->effectiveTransferLimit();

        if ($amount === false || bccomp($amount, bcmul($transferLimit, '100', 0), 0) > 0) {
            Log::warning('API key money request refused: per-transfer limit', [
                'api_key_id' => $apiKey->id,
                'user_uuid'  => $apiKey->user_uuid,
                'operation'  => $operation,
                'amount'     => $amount === false ? 'unreadable' : $amount,
                'limit'      => $transferLimit,
            ]);

            return $this->refuse(
                self::CODE_TRANSFER_LIMIT_EXCEEDED,
                $amount === false
                    ? 'The amount is not a valid number. The limits of this API key can only be checked against a valid number.'
                    : 'This amount is more than this API key may move in one request.',
                422,
                ['limit' => $transferLimit],
            );
        }

        // At most the per-transfer limit, which is at most the ceiling, so
        // it fits in an int.
        $hundredths = (int) $amount;
        $now = CarbonImmutable::now('UTC');
        $counterKey = $apiKey->dailyMoneyCounterKey($now);
        $dailyLimit = $apiKey->effectiveDailyLimit();
        $dailyLimitHundredths = (int) bcmul($dailyLimit, '100', 0);
        $resetsAt = $now->startOfDay()->addDay();

        // Reserve before the money moves: add + increment is atomic, so two
        // concurrent requests can never both fit into the same remainder.
        Cache::add($counterKey, 0, (int) $now->diffInSeconds($resetsAt) + 3600);
        $total = Cache::increment($counterKey, $hundredths);

        if (! is_int($total) || $total > $dailyLimitHundredths) {
            if (is_int($total)) {
                Cache::decrement($counterKey, $hundredths);
            }

            $usedBefore = is_int($total) ? max(0, $total - $hundredths) : $dailyLimitHundredths;
            $remaining = max(0, $dailyLimitHundredths - $usedBefore);

            Log::warning('API key money request refused: daily limit', [
                'api_key_id' => $apiKey->id,
                'user_uuid'  => $apiKey->user_uuid,
                'operation'  => $operation,
                'amount'     => $amount,
                'limit'      => $dailyLimit,
                'used'       => $usedBefore,
            ]);

            return $this->refuse(
                self::CODE_DAILY_LIMIT_EXCEEDED,
                'This amount would take this API key over the total it may move today.',
                422,
                [
                    'limit'           => $dailyLimit,
                    'remaining_today' => bcdiv((string) $remaining, '100', 2),
                    'resets_at'       => $resetsAt->toIso8601String(),
                ],
            );
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            Cache::decrement($counterKey, $hundredths);

            throw $e;
        }

        if (! $response->isSuccessful()) {
            // Nothing moved (validation, insufficient funds, an error): give
            // the reservation back.
            Cache::decrement($counterKey, $hundredths);
        }

        return $response;
    }

    private function sourceAccountsAllowed(Request $request, ApiKey $apiKey, string $operation): bool
    {
        if ($operation === self::BANK) {
            // The money leaves an external bank account, never one of the
            // pinned accounts.
            return ! $apiKey->isPinnedToAccounts();
        }

        if ($operation === self::TRANSFER) {
            foreach (self::TRANSFER_SOURCE_FIELDS as $field) {
                $value = $request->input($field);

                if ($value !== null && ! $this->accountAllowed($value, $apiKey)) {
                    return false;
                }
            }

            // Without a source the controller refuses the request itself.
            return true;
        }

        return $this->accountAllowed($request->route('uuid'), $apiKey);
    }

    private function accountAllowed(mixed $accountUuid, ApiKey $apiKey): bool
    {
        if (! is_string($accountUuid) || $accountUuid === '') {
            return false;
        }

        if (! $apiKey->pinAllowsAccount($accountUuid)) {
            return false;
        }

        $owner = Account::query()->where('uuid', $accountUuid)->value('user_uuid');

        return is_string($owner) && $owner !== '' && $owner === $apiKey->user_uuid;
    }

    /**
     * The request amount in hundredths of its unit, rounded up. Null when
     * there is no amount or it is not positive; false when it cannot be read.
     *
     * @return numeric-string|false|null
     */
    private function amountInHundredths(Request $request, string $operation): string|false|null
    {
        $value = $request->input('amount');

        if ($value === null) {
            return null;
        }

        $amount = DecimalString::fromNumeric($value, 30);

        if ($amount === null) {
            return false;
        }

        if (bccomp($amount, '0', 30) <= 0) {
            return null;
        }

        if ($operation === self::BASKET) {
            // compose/decompose take an integer in the basket's minor units.
            $amount = bcdiv($amount, bcpow('10', (string) $this->basketPrecision($request->input('basket_code')), 0), 30);
        }

        $hundredths = DecimalString::toHundredthsRoundedUp($amount);

        if ($hundredths === null || bccomp($hundredths, (string) PHP_INT_MAX, 0) > 0) {
            return false;
        }

        return $hundredths;
    }

    private function basketPrecision(mixed $basketCode): int
    {
        if (! is_string($basketCode) || $basketCode === '') {
            return self::DEFAULT_BASKET_PRECISION;
        }

        $precision = Asset::query()->where('code', $basketCode)->value('precision');

        return is_numeric($precision) ? max(0, min(18, (int) $precision)) : self::DEFAULT_BASKET_PRECISION;
    }

    /**
     * @param  array<string, string>  $details
     */
    private function refuse(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(array_merge(['error' => $code, 'message' => $message], $details), $status);
    }
}
