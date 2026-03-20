<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\DataObjects;

use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;

final class VisaCliPaymentResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $paymentReference,
        public readonly VisaCliPaymentStatus $status,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $url,
        public readonly ?string $cardLast4 = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payment_reference' => $this->paymentReference,
            'status'            => $this->status->value,
            'amount_cents'      => $this->amountCents,
            'currency'          => $this->currency,
            'url'               => $this->url,
            'card_last4'        => $this->cardLast4,
            'error_message'     => $this->errorMessage,
            'metadata'          => $this->metadata,
        ];
    }
}
