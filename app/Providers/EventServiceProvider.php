<?php

namespace App\Providers;

use App\Domain\Account\Events\MoneyTransferred;
use App\Domain\Account\Listeners\CreateAccountForNewUser;
use App\Domain\AccountProvisioning\Support\SuppressNotificationsListener;
use App\Domain\CardIssuance\Events\CardProvisioned;
use App\Domain\Compliance\Events\KycVerificationCompleted;
use App\Domain\Mobile\Listeners\LogMobileAuditEventListener;
use App\Domain\Mobile\Listeners\SendSecurityAlertListener;
use App\Domain\Mobile\Listeners\SendTransactionPushNotificationListener;
use App\Domain\Privacy\Events\ProofOfInnocenceGenerated;
use App\Domain\Referral\Listeners\CompleteReferralOnKycApproval;
use App\Domain\Rewards\Listeners\TriggerQuestOnCardCreated;
use App\Domain\Rewards\Listeners\TriggerQuestOnLogin;
use App\Domain\Rewards\Listeners\TriggerQuestOnPayment;
use App\Domain\Rewards\Listeners\TriggerQuestOnShield;
use App\Domain\VisaCli\Events\VisaCliCardEnrolled;
use App\Domain\VisaCli\Listeners\SyncVisaCliCardToCardIssuance;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSending;

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
        Login::class => [
            TriggerQuestOnLogin::class,
        ],
        MoneyTransferred::class => [
            SendTransactionPushNotificationListener::class,
            TriggerQuestOnPayment::class,
        ],
        KycVerificationCompleted::class => [
            CompleteReferralOnKycApproval::class,
        ],
        CardProvisioned::class => [
            TriggerQuestOnCardCreated::class,
        ],
        ProofOfInnocenceGenerated::class => [
            TriggerQuestOnShield::class,
        ],
        VisaCliCardEnrolled::class => [
            SyncVisaCliCardToCardIssuance::class,
        ],
        NotificationSending::class => [
            SuppressNotificationsListener::class,
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
