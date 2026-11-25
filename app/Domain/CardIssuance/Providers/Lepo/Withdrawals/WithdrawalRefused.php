<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo\Withdrawals;

use RuntimeException;

/**
 * A withdrawal request that was refused, with the stable error code the API
 * answers and the context fields that go with it. The message is ours and
 * never carries a provider payload.
 */
final class WithdrawalRefused extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(private readonly string $errorCode, private readonly array $context = [])
    {
        parent::__construct("The withdrawal was refused ({$errorCode}).");
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function because(string $errorCode, array $context = []): self
    {
        return new self($errorCode, $context);
    }

    public static function providerUnavailable(): self
    {
        return new self('ERR_CARDS_016');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
