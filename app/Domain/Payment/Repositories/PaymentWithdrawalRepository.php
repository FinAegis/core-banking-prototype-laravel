<?php

namespace App\Domain\Payment\Repositories;

use App\Models\PaymentWithdrawal;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class PaymentWithdrawalRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws \Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = PaymentWithdrawal::class
    )
    {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}