<?php

namespace App\Domain\Payment\Aggregates;

use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Events\WithdrawalInitiated;
use App\Domain\Payment\Events\WithdrawalCompleted;
use App\Domain\Payment\Events\WithdrawalFailed;
use App\Domain\Payment\Repositories\PaymentWithdrawalRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class PaymentWithdrawalAggregate extends AggregateRoot
{
    protected string $withdrawalStatus = 'pending';
    protected ?string $transactionId = null;
    
    /**
     * @return PaymentWithdrawalRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): PaymentWithdrawalRepository
    {
        return app()->make(
            abstract: PaymentWithdrawalRepository::class
        );
    }

    /**
     * Initiate a new withdrawal
     */
    public function initiateWithdrawal(BankWithdrawal $withdrawal): static
    {
        $this->recordThat(
            domainEvent: new WithdrawalInitiated(
                accountUuid: $withdrawal->getAccountUuid(),
                amount: $withdrawal->getAmount(),
                currency: $withdrawal->getCurrency(),
                reference: $withdrawal->getReference(),
                bankAccountNumber: $withdrawal->getAccountNumber(),
                bankRoutingNumber: $withdrawal->getRoutingNumber(),
                bankAccountName: $withdrawal->getAccountHolderName(),
                metadata: $withdrawal->getMetadata()
            )
        );

        return $this;
    }

    /**
     * Complete the withdrawal
     */
    public function completeWithdrawal(string $transactionId): static
    {
        if ($this->withdrawalStatus !== 'pending') {
            throw new \Exception('Cannot complete withdrawal that is not pending');
        }

        $this->recordThat(
            domainEvent: new WithdrawalCompleted(
                transactionId: $transactionId,
                completedAt: now()
            )
        );

        return $this;
    }

    /**
     * Fail the withdrawal
     */
    public function failWithdrawal(string $reason): static
    {
        if ($this->withdrawalStatus !== 'pending') {
            throw new \Exception('Cannot fail withdrawal that is not pending');
        }

        $this->recordThat(
            domainEvent: new WithdrawalFailed(
                reason: $reason,
                failedAt: now()
            )
        );

        return $this;
    }

    /**
     * Apply withdrawal initiated event
     */
    protected function applyWithdrawalInitiated(WithdrawalInitiated $event): void
    {
        $this->withdrawalStatus = 'pending';
    }

    /**
     * Apply withdrawal completed event
     */
    protected function applyWithdrawalCompleted(WithdrawalCompleted $event): void
    {
        $this->withdrawalStatus = 'completed';
        $this->transactionId = $event->transactionId;
    }

    /**
     * Apply withdrawal failed event
     */
    protected function applyWithdrawalFailed(WithdrawalFailed $event): void
    {
        $this->withdrawalStatus = 'failed';
    }
}