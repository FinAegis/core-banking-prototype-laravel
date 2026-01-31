<?php

namespace App\Providers;

use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Listeners\CreateAccountForNewUser;
use App\Domain\Mobile\Listeners\LogMobileAuditEventListener;
use App\Domain\Mobile\Listeners\SendSecurityAlertListener;
use App\Domain\Mobile\Listeners\SendTransactionPushNotificationListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            CreateAccountForNewUser::class,
        ],
        MoneyTransferred::class => [
            SendTransactionPushNotificationListener::class,
        ],
    ];

    /**
     * The subscribers to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        SendSecurityAlertListener::class,
        LogMobileAuditEventListener::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
