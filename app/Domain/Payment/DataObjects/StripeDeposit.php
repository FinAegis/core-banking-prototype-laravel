<?php

declare(strict_types=1);

namespace App\Domain\Payment\DataObjects;

use App\Domain\Account\DataObjects\DataObject;
use JustSteveKing\DataObjects\Contracts\DataObjectContract;

final readonly class StripeDeposit extends DataObject implements DataObjectContract
{
    public function __construct(
        private string $accountUuid,
        private int $amount,
        private string $currency,
        private string $reference,
        private string $externalReference,
        private string $paymentMethod,
        private string $paymentMethodType,
        private array $metadata = []
    ) {}

    public function getAccountUuid(): string
    {
        return $this->accountUuid;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getExternalReference(): string
    {
        return $this->externalReference;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getPaymentMethodType(): string
    {
        return $this->paymentMethodType;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'account_uuid' => $this->accountUuid,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'external_reference' => $this->externalReference,
            'payment_method' => $this->paymentMethod,
            'payment_method_type' => $this->paymentMethodType,
            'metadata' => $this->metadata,
        ];
    }
}