<?php

declare(strict_types=1);

namespace App\Domain\Asset\Aggregates;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Utils\ValidatesHash;
use App\Domain\Asset\Events\AssetTransferCompleted;
use App\Domain\Asset\Events\AssetTransferFailed;
use App\Domain\Asset\Events\AssetTransferInitiated;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AssetTransferAggregate extends AggregateRoot
{
    use ValidatesHash;

    private ?AccountUuid $fromAccountUuid = null;

    private ?AccountUuid $toAccountUuid = null;

    private ?string $fromAssetCode = null;

    private ?string $toAssetCode = null;

    private ?Money $fromAmount = null;

    private ?Money $toAmount = null;

    private ?float $exchangeRate = null;

    private ?Hash $hash = null;

    private ?string $status = null;

    private ?string $failureReason = null;

    /**
     * Initiate a transfer between assets.
     */
    public function initiate(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount,
        Money $toAmount,
        ?float $exchangeRate = null,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        $hash = $this->generateHash($fromAmount);

        $this->recordThat(
            new AssetTransferInitiated(
                fromAccountUuid: $fromAccountUuid,
                toAccountUuid: $toAccountUuid,
                fromAssetCode: $fromAssetCode,
                toAssetCode: $toAssetCode,
                fromAmount: $fromAmount,
                toAmount: $toAmount,
                exchangeRate: $exchangeRate,
                hash: $hash,
                description: $description,
                metadata: $metadata
            )
        );

        return $this;
    }

    /**
     * Complete the transfer.
     */
    public function complete(
        ?string $transferId = null,
        ?array $metadata = null
    ): self {
        if ($this->status !== 'initiated') {
            throw new InvalidArgumentException('Transfer must be initiated before it can be completed');
        }

        $this->recordThat(
            new AssetTransferCompleted(
                fromAccountUuid: $this->fromAccountUuid,
                toAccountUuid: $this->toAccountUuid,
                fromAssetCode: $this->fromAssetCode,
                toAssetCode: $this->toAssetCode,
                fromAmount: $this->fromAmount,
                toAmount: $this->toAmount,
                hash: $this->hash,
                transferId: $transferId,
                metadata: $metadata
            )
        );

        return $this;
    }

    /**
     * Fail the transfer.
     */
    public function fail(
        string $reason,
        ?string $transferId = null,
        ?array $metadata = null
    ): self {
        if ($this->status !== 'initiated') {
            throw new InvalidArgumentException('Transfer must be initiated before it can fail');
        }

        $this->recordThat(
            new AssetTransferFailed(
                fromAccountUuid: $this->fromAccountUuid,
                toAccountUuid: $this->toAccountUuid,
                fromAssetCode: $this->fromAssetCode,
                toAssetCode: $this->toAssetCode,
                fromAmount: $this->fromAmount,
                reason: $reason,
                hash: $this->hash,
                transferId: $transferId,
                metadata: $metadata
            )
        );

        return $this;
    }

    /**
     * Apply asset transfer initiated event.
     */
    public function applyAssetTransferInitiated(AssetTransferInitiated $event): void
    {
        $this->fromAccountUuid = $event->fromAccountUuid;
        $this->toAccountUuid = $event->toAccountUuid;
        $this->fromAssetCode = $event->fromAssetCode;
        $this->toAssetCode = $event->toAssetCode;
        $this->fromAmount = $event->fromAmount;
        $this->toAmount = $event->toAmount;
        $this->exchangeRate = $event->exchangeRate;
        $this->hash = $event->hash;
        $this->status = 'initiated';
    }

    /**
     * Apply asset transfer completed event.
     */
    public function applyAssetTransferCompleted(AssetTransferCompleted $event): void
    {
        $this->status = 'completed';
    }

    /**
     * Apply asset transfer failed event.
     */
    public function applyAssetTransferFailed(AssetTransferFailed $event): void
    {
        $this->status = 'failed';
        $this->failureReason = $event->reason;
    }

    /**
     * Get the from account UUID.
     */
    public function getFromAccountUuid(): ?AccountUuid
    {
        return $this->fromAccountUuid;
    }

    /**
     * Get the to account UUID.
     */
    public function getToAccountUuid(): ?AccountUuid
    {
        return $this->toAccountUuid;
    }

    /**
     * Get the from asset code.
     */
    public function getFromAssetCode(): ?string
    {
        return $this->fromAssetCode;
    }

    /**
     * Get the to asset code.
     */
    public function getToAssetCode(): ?string
    {
        return $this->toAssetCode;
    }

    /**
     * Get the from amount.
     */
    public function getFromAmount(): ?Money
    {
        return $this->fromAmount;
    }

    /**
     * Get the to amount.
     */
    public function getToAmount(): ?Money
    {
        return $this->toAmount;
    }

    /**
     * Get the exchange rate.
     */
    public function getExchangeRate(): ?float
    {
        return $this->exchangeRate;
    }

    /**
     * Get the status.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Get the failure reason.
     */
    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * Check if this is a same-asset transfer.
     */
    public function isSameAssetTransfer(): bool
    {
        return $this->fromAssetCode === $this->toAssetCode;
    }

    /**
     * Check if this is a cross-asset transfer (exchange).
     */
    public function isCrossAssetTransfer(): bool
    {
        return $this->fromAssetCode !== $this->toAssetCode;
    }
}
