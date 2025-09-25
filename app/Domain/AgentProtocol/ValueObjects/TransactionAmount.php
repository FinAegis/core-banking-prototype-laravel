<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing a transaction amount with currency.
 * Immutable by design.
 */
final class TransactionAmount
{
    private float $amount;

    private string $currency;

    public function __construct(float $amount, string $currency = 'USD')
    {
        $this->validateAmount($amount);
        $this->validateCurrency($currency);

        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    private function validateAmount(float $amount): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Transaction amount cannot be negative');
        }

        if ($amount > 999999999.99) {
            throw new InvalidArgumentException('Transaction amount exceeds maximum limit');
        }
    }

    private function validateCurrency(string $currency): void
    {
        if (empty($currency)) {
            throw new InvalidArgumentException('Currency cannot be empty');
        }

        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code');
        }

        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'];
        if (! in_array(strtoupper($currency), $supportedCurrencies, true)) {
            throw new InvalidArgumentException("Currency {$currency} is not supported");
        }
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get amount in cents/minor units.
     */
    public function getAmountInCents(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Add another amount (must be same currency).
     */
    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add amounts with different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another amount (must be same currency).
     */
    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot subtract amounts with different currencies');
        }

        $result = $this->amount - $other->amount;
        if ($result < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self($result, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplication factor cannot be negative');
        }

        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Check if two amounts have the same currency.
     */
    public function isSameCurrency(self $other): bool
    {
        return $this->currency === $other->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isGreaterThan(self $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot compare amounts with different currencies');
        }

        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot compare amounts with different currencies');
        }

        return $this->amount < $other->amount;
    }

    public function toString(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create from array for reconstitution.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['amount'] ?? 0),
            $data['currency'] ?? 'USD'
        );
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        return [
            'amount'   => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
