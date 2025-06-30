<?php

declare(strict_types=1);

namespace App\Domain\Payment\DataObjects;

use App\Domain\Account\DataObjects\DataObject;
use JustSteveKing\DataObjects\Contracts\DataObjectContract;

final readonly class BankWithdrawal extends DataObject implements DataObjectContract
{
    public function __construct(
        private string $accountUuid,
        private int $amount,
        private string $currency,
        private string $reference,
        private string $bankName,
        private string $accountNumber,
        private string $accountHolderName,
        private ?string $routingNumber = null,
        private ?string $iban = null,
        private ?string $swift = null,
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

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountHolderName(): string
    {
        return $this->accountHolderName;
    }

    public function getRoutingNumber(): ?string
    {
        return $this->routingNumber;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function getSwift(): ?string
    {
        return $this->swift;
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
            'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber,
            'account_holder_name' => $this->accountHolderName,
            'routing_number' => $this->routingNumber,
            'iban' => $this->iban,
            'swift' => $this->swift,
            'metadata' => $this->metadata,
        ];
    }
}