<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * A generic sponsorship request (REST POST /api/v1/relayer/sponsor or the
 * GraphQL sponsorTransaction mutation) failed the sponsorship policy in
 * {@see \App\Domain\Relayer\Services\SponsorshipRequestValidator}.
 *
 * Client-safe: the message never contains provider data, so GraphQL
 * returns it as-is.
 */
class SponsorshipRequestRejectedException extends RuntimeException implements ClientAware
{
    public const CODE_DISABLED = 'ERR_RELAYER_300';

    public const CODE_SENDER_NOT_OWNED = 'ERR_RELAYER_301';

    public const CODE_CALLDATA_NOT_ALLOWED = 'ERR_RELAYER_302';

    public const CODE_NETWORK_NOT_ALLOWED = 'ERR_RELAYER_303';

    public const CODE_INIT_CODE_NOT_ALLOWED = 'ERR_RELAYER_304';

    public const CODE_DAILY_LIMIT_REACHED = 'ERR_RELAYER_305';

    public const CODE_TEMPORARILY_UNAVAILABLE = 'ERR_RELAYER_306';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatusCode,
    ) {
        parent::__construct($message);
    }

    public static function disabled(): self
    {
        return new self('Transaction sponsorship is not available.', self::CODE_DISABLED, 404);
    }

    public static function senderNotOwned(): self
    {
        return new self(
            'The sender must be a smart account or wallet address registered to your account on this network.',
            self::CODE_SENDER_NOT_OWNED,
            403,
        );
    }

    public static function callDataNotAllowed(): self
    {
        return new self(
            'Only USDC/USDT transfers from your smart account can be sponsored.',
            self::CODE_CALLDATA_NOT_ALLOWED,
            422,
        );
    }

    public static function networkNotAllowed(string $network): self
    {
        return new self(
            "Sponsored transactions are not available on '{$network}'.",
            self::CODE_NETWORK_NOT_ALLOWED,
            422,
        );
    }

    public static function initCodeNotAllowed(): self
    {
        return new self(
            'The init code does not match the deployment data of your smart account.',
            self::CODE_INIT_CODE_NOT_ALLOWED,
            422,
        );
    }

    /**
     * The caller's share of the daily sponsorship budget is used up.
     */
    public static function dailyLimitReached(): self
    {
        return new self(
            "You've reached today's sponsored transaction limit. It resets at 00:00 UTC.",
            self::CODE_DAILY_LIMIT_REACHED,
            429,
        );
    }

    /**
     * The platform-wide daily sponsorship budget is used up.
     */
    public static function temporarilyUnavailable(): self
    {
        return new self(
            'Sponsored transactions are temporarily paused. Please try again later.',
            self::CODE_TEMPORARILY_UNAVAILABLE,
            429,
        );
    }

    public function isClientSafe(): bool
    {
        return true;
    }
}
