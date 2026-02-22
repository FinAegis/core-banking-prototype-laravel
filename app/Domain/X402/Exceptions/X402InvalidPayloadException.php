<?php

declare(strict_types=1);

namespace App\Domain\X402\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an x402 payment payload cannot be decoded or is structurally invalid.
 */
class X402InvalidPayloadException extends InvalidArgumentException
{
    /**
     * The payload could not be decoded from base64 / JSON.
     */
    public static function invalidBase64(string $message = ''): self
    {
        $detail = $message !== '' ? ": {$message}" : '.';

        return new self("Invalid x402 base64 payload{$detail}");
    }

    /**
     * A required field is missing from the payload.
     */
    public static function missingField(string $field): self
    {
        return new self("Missing required x402 payload field: {$field}");
    }

    /**
     * A field has an invalid value.
     */
    public static function invalidField(string $field, string $reason): self
    {
        return new self("Invalid x402 payload field '{$field}': {$reason}");
    }
}
