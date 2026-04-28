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
     * @param  array<string, mixed> $arguments  The tool call arguments (must contain the configured amount + currency fields).
     * @param  array{amount_arg?: string, currency_arg?: string}|array<string, mixed> $entry  The catalog entry for this tool.
     * @param  callable(): array<string, mixed> $execute  Closure that runs the tool and returns the McpToolAdapter envelope.
     * @return array<string, mixed>
     *
     * @throws SpendingLimitExceededException  When the policy rejects the reservation.
     */
    public function run(string $tokenId, array $arguments, array $entry, callable $execute): array
    {
        [$amountMinor, $currency] = $this->extractAmountAndCurrency($arguments, $entry);

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
     * Pull the numeric amount + ISO currency code from the call arguments.
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

        $rawAmount = $arguments[$amountField] ?? null;
        $rawCurrency = $arguments[$currencyField] ?? null;

        if (! is_int($rawAmount) || $rawAmount <= 0) {
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

        return [$rawAmount, $rawCurrency];
    }
}
