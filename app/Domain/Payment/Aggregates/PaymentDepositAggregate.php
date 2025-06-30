<?php

namespace App\Domain\Payment\Aggregates;

use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Events\DepositInitiated;
use App\Domain\Payment\Events\DepositCompleted;
use App\Domain\Payment\Events\DepositFailed;
use App\Domain\Payment\Repositories\PaymentDepositRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class PaymentDepositAggregate extends AggregateRoot
{
    protected string $depositStatus = 'pending';
    protected ?string $transactionId = null;
    
    /**
     * @return PaymentDepositRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): PaymentDepositRepository
    {
        return app()->make(
            abstract: PaymentDepositRepository::class
        );
    }

    /**
     * Initiate a new deposit
     */
    public function initiateDeposit(StripeDeposit $deposit): static
    {
        $this->recordThat(
            domainEvent: new DepositInitiated(
                accountUuid: $deposit->getAccountUuid(),
                amount: $deposit->getAmount(),
                currency: $deposit->getCurrency(),
                reference: $deposit->getReference(),
                externalReference: $deposit->getExternalReference(),
                paymentMethod: $deposit->getPaymentMethod(),
                paymentMethodType: $deposit->getPaymentMethodType(),
                metadata: $deposit->getMetadata()
            )
        );

        return $this;
    }

    /**
     * Complete the deposit
     */
    public function completeDeposit(string $transactionId): static
    {
        if ($this->depositStatus !== 'pending') {
            throw new \Exception('Cannot complete deposit that is not pending');
        }

        $this->recordThat(
            domainEvent: new DepositCompleted(
                transactionId: $transactionId,
                completedAt: now()
            )
        );

        return $this;
    }

    /**
     * Fail the deposit
     */
    public function failDeposit(string $reason): static
    {
        if ($this->depositStatus !== 'pending') {
            throw new \Exception('Cannot fail deposit that is not pending');
        }

        $this->recordThat(
            domainEvent: new DepositFailed(
                reason: $reason,
                failedAt: now()
            )
        );

        return $this;
    }

    /**
     * Apply deposit initiated event
     */
    protected function applyDepositInitiated(DepositInitiated $event): void
    {
        $this->depositStatus = 'pending';
    }

    /**
     * Apply deposit completed event
     */
    protected function applyDepositCompleted(DepositCompleted $event): void
    {
        $this->depositStatus = 'completed';
        $this->transactionId = $event->transactionId;
    }

    /**
     * Apply deposit failed event
     */
    protected function applyDepositFailed(DepositFailed $event): void
    {
        $this->depositStatus = 'failed';
    }
}