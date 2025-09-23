<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\HasMoney;
use App\Domain\Account\Events\MoneySubtracted;
use App\Domain\Account\Repositories\TurnoverRepository;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UpdateTurnover
{
    public function __construct(
        protected TurnoverRepository $turnoverRepository,
    ) {
    }

    public function __invoke(HasMoney&ShouldBeStored $event): void
    {
        $amount = $event instanceof MoneySubtracted
            ? $event->getMoney()->invert()->getAmount()
            : $event->getMoney()->getAmount();

        $this->turnoverRepository->incrementForDateById(
            Carbon::now(),
            $event->aggregateRootUuid(),
            $amount
        );
    }
}
