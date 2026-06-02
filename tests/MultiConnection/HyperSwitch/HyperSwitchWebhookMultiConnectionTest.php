<?php

declare(strict_types=1);

namespace Tests\MultiConnection\HyperSwitch;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Services\AccountCreditService;
use App\Domain\Asset\Models\Asset;
use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Models\PaymentDeposit;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// The HyperSwitch webhook does its idempotency bookkeeping (processed_webhook_events
// + hyperswitch_deposit_intents) on the DEFAULT connection, then credits the balance
// and completes the aggregate on the TENANT connection. This test exercises that
// deadlock-sensitive tenant-side sequence DIRECTLY — like the AccountProvisioning
// multi-connection test calls its service directly, rather than through the HTTP
// kernel which re-juggles the harness's purged connection pools. If
// AccountCreditService ever wrapped its tenant write in the default-connection
// transaction, this would self-deadlock and time out at innodb_lock_wait_timeout
// (see the CLAUDE.md multi-connection pitfall). The full webhook path (signature,
// claim, idempotency) is covered by tests/Feature/HyperSwitch.
it('credits + completes a deposit on the tenant connection without deadlock under real multi-session topology', function () {
    // account_balances.asset_code FKs to assets.code; the harness truncates
    // assets, so seed the currency the credit will reference.
    Asset::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

    $account = Account::factory()->create();
    $depositUuid = (string) Str::uuid();
    $paymentId = 'pay_mc_' . uniqid();

    PaymentDepositAggregate::retrieve($depositUuid)
        ->initiateDeposit(new StripeDeposit(
            accountUuid: $account->uuid,
            amount: 30_000,
            currency: 'USD',
            reference: 'DEP-MC',
            externalReference: $paymentId,
            paymentMethod: 'hyperswitch',
            paymentMethodType: 'card',
            metadata: ['processor' => 'hyperswitch'],
        ))
        ->persist();

    // Default-connection idempotency claim commits first (as the webhook does).
    DB::transaction(function () use ($paymentId): void {
        ProcessedWebhookEvent::firstOrCreate(
            ['provider' => 'hyperswitch', 'event_id' => 'evt_mc_' . $paymentId],
            ['event_type' => 'payment_succeeded', 'processed_at' => now()],
        );
    });

    // Then the tenant-side credit + aggregate completion — the exact sequence
    // HyperSwitchWebhookController::handlePaymentSucceeded runs after the claim,
    // invoked directly so a deadlock or error surfaces rather than being caught.
    app(AccountCreditService::class)->credit($account->uuid, 30_000, 'USD');
    PaymentDepositAggregate::retrieve($depositUuid)->completeDeposit('hs_' . $paymentId)->persist();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(30_000)
        ->and(PaymentDeposit::where('aggregate_uuid', $depositUuid)->where('event_class', 'deposit_completed')->exists())
        ->toBeTrue();
});
