<?php

declare(strict_types=1);

namespace App\Domain\MCP\Sagas;

use App\Domain\MCP\Exceptions\SpendingLimitExceededException;
use App\Domain\MCP\Policy\SpendingLimitService;
use Throwable;

/**
 * Saga (synchronous, in-process) that enforces per-token daily spend limits
 * around a payment-tool execution.
 *
 * Steps:
 *   1. Reserve $amountMinor against the token's daily window. If the policy
 *      rejects (over limit, currency mismatch, no policy), throw
 *      SpendingLimitExceededException — the router maps this to -32003.
 *   2. Run the underlying tool execution callable.
 *   3. On adapter-level error (`isError === true`) OR a thrown exception,
 *      call SpendingLimitService::release() to roll back the reservation,
 *      then propagate the result/exception. The reservation only sticks
 *      when the tool reports success.
 *
 * This is the saga *pattern* (reserve → exec → commit/compensate) rather
 * than a durable workflow: it lives in a single PHP request, fits the
 * shape of MCP's synchronous JSON-RPC envelope, and avoids the durable
 * runtime overhead. If we later need to resume a half-executed payment
 * across crashes, this is the obvious extraction point.
 */
final class SpendingEnforcedToolCallSaga
{
    public function __construct(
        private readonly SpendingLimitService $spending,
    ) {
    }

    /**
     * @param  array<string, mixed> $arguments  The tool call arguments (must contain the configured amount + currency fields unless the entry is fixed-cost).
     * @param  array{amount_arg?: string, currency_arg?: string, fixed_cost_minor?: int, fixed_cost_currency?: string}|array<string, mixed> $entry  The catalog entry for this tool.
     * @param  callable(): array<string, mixed> $execute  Closure that runs the tool and returns the McpToolAdapter envelope.
     * @return array<string, mixed>
     *
     * @throws SpendingLimitExceededException  When the policy rejects the reservation.
     */
    public function run(string $tokenId, array $arguments, array $entry, callable $execute): array
    {
        [$amountMinor, $currency] = $this->resolveCharge($arguments, $entry);

        $reserve = $this->spending->reserve($tokenId, $amountMinor, $currency);
        if (! ($reserve['allowed'] ?? false)) {
            throw new SpendingLimitExceededException([
                'error_code'             => $reserve['error_code'] ?? 'LIMIT_EXCEEDED',
                'limit_remaining_minor'  => $reserve['limit_remaining_minor'] ?? null,
                'window_resets_at'       => $reserve['window_resets_at'] ?? null,
                'amount_requested_minor' => $amountMinor,
                'currency'               => $currency,
            ]);
        }

        try {
            $result = $execute();
        } catch (Throwable $e) {
            $this->spending->release($tokenId, $amountMinor, $currency);
            throw $e;
        }

        if (($result['isError'] ?? false) === true) {
            $this->spending->release($tokenId, $amountMinor, $currency);
        }

        return $result;
    }

    /**
     * Resolve the charge to reserve for this call. Two pricing models:
     *
     *  - Fixed-cost tools (catalog `fixed_cost_minor`, e.g. sms.send) charge a
     *    flat per-call amount in minor units regardless of the call arguments —
     *    the price isn't in the tool schema, so it lives in the catalog entry.
     *  - Variable-amount tools read major-unit amount + currency from the call
     *    arguments via `amount_arg` / `currency_arg` / `amount_decimals`.
     *
     * @param  array<string, mixed> $arguments
     * @param  array<string, mixed> $entry
     * @return array{0: int, 1: string}
     */
    private function resolveCharge(array $arguments, array $entry): array
    {
        if (isset($entry['fixed_cost_minor'])) {
            return [(int) $entry['fixed_cost_minor'], (string) ($entry['fixed_cost_currency'] ?? 'USD')];
        }

        return $this->extractAmountAndCurrency($arguments, $entry);
    }

    /**
     * Pull the numeric amount + ISO currency code from the call arguments and
     * convert to minor units. Tools advertise amounts in major units as either
     * integers or floats; the saga converts to integer minor units via bcmath
     * to avoid IEEE-754 rounding (e.g. (int)(0.1 * 100) == 9 in PHP).
     *
     * Throws SpendingLimitExceededException with a 400-equivalent code when
     * the payment-tool catalog entry is missing the configured fields — same
     * surface as a real over-limit so the router uses one error path.
     *
     * @param  array<string, mixed> $arguments
     * @param  array<string, mixed> $entry
     * @return array{0: int, 1: string}
     */
    private function extractAmountAndCurrency(array $arguments, array $entry): array
    {
        $amountField = (string) ($entry['amount_arg'] ?? 'amount_minor');
        $currencyField = (string) ($entry['currency_arg'] ?? 'currency');
        $decimals = max(0, (int) ($entry['amount_decimals'] ?? 0));

        $rawAmount = $arguments[$amountField] ?? null;
        $rawCurrency = $arguments[$currencyField] ?? null;

        // Reject anything other than int or string-of-digits-and-dot. JSON floats
        // are tolerated for backward compatibility (catalog v1 tools use them)
        // but rejected if they came in as scientific-notation strings — `(string)`
        // of a float can produce "1.0E-5" which bcmath misreads. Accept:
        //   - int (any size)
        //   - string matching /^\d+(\.\d+)?$/
        //   - float that round-trips through string→float without scientific notation
        if (! $this->isAcceptableAmount($rawAmount)) {
            throw new SpendingLimitExceededException([
                'error_code'   => 'AMOUNT_INVALID',
                'amount_field' => $amountField,
            ]);
        }

        // After isAcceptableAmount(), $rawAmount is int / finite-positive float
        // without scientific notation / digits-and-dot string. The (string)
        // cast on the numeric variants produces a bcmath-compatible representation.
        /** @var numeric-string $amountString */
        $amountString = is_string($rawAmount) ? $rawAmount : (string) $rawAmount;

        // bcmul preserves precision through the major→minor conversion.
        $amountMinor = (int) bcmul($amountString, bcpow('10', (string) $decimals), 0);
        if ($amountMinor <= 0) {
            throw new SpendingLimitExceededException([
                'error_code'   => 'AMOUNT_INVALID',
                'amount_field' => $amountField,
            ]);
        }

        if (! is_string($rawCurrency) || $rawCurrency === '') {
            throw new SpendingLimitExceededException([
                'error_code'     => 'CURRENCY_INVALID',
                'currency_field' => $currencyField,
            ]);
        }

        return [$amountMinor, $rawCurrency];
    }

    /**
     * Whitelist amount inputs. We accept ints, plain decimal-string amounts
     * (`"100.50"`), and floats that don't trip PHP's scientific-notation
     * stringification. Rejects: scientific-notation strings (`"1e2"`),
     * negative numbers, NaN/Inf casts, hex/octal numeric strings.
     */
    private function isAcceptableAmount(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_float($value)) {
            if (! is_finite($value) || $value <= 0) {
                return false;
            }

            // Round-trip via PHP's serialize_precision. If the resulting string
            // contains 'e' or 'E', bcmath would misread it.
            return ! str_contains((string) $value, 'e') && ! str_contains((string) $value, 'E');
        }

        if (is_string($value)) {
            // Plain decimal: optional sign rejected, optional dot, no scientific.
            return preg_match('/^\d+(\.\d+)?$/', $value) === 1;
        }

        return false;
    }
}
