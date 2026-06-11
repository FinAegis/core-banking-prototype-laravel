<?php

/**
 * Targeted operator actions on the money-rail admin resources.
 *
 * Bridge actions exercise the REAL wrapped services (BridgeDeveloperFeeSync /
 * BridgePostKycHandler are final, so they can't be Mockery-mocked) against
 * Http::fake — no Bridge HTTP ever leaves the test. Outbox re-dispatch is
 * asserted via Bus::fake; the HyperSwitch reconciliation action moves no
 * money by design, so it is asserted purely on persisted state.
 */

declare(strict_types=1);

use App\Domain\Compliance\Kyc\Events\Broadcast\BridgeVirtualAccountReady;
use App\Domain\Compliance\Kyc\Services\BridgeDeveloperFeeSync;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Subscription\Jobs\ProjectRevenueOutbox;
use App\Filament\Admin\Resources\BridgeCustomerResource\Pages\ListBridgeCustomers;
use App\Filament\Admin\Resources\HyperSwitchDepositIntentResource\Pages\ListHyperSwitchDepositIntents;
use App\Filament\Admin\Resources\RevenueOutboxEventResource\Pages\ListRevenueOutboxEvents;
use App\Infrastructure\Bridge\BridgeClient;
use App\Models\User;
use Database\Factories\Domain\Compliance\Kyc\Models\BridgeCustomerFactory;
use Database\Factories\Domain\Payment\Models\HyperSwitchDepositIntentFactory;
use Database\Factories\Domain\Subscription\Models\RevenueOutboxEventFactory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setServingStatus(true);

    $this->admin = User::factory()->withAdminRole()->createOne();
    $this->actingAs($this->admin);

    config([
        'kyc.providers.bridge.api_key'  => 'sk_test',
        'kyc.providers.bridge.base_url' => 'https://api.bridge.xyz',
    ]);
});

describe('BridgeCustomer syncDevFee action', function () {
    it('invokes BridgeDeveloperFeeSync and updates the local dev fee', function (): void {
        // The service is final — swap the container binding for a real
        // instance with a deterministic tier resolver, and fake Bridge HTTP.
        app()->bind(BridgeDeveloperFeeSync::class, function (): BridgeDeveloperFeeSync {
            return new BridgeDeveloperFeeSync(
                BridgeClient::fromConfig(),
                static fn (User $user): string => 'pro',
            );
        });

        Http::fake([
            'api.bridge.xyz/*' => Http::response(['id' => 'cust_sync_admin', 'developer_fee_bps' => 0]),
        ]);

        $user = User::factory()->createOne();
        $customer = BridgeCustomerFactory::new()->approved()->createOne([
            'user_id'            => $user->id,
            'bridge_customer_id' => 'cust_sync_admin',
            'developer_fee_bps'  => 75,
        ]);

        Livewire::test(ListBridgeCustomers::class)
            ->callTableAction('syncDevFee', $customer)
            ->assertHasNoTableActionErrors();

        expect($customer->refresh()->developer_fee_bps)->toBe(0);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/v0/customers/cust_sync_admin')
                && $request['developer_fee_bps'] === 0;
        });
    });

    it('does not call Bridge when the dev fee already matches the tier', function (): void {
        app()->bind(BridgeDeveloperFeeSync::class, function (): BridgeDeveloperFeeSync {
            return new BridgeDeveloperFeeSync(
                BridgeClient::fromConfig(),
                static fn (User $user): string => 'free',
            );
        });

        Http::fake();

        $customer = BridgeCustomerFactory::new()->approved()->createOne([
            'developer_fee_bps' => 75,
        ]);

        Livewire::test(ListBridgeCustomers::class)
            ->callTableAction('syncDevFee', $customer)
            ->assertHasNoTableActionErrors();

        expect($customer->refresh()->developer_fee_bps)->toBe(75);
        Http::assertSentCount(0);
    });
});

describe('BridgeCustomer retryVaProvisioning action', function () {
    it('provisions the virtual account via BridgePostKycHandler', function (): void {
        Event::fake([BridgeVirtualAccountReady::class]);

        Http::fake([
            'api.bridge.xyz/v0/customers/cust_va_admin/virtual_accounts' => Http::response([
                'id'          => 'va_admin_retry_1',
                'destination' => [
                    'currency'     => 'usdc',
                    'payment_rail' => 'polygon',
                    'to_address'   => '0xadminretry',
                ],
                'source'          => ['iban' => 'GB29NWBK60161331926819'],
                'supported_rails' => ['ach', 'sepa'],
            ], 201),
        ]);

        $user = User::factory()->createOne();

        App\Domain\Account\Models\BlockchainAddress::create([
            'user_uuid'  => $user->uuid,
            'chain'      => 'polygon',
            'address'    => '0xadminretry',
            'public_key' => '0x' . str_repeat('a', 128),
        ]);

        $customer = BridgeCustomerFactory::new()->approved()->createOne([
            'user_id'            => $user->id,
            'bridge_customer_id' => 'cust_va_admin',
        ]);

        Livewire::test(ListBridgeCustomers::class)
            ->callTableAction('retryVaProvisioning', $customer)
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        expect($customer->virtual_account_id)->toBe('va_admin_retry_1')
            ->and($customer->supported_rails)->toBe(['ach', 'sepa']);

        Event::assertDispatched(BridgeVirtualAccountReady::class);
    });

    it('is hidden for customers that already have a virtual account', function (): void {
        $customer = BridgeCustomerFactory::new()->withVirtualAccount()->createOne();

        Livewire::test(ListBridgeCustomers::class)
            ->assertTableActionHidden('retryVaProvisioning', $customer);
    });
});

describe('RevenueOutbox redispatch action', function () {
    it('queues ProjectRevenueOutbox for a failed row', function (): void {
        Bus::fake([ProjectRevenueOutbox::class]);

        $row = RevenueOutboxEventFactory::new()->failed()->createOne();

        Livewire::test(ListRevenueOutboxEvents::class)
            ->callTableAction('redispatch', $row)
            ->assertHasNoTableActionErrors();

        Bus::assertDispatched(
            ProjectRevenueOutbox::class,
            fn (ProjectRevenueOutbox $job): bool => $job->rowId === (int) $row->id,
        );
    });

    it('is hidden for delivered rows', function (): void {
        Bus::fake([ProjectRevenueOutbox::class]);

        $delivered = RevenueOutboxEventFactory::new()->delivered()->createOne();

        Livewire::test(ListRevenueOutboxEvents::class)
            ->assertTableActionHidden('redispatch', $delivered);

        Bus::assertNotDispatched(ProjectRevenueOutbox::class);
    });
});

describe('HyperSwitch markReconciled action', function () {
    it('records the reconciliation audit trail without moving money', function (): void {
        $intent = HyperSwitchDepositIntentFactory::new()->completionFailed()->createOne();

        Livewire::test(ListHyperSwitchDepositIntents::class)
            ->callTableAction('markReconciled', $intent, data: [
                'reconciliation_note' => 'Manually credited via ledger adjustment #42.',
            ])
            ->assertHasNoTableActionErrors();

        $intent->refresh();

        expect($intent->status)->toBe(HyperSwitchDepositIntent::STATUS_RECONCILED)
            ->and($intent->reconciliation_note)->toBe('Manually credited via ledger adjustment #42.')
            ->and($intent->reconciled_by)->toBe($this->admin->email)
            ->and($intent->reconciled_at)->not->toBeNull();
    });

    it('requires a reconciliation note', function (): void {
        $intent = HyperSwitchDepositIntentFactory::new()->completionFailed()->createOne();

        Livewire::test(ListHyperSwitchDepositIntents::class)
            ->callTableAction('markReconciled', $intent, data: [
                'reconciliation_note' => '',
            ])
            ->assertHasTableActionErrors(['reconciliation_note' => 'required']);

        expect($intent->refresh()->status)->toBe(HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED);
    });

    it('is hidden for intents that are not completion_failed', function (): void {
        $completed = HyperSwitchDepositIntentFactory::new()->completed()->createOne();

        Livewire::test(ListHyperSwitchDepositIntents::class)
            ->removeTableFilters()
            ->assertTableActionHidden('markReconciled', $completed);
    });
});
