<?php

declare(strict_types=1);

namespace App\Infrastructure\FinCard;

use RuntimeException;

/**
 * Raised when a FinCard (FinHub BFF) RPC call fails.
 *
 * FinCard returns an RPC envelope `{ success, code, msg, data }`. A business
 * failure arrives as HTTP 200 with `success=false` (carrying an integer `code`
 * and a human `msg`); a transport/gateway failure arrives as a non-2xx status.
 * Both surface here so the call site can map FinCard's `code` onto our own
 * `ERR_CARDS_*` responses without re-parsing the envelope.
 *
 * The FinCard business-code catalogue is not published (open item, see the
 * design spec §14) — `$apiCode` is preserved verbatim for that mapping once we
 * have it, and for operator log correlation in the meantime.
 */
final class FinCardApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $apiCode = null,
        public readonly ?int $httpStatus = null,
        public readonly string $path = '',
    ) {
        parent::__construct($message);
    }

    public static function business(string $path, ?int $apiCode, string $msg): self
    {
        return new self(
            sprintf('FinCard %s failed (code %s): %s', $path, $apiCode ?? 'null', $msg !== '' ? $msg : 'no message'),
            apiCode: $apiCode,
            path: $path,
        );
    }

    public static function transport(string $path, int $httpStatus): self
    {
        return new self(
            sprintf('FinCard %s returned HTTP %d', $path, $httpStatus),
            httpStatus: $httpStatus,
            path: $path,
        );
    }

    public static function malformed(string $path): self
    {
        return new self(
            sprintf('FinCard %s returned a malformed (non-object) response', $path),
            path: $path,
        );
    }
}
