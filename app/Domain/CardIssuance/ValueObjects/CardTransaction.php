<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\ValueObjects;

use DateTimeImmutable;

/**
 * Represents a card transaction from the issuer.
 */
final readonly class CardTransaction
{
    public function __construct(
        public string $transactionId,
        public string $cardToken,
        public string $merchantName,
        public string $merchantCategory,
        public int $amountCents,
        public string $currency,
        public string $status,
        public DateTimeImmutable $timestamp,
    ) {
    }

    /**
     * Get the transaction amount as a decimal.
     */
    public function getAmountDecimal(): float
    {
        return $this->amountCents / 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->transactionId,
            'amount'    => $this->getAmountDecimal(),
            'currency'  => $this->currency,
            'merchant'  => $this->merchantName,
            'category'  => $this->merchantCategory,
            'status'    => $this->status,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
