<?php

/**
 * Money-rail admin resources — listing, read-only enforcement, and
 * ADMIN_MODULES gating for the Ramp / Subscriptions / Payments / Webhooks
 * operator surfaces (plus the re-homed Support inbox).
 */

declare(strict_types=1);

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Contact\Models\ContactSubmission;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Filament\Admin\Resources\BridgeCustomerResource;
use App\Filament\Admin\Resources\BridgeCustomerResource\Pages\ListBridgeCustomers;
use App\Filament\Admin\Resources\ContactSubmissionResource;
use App\Filament\Admin\Resources\ContactSubmissionResource\Pages\ListContactSubmissions;
use App\Filament\Admin\Resources\HyperSwitchDepositIntentResource;
use App\Filament\Admin\Resources\HyperSwitchDepositIntentResource\Pages\ListHyperSwitchDepositIntents;
use App\Filament\Admin\Resources\IapSubscriptionResource;
use App\Filament\Admin\Resources\IapSubscriptionResource\Pages\ListIapSubscriptions;
use App\Filament\Admin\Resources\ProcessedWebhookEventResource;
use App\Filament\Admin\Resources\ProcessedWebhookEventResource\Pages\ListProcessedWebhookEvents;
use App\Filament\Admin\Resources\RampSessionResource;
use App\Filament\Admin\Resources\RampSessionResource\Pages\ListRampSessions;
use App\Filament\Admin\Resources\RevenueEventResource;
use App\Filament\Admin\Resources\RevenueEventResource\Pages\ListRevenueEvents;
use App\Filament\Admin\Resources\RevenueOutboxEventResource;
use App\Filament\Admin\Resources\RevenueOutboxEventResource\Pages\ListRevenueOutboxEvents;
use App\Models\User;
use Database\Factories\Domain\Compliance\Kyc\Models\BridgeCustomerFactory;
use Database\Factories\Domain\Payment\Models\HyperSwitchDepositIntentFactory;
use Database\Factories\Domain\Subscription\Models\IapSubscriptionFactory;
use Database\Factories\Domain\Subscription\Models\ProcessedWebhookEventFactory;
use Database\Factories\Domain\Subscription\Models\RevenueEventFactory;
use Database\Factories\Domain\Subscription\Models\RevenueOutboxEventFactory;
use Database\Factories\RampSessionFactory;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setServingStatus(true);

    $this->actingAs(User::factory()->withAdminRole()->create());
});

describe('listing', function () {
    it('lists bridge customers for an admin', function (): void {
        $customers = BridgeCustomerFactory::new()->count(2)->create();

        Livewire::test(ListBridgeCustomers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($customers);
    });

    it('lists ramp sessions without exposing deposit instructions', function (): void {
        $session = RampSessionFactory::new()->withDepositInstructions()->createOne();

        Livewire::test(ListRampSessions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$session])
            // PII guard: account/routing numbers from the encrypted blob never render.
            ->assertDontSee('123456789')
            ->assertDontSee('021000021')
            ->assertDontSee('BRIDGE-TEST');
    });

    it('lists IAP subscriptions and filters by store', function (): void {
        $apple = IapSubscriptionFactory::new()->createOne(['store' => 'apple']);
        $google = IapSubscriptionFactory::new()->createOne(['store' => 'google']);

        Livewire::test(ListIapSubscriptions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$apple, $google])
            ->filterTable('store', 'apple')
            ->assertCanSeeTableRecords([$apple])
            ->assertCanNotSeeTableRecords([$google]);
    });

    it('lists revenue events with bcmath-formatted amounts', function (): void {
        $event = RevenueEventFactory::new()->createOne([
            'amount'              => 1234,
            'amount_decimals'     => 2,
            'amount_denomination' => 'EUR',
        ]);

        Livewire::test(ListRevenueEvents::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$event]);

        expect(RevenueEventResource::formatAmount($event->refresh()))->toBe('12.34 EUR');
    });

    it('lists revenue outbox events', function (): void {
        $rows = RevenueOutboxEventFactory::new()->count(2)->create();

        Livewire::test(ListRevenueOutboxEvents::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($rows);
    });

    it('defaults the HyperSwitch intent list to the completion_failed worklist', function (): void {
        $failed = HyperSwitchDepositIntentFactory::new()->completionFailed()->createOne();
        $pending = HyperSwitchDepositIntentFactory::new()->createOne();

        Livewire::test(ListHyperSwitchDepositIntents::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$pending])
            ->removeTableFilters()
            ->assertCanSeeTableRecords([$failed, $pending]);
    });

    it('lists processed webhook events and filters by provider', function (): void {
        $bridge = ProcessedWebhookEventFactory::new()->createOne(['provider' => 'bridge']);
        $hyperswitch = ProcessedWebhookEventFactory::new()->createOne(['provider' => 'hyperswitch']);

        Livewire::test(ListProcessedWebhookEvents::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bridge, $hyperswitch])
            ->filterTable('provider', 'bridge')
            ->assertCanSeeTableRecords([$bridge])
            ->assertCanNotSeeTableRecords([$hyperswitch]);
    });

    it('searches processed webhook events by event_id', function (): void {
        $needle = ProcessedWebhookEventFactory::new()->createOne(['event_id' => 'evt_needle_123']);
        $other = ProcessedWebhookEventFactory::new()->createOne(['event_id' => 'evt_other_456']);

        Livewire::test(ListProcessedWebhookEvents::class)
            ->searchTable('evt_needle_123')
            ->assertCanSeeTableRecords([$needle])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('lists contact submissions in the re-homed support inbox', function (): void {
        $submission = ContactSubmission::create([
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'subject' => 'technical',
            'message' => 'Something is broken.',
        ]);

        Livewire::test(ListContactSubmissions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$submission]);
    });
});

describe('read-only enforcement', function () {
    it('forbids create and edit on every money-rail resource', function (): void {
        $cases = [
            [BridgeCustomerResource::class, new BridgeCustomer()],
            [RampSessionResource::class, new App\Models\RampSession()],
            [IapSubscriptionResource::class, new App\Domain\Subscription\Models\IapSubscription()],
            [RevenueEventResource::class, new App\Domain\Subscription\Models\RevenueEvent()],
            [RevenueOutboxEventResource::class, new App\Domain\Subscription\Models\RevenueOutboxEvent()],
            [HyperSwitchDepositIntentResource::class, new HyperSwitchDepositIntent()],
            [ProcessedWebhookEventResource::class, new App\Domain\Subscription\Models\ProcessedWebhookEvent()],
        ];

        foreach ($cases as [$resource, $record]) {
            expect($resource::canCreate())->toBeFalse()
                ->and($resource::canEdit($record))->toBeFalse()
                ->and($resource::canDelete($record))->toBeFalse()
                ->and($resource::canDeleteAny())->toBeFalse();
        }
    });

    it('forbids create on the re-homed contact submission inbox', function (): void {
        expect(ContactSubmissionResource::canCreate())->toBeFalse();
    });
});

describe('module visibility gating', function () {
    afterEach(function (): void {
        config(['brand.admin_modules' => null]);
    });

    it('maps each resource to its expected navigation group', function (): void {
        expect(BridgeCustomerResource::getNavigationGroup())->toBe('Ramp')
            ->and(RampSessionResource::getNavigationGroup())->toBe('Ramp')
            ->and(IapSubscriptionResource::getNavigationGroup())->toBe('Subscriptions')
            ->and(RevenueEventResource::getNavigationGroup())->toBe('Subscriptions')
            ->and(RevenueOutboxEventResource::getNavigationGroup())->toBe('Subscriptions')
            ->and(HyperSwitchDepositIntentResource::getNavigationGroup())->toBe('Payments')
            ->and(ProcessedWebhookEventResource::getNavigationGroup())->toBe('Webhooks')
            ->and(ContactSubmissionResource::getNavigationGroup())->toBe('Support');
    });

    it('shows all money-rail resources when ADMIN_MODULES is unset', function (): void {
        config(['brand.admin_modules' => null]);

        expect(BridgeCustomerResource::shouldRegisterNavigation())->toBeTrue()
            ->and(IapSubscriptionResource::shouldRegisterNavigation())->toBeTrue()
            ->and(HyperSwitchDepositIntentResource::shouldRegisterNavigation())->toBeTrue()
            ->and(ProcessedWebhookEventResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('hides resources whose group is not in ADMIN_MODULES', function (): void {
        config(['brand.admin_modules' => ['Banking', 'System']]);

        expect(BridgeCustomerResource::shouldRegisterNavigation())->toBeFalse()
            ->and(RampSessionResource::shouldRegisterNavigation())->toBeFalse()
            ->and(IapSubscriptionResource::shouldRegisterNavigation())->toBeFalse()
            ->and(RevenueEventResource::shouldRegisterNavigation())->toBeFalse()
            ->and(RevenueOutboxEventResource::shouldRegisterNavigation())->toBeFalse()
            ->and(HyperSwitchDepositIntentResource::shouldRegisterNavigation())->toBeFalse()
            ->and(ProcessedWebhookEventResource::shouldRegisterNavigation())->toBeFalse()
            ->and(ContactSubmissionResource::shouldRegisterNavigation())->toBeFalse();
    });

    it('shows resources whose group is in ADMIN_MODULES', function (): void {
        config(['brand.admin_modules' => ['Ramp', 'Subscriptions', 'Payments', 'Webhooks', 'Support']]);

        expect(BridgeCustomerResource::shouldRegisterNavigation())->toBeTrue()
            ->and(RampSessionResource::shouldRegisterNavigation())->toBeTrue()
            ->and(IapSubscriptionResource::shouldRegisterNavigation())->toBeTrue()
            ->and(RevenueEventResource::shouldRegisterNavigation())->toBeTrue()
            ->and(RevenueOutboxEventResource::shouldRegisterNavigation())->toBeTrue()
            ->and(HyperSwitchDepositIntentResource::shouldRegisterNavigation())->toBeTrue()
            ->and(ProcessedWebhookEventResource::shouldRegisterNavigation())->toBeTrue()
            ->and(ContactSubmissionResource::shouldRegisterNavigation())->toBeTrue();
    });
});
