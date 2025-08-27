<?php

declare(strict_types=1);

namespace App\Domain\Product\ValueObjects;

use InvalidArgumentException;

class Price
{
    public function __construct(
        private float $amount,
        private string $currency,
        private string $type = 'fixed', // fixed, percentage, tiered
        private string $interval = 'one_time', // one_time, monthly, yearly
        private array $tiers = [],
        private array $discounts = []
    ) {
        $this->validateAmount();
        $this->validateCurrency();
        $this->validateType();
    }

    private function validateAmount(): void
    {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Price amount cannot be negative');
        }
    }

    private function validateCurrency(): void
    {
        $validCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'];
        if (! in_array($this->currency, $validCurrencies, true)) {
            throw new InvalidArgumentException("Invalid currency: {$this->currency}");
        }
    }

    private function validateType(): void
    {
        $validTypes = ['fixed', 'percentage', 'tiered'];
        if (! in_array($this->type, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid price type: {$this->type}");
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currency: $data['currency'],
            type: $data['type'] ?? 'fixed',
            interval: $data['interval'] ?? 'one_time',
            tiers: $data['tiers'] ?? [],
            discounts: $data['discounts'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'amount'    => $this->amount,
            'currency'  => $this->currency,
            'type'      => $this->type,
            'interval'  => $this->interval,
            'tiers'     => $this->tiers,
            'discounts' => $this->discounts,
        ];
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function isRecurring(): bool
    {
        return ! in_array($this->interval, ['one_time', 'once'], true);
    }

    public function getFormattedAmount(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CHF' => 'CHF',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency;

        if ($this->type === 'percentage') {
            return number_format($this->amount, 2) . '%';
        }

        return $symbol . number_format($this->amount, 2);
    }

    public function calculateTotal(int $quantity = 1, ?string $discountCode = null): float
    {
        $baseAmount = $this->amount * $quantity;

        if ($this->type === 'tiered' && ! empty($this->tiers)) {
            $baseAmount = $this->calculateTieredPrice($quantity);
        }

        if ($discountCode && isset($this->discounts[$discountCode])) {
            $discount = $this->discounts[$discountCode];
            if ($discount['type'] === 'percentage') {
                $baseAmount *= (1 - $discount['value'] / 100);
            } else {
                $baseAmount -= $discount['value'];
            }
        }

        return max(0, $baseAmount);
    }

    private function calculateTieredPrice(int $quantity): float
    {
        $total = 0;
        $remaining = $quantity;

        foreach ($this->tiers as $tier) {
            $tierQuantity = min($remaining, $tier['max'] - ($tier['min'] - 1));
            $total += $tierQuantity * $tier['price'];
            $remaining -= $tierQuantity;

            if ($remaining <= 0) {
                break;
            }
        }

        return $total;
    }
}
