<?php

declare(strict_types=1);

namespace Tests\MultiConnection\HyperSwitch;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Models\User;
use Illuminate\Support\Str;

// The HyperSwitch webhook does its idempotency bookkeeping
// (processed_webhook_events + hyperswitch_deposit_intents) on the DEFAULT
// connection, then credits the balance and completes the aggregate on the
// TENANT connection. If those tenant-connection writes were ever wrapped in the
// default-connection transaction, this would self-deadlock and time out at
// innodb_lock_wait_timeout (see the CLAUDE.md multi-connection pitfall).
it('completes a HyperSwitch deposit (credit + aggregate) without deadlock under real multi-session topology', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);

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

    HyperSwitchDepositIntent::create([
        'hyperswitch_payment_id' => $paymentId,
        'deposit_uuid'           => $depositUuid,
        'account_uuid'           => $account->uuid,
        'user_uuid'              => $user->uuid,
        'amount_cents'           => 30_000,
        'currency'               => 'USD',
        'status'                 => HyperSwitchDepositIntent::STATUS_PENDING,
    ]);

    $this->postJson('/api/webhooks/hyperswitch', [
        'event_type' => 'payment_succeeded',
        'event_id'   => 'evt_mc_' . uniqid(),
        'content'    => ['object' => [
            'payment_id' => $paymentId,
            'amount'     => 30_000,
            'currency'   => 'USD',
        ]],
    ])->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(30_000)
        ->and(HyperSwitchDepositIntent::where('hyperswitch_payment_id', $paymentId)->value('status'))
        ->toBe(HyperSwitchDepositIntent::STATUS_COMPLETED);
});
