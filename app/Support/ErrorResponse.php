<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use LogicException;

/**
 * Canonical Plan B error response builder.
 *
 * New endpoints emit errors via `ErrorResponse::make('ERR_QUOTE_001')`
 * instead of raw `response()->json([...], 410)`. Every code MUST be
 * registered in `config/error_codes.php` — unknown codes throw at runtime
 * (fail loud during code review/test, never in production).
 *
 * @see config/error_codes.php
 */
final class ErrorResponse
{
    private function __construct()
    {
        // Static helpers only.
    }

    /**
     * Build a JsonResponse for a registered Plan B error code.
     *
     * @param  array<string, mixed>  $context  optional fields merged into `error.*` (e.g. `existingSource`, `recoveryUrl`)
     *
     * @throws LogicException if the code is not registered in config/error_codes.php
     */
    public static function make(string $code, ?string $messageOverride = null, array $context = []): JsonResponse
    {
        /** @var array<string, array{http: int, description: string}> $registry */
        $registry = config('error_codes', []);

        if (! isset($registry[$code])) {
            throw new LogicException(sprintf(
                'Unknown error code "%s". Register it in config/error_codes.php before use.',
                $code,
            ));
        }

        $entry = $registry[$code];
        $message = $messageOverride ?? $entry['description'];

        return response()->json([
            'success' => false,
            'error'   => array_merge([
                'code'    => $code,
                'message' => $message,
            ], $context),
        ], $entry['http']);
    }
}
