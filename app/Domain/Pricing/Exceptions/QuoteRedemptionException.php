<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exceptions;

use RuntimeException;

/**
 * Thrown by QuoteService::redeem() when a quote cannot be consumed.
 *
 * The $errorCode property maps directly to a Plan B error code in
 * config/error_codes.php so controllers can pass it to ErrorResponse::make().
 */
final class QuoteRedemptionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function notFound(): self
    {
        return new self('ERR_QUO_001', 'Quote not found or does not belong to the authenticated user.');
    }

    public static function alreadyConsumed(): self
    {
        return new self('ERR_QUO_002', 'Quote has already been consumed.');
    }

    public static function expired(): self
    {
        return new self('ERR_QUOTE_001', 'Quote has expired.');
    }

    public static function payloadMismatch(): self
    {
        return new self('ERR_QUOTE_002', 'Submitted payload does not match quoted userOp hash.');
    }
}
