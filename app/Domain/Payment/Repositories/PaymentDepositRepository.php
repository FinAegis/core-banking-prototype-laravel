<?php

namespace App\Domain\Payment\Repositories;

use App\Models\PaymentDeposit;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class PaymentDepositRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws \Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = PaymentDeposit::class
    )
    {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}