<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Listeners;

use App\Domain\Custodian\Events\CustodianHealthChanged;
use App\Domain\Custodian\Services\BankAlertingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Log;
use Throwable;

class HandleCustodianHealthChange implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly BankAlertingService $alertingService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(CustodianHealthChanged $event): void
    {
        $this->alertingService->handleHealthChange($event);
    }

    /**
     * Handle a job failure.
     */
    public function failed(CustodianHealthChanged $event, Throwable $exception): void
    {
        Log::error(
            'Failed to handle custodian health change',
            [
            'custodian' => $event->custodian,
            'error'     => $exception->getMessage(),
            ]
        );
    }
}
