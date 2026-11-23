<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use Illuminate\Support\Facades\DB;

/**
 * The daily spending limit a user gives an MCP app on the consent screen.
 *
 * The limit belongs to the user's approval of the app, not to one access
 * token: tokens are refreshed and re-issued, and each new token must keep
 * using the same allowance. Rows live in mcp_token_policies, whose token_id
 * column holds this key ("grant:{client id}:{user id}").
 */
final class ConsentSpendingPolicy
{
    /** Stored for "No limit"; the reservation arithmetic needs a number. */
    public const NO_LIMIT = PHP_INT_MAX;

    public static function key(string $clientId, int|string $userId): string
    {
        return "grant:{$clientId}:{$userId}";
    }

    /**
     * Turn the posted consent field into a limit in minor units. Only the
     * values the screen offers are accepted; an empty value is "No limit";
     * anything else (or no field at all) gets the configured default.
     */
    public static function limitFromConsent(mixed $value): int
    {
        $default = (int) config('mcp.spending.default_daily_limit_minor', 50000);
        if ($value === null) {
            return $default;
        }

        /** @var array<int, int|null> $options */
        $options = (array) config('mcp.spending.consent_options_minor', []);
        if ($value === '' && in_array(null, $options, true)) {
            return self::NO_LIMIT;
        }

        if (is_string($value) && ctype_digit($value) && in_array((int) $value, $options, true)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Save the limit for this approval. A repeated approval changes the limit
     * but keeps what was already spent in the current 24-hour window.
     */
    public function record(string $clientId, int|string $userId, int $limitMinor): void
    {
        $key = self::key($clientId, $userId);
        $currency = (string) config('mcp.spending.default_daily_limit_currency', 'USD');

        $updated = DB::table('mcp_token_policies')->where('token_id', $key)->update([
            'daily_limit_minor'    => $limitMinor,
            'daily_limit_currency' => $currency,
            'updated_at'           => now(),
        ]);

        if ($updated === 0) {
            DB::table('mcp_token_policies')->insertOrIgnore([
                'token_id'              => $key,
                'daily_limit_minor'     => $limitMinor,
                'daily_limit_currency'  => $currency,
                'daily_spend_minor'     => 0,
                'daily_window_start_at' => now(),
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }
}
