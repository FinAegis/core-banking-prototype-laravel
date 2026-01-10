<?php

namespace App\Domain\Account\Aggregates;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Events\TransferThresholdReached;
use App\Domain\Account\Repositories\TransferRepository;
use App\Domain\Account\Repositories\TransferSnapshotRepository;
use App\Domain\Account\Utils\ValidatesHash;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class TransferAggregate extends AggregateRoot
{
    use ValidatesHash;

    public const int    COUNT_THRESHOLD = 1000;

    public function __construct(
        public int $count = 0,
    ) {
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): TransferRepository
    {
        return app()->make(
            abstract: TransferRepository::class
        );
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): TransferSnapshotRepository
    {
        return app()->make(
            abstract: TransferSnapshotRepository::class
        );
    }

    /**
     * @return $this
     */
    public function transfer(AccountUuid $from, AccountUuid $to, Money $money): static
    {
        $this->recordThat(
            domainEvent: new MoneyTransferred(
                from: $from,
                to: $to,
                money: $money,
                hash: $this->generateHash($money)
            )
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function applyMoneyTransferred(MoneyTransferred $event): static
    {
        $this->validateHash(
            hash: $event->hash,
            money: $event->money
        );

        if (++$this->count >= self::COUNT_THRESHOLD) {
            $this->recordThat(
                domainEvent: new TransferThresholdReached()
            );
            $this->count = 0;
        }

        $this->storeHash($event->hash);

        return $this;
    }

    /**
     * Get the aggregate state for snapshots.
     */
    protected function getState(): array
    {
        return [
            'count' => $this->count,
        ];
    }

    /**
     * Restore the aggregate state from snapshot.
     */
    protected function useState(array $state): void
    {
        $this->count = $state['count'] ?? 0;
    }
}
