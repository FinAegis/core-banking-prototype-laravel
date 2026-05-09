<?php

/**
 * ErrorResponse — single canonical helper for `ERR_*` API error responses.
 *
 * Reads the registry in config/error_codes.php; every code returned to a
 * client MUST be registered there. Allows extra fields per call (e.g.
 * `eligibleAfter`, `recoveryUrl`, `conflict`) without duplicating shape.
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ErrorResponse
{
    /**
     * Build a JsonResponse for the given registered error code.
     *
     * @param string                $code   error code, e.g. `ERR_SUB_004`
     * @param array<string, mixed>  $extra  optional fields to merge into the JSON body
     * @param int|null              $status overrides config-default status code
     */
    public static function make(string $code, array $extra = [], ?int $status = null): JsonResponse
    {
        $registry = (array) config('error_codes', []);

        if (! isset($registry[$code]) || ! is_array($registry[$code])) {
            throw new InvalidArgumentException(
                "Unknown error code: {$code} — register it in config/error_codes.php."
            );
        }

        /** @var array{http: int, message: string} $entry */
        $entry = $registry[$code];

        $body = array_merge(
            [
                'code'    => $code,
                'message' => $entry['message'],
            ],
            $extra,
        );

        return response()->json($body, $status ?? $entry['http']);
    }
}
