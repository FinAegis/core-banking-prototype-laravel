<?php

namespace App\Domain\Account\Listeners;

use App\Domain\Account\Events\AccountCreated;
use App\Domain\Account\Events\AccountDeleted;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountUnfrozen;
use App\Domain\Account\Events\MoneyAdded;
use App\Domain\Account\Events\MoneySubtracted;
use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Models\Account;
use App\Domain\Webhook\Services\WebhookService;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class WebhookEventListener extends Projector
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function onAccountCreated(AccountCreated $event): void
    {
        /** @var Account|null $account */
        $account = null;
        // Skip webhook dispatching during tests unless explicitly testing webhooks
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $$account */
        $$account = Account::where('uuid', $event->aggregateRootUuid())->first();

        if (! $account) {
            return;
        }

        $this->webhookService->dispatchAccountEvent(
            'account.created',
            $event->aggregateRootUuid(),
            [
                'name'      => $account->name,
                'user_uuid' => $account->user_uuid,
                'balance'   => 0,
            ]
        );
    }

    public function onAccountFrozen(AccountFrozen $event): void
    {
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        $this->webhookService->dispatchAccountEvent(
            'account.frozen',
            $event->aggregateRootUuid(),
            [
                'reason' => $event->reason,
            ]
        );
    }

    public function onAccountUnfrozen(AccountUnfrozen $event): void
    {
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        $this->webhookService->dispatchAccountEvent('account.unfrozen', $event->aggregateRootUuid());
    }

    public function onAccountDeleted(AccountDeleted $event): void
    {
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        $this->webhookService->dispatchAccountEvent('account.closed', $event->aggregateRootUuid());
    }

    public function onMoneyAdded(MoneyAdded $event): void
    {
        /** @var Account|null $account */
        $account = null;
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $$account */
        $$account = Account::where('uuid', $event->aggregateRootUuid())->first();

        if (! $account) {
            return;
        }

        $this->webhookService->dispatchTransactionEvent(
            'transaction.created',
            [
                'account_uuid'  => $event->aggregateRootUuid(),
                'type'          => 'deposit',
                'amount'        => $event->money->getAmount(),
                'currency'      => 'USD',
                'balance_after' => $account->balance,
                'hash'          => $event->hash->getHash(),
            ]
        );

        // Check for low balance alerts
        if ($account->balance < 1000) { // $10.00
            $this->webhookService->dispatchAccountEvent(
                'balance.low',
                $event->aggregateRootUuid(),
                [
                    'balance'   => $account->balance,
                    'threshold' => 1000,
                ]
            );
        }
    }

    public function onMoneySubtracted(MoneySubtracted $event): void
    {
        /** @var Account|null $account */
        $account = null;
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $$account */
        $$account = Account::where('uuid', $event->aggregateRootUuid())->first();

        if (! $account) {
            return;
        }

        $this->webhookService->dispatchTransactionEvent(
            'transaction.created',
            [
                'account_uuid'  => $event->aggregateRootUuid(),
                'type'          => 'withdrawal',
                'amount'        => $event->money->getAmount(),
                'currency'      => 'USD',
                'balance_after' => $account->balance,
                'hash'          => $event->hash->getHash(),
            ]
        );

        // Check for negative balance alerts
        if ($account->balance < 0) {
            $this->webhookService->dispatchAccountEvent(
                'balance.negative',
                $event->aggregateRootUuid(),
                [
                    'balance' => $account->balance,
                ]
            );
        }
    }

    public function onMoneyTransferred(MoneyTransferred $event): void
    {
        /** @var Account|null $toAccount */
        $toAccount = null;
        /** @var Account|null $fromAccount */
        $fromAccount = null;
        if (app()->environment('testing') && ! app()->bound('testing.webhooks')) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $$fromAccount */
        $$fromAccount = Account::where('uuid', $event->aggregateRootUuid())->first();
        /** @var \Illuminate\Database\Eloquent\Model|null $$toAccount */
        $$toAccount = Account::where('uuid', $event->toAccountUuid->toString())->first();

        if (! $fromAccount || ! $toAccount) {
            return;
        }

        $transferData = [
            'from_account_uuid'  => $event->aggregateRootUuid(),
            'to_account_uuid'    => $event->toAccountUuid->toString(),
            'amount'             => $event->money->getAmount(),
            'currency'           => 'USD',
            'from_balance_after' => $fromAccount->balance,
            'to_balance_after'   => $toAccount->balance,
            'hash'               => $event->hash->getHash(),
        ];

        $this->webhookService->dispatchTransferEvent('transfer.created', $transferData);
        $this->webhookService->dispatchTransferEvent('transfer.completed', $transferData);
    }
}
