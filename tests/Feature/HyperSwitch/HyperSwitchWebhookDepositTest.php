<?php

/**
 * Webhook-completion tests for the HyperSwitch deposit integration.
 *
 * A verified `payment_succeeded` webhook must credit the account (once),
 * complete the deposit aggregate, and be idempotent on replay. `payment_failed`
 * fails the aggregate without crediting. The amount credited is taken from the
 * stored intent — never the (untrusted) webhook payload.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Payment\Models\PaymentDeposit;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Support\Str;

/**
 * Seed an initiated deposit + a pending HyperSwitch intent.
 *
 * @return array{0: Account, 1: string} [account, depositUuid]
 */
function hsSeedDeposit(string $paymentId, int $amountCents = 10_000, string $currency = 'USD', string $status = HyperSwitchDepositIntent::STATUS_PENDING): array
{
    $account = Account::factory()->create();
    $depositUuid = (string) Str::uuid();

    PaymentDepositAggregate::retrieve($depositUuid)
        ->initiateDeposit(new StripeDeposit(
            accountUuid: $account->uuid,
            amount: $amountCents,
            currency: $currency,
            reference: 'DEP-' . $paymentId,
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
        'user_uuid'              => $account->user_uuid,
        'amount_cents'           => $amountCents,
        'currency'               => $currency,
        'status'                 => $status,
    ]);

    return [$account, $depositUuid];
}

/**
 * @return array<string, mixed>
 */
function hsPayload(string $eventType, string $paymentId, string $eventId, int $amount = 10_000, string $currency = 'USD'): array
{
    return [
        'event_type' => $eventType,
        'event_id'   => $eventId,
        'content'    => ['object' => [
            'payment_id' => $paymentId,
            'amount'     => $amount,
            'currency'   => $currency,
            'connector'  => 'stripe',
        ]],
    ];
}

it('credits the account and completes the deposit on payment_succeeded', function () {
    [$account, $depositUuid] = hsSeedDeposit('pay_ok', 25_000, 'USD');

    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_succeeded', 'pay_ok', 'evt_ok', 25_000, 'USD'))
        ->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(25_000)
        ->and(HyperSwitchDepositIntent::where('hyperswitch_payment_id', 'pay_ok')->value('status'))
        ->toBe(HyperSwitchDepositIntent::STATUS_COMPLETED)
        ->and(ProcessedWebhookEvent::where(['provider' => 'hyperswitch', 'event_id' => 'evt_ok'])->exists())
        ->toBeTrue()
        ->and(PaymentDeposit::where('aggregate_uuid', $depositUuid)->where('event_class', 'deposit_completed')->exists())
        ->toBeTrue();
});

it('is idempotent — a replayed event_id credits only once', function () {
    [$account] = hsSeedDeposit('pay_dup', 10_000, 'USD');
    $payload = hsPayload('payment_succeeded', 'pay_dup', 'evt_dup', 10_000, 'USD');

    $this->postJson('/api/webhooks/hyperswitch', $payload)->assertOk();
    $this->postJson('/api/webhooks/hyperswitch', $payload)->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(10_000);
});

it('credits from the stored intent amount, ignoring a tampered payload amount', function () {
    [$account] = hsSeedDeposit('pay_tamper', 5_000, 'USD');

    // Attacker inflates the payload amount; we must credit the intent's 5,000.
    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_succeeded', 'pay_tamper', 'evt_tamper', 9_999_999, 'USD'))
        ->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(5_000);
});

it('fails the deposit on payment_failed without crediting', function () {
    [$account, $depositUuid] = hsSeedDeposit('pay_fail', 10_000, 'USD');

    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_failed', 'pay_fail', 'evt_fail', 10_000, 'USD'))
        ->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->exists())
        ->toBeFalse()
        ->and(HyperSwitchDepositIntent::where('hyperswitch_payment_id', 'pay_fail')->value('status'))
        ->toBe(HyperSwitchDepositIntent::STATUS_FAILED)
        ->and(PaymentDeposit::where('aggregate_uuid', $depositUuid)->where('event_class', 'deposit_failed')->exists())
        ->toBeTrue();
});

it('returns 200 and does nothing when no intent matches the payment', function () {
    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_succeeded', 'pay_unknown', 'evt_unknown'))
        ->assertOk();

    expect(HyperSwitchDepositIntent::count())->toBe(0);
});

it('rejects a webhook with a bad signature when a secret is configured', function () {
    config(['hyperswitch.webhook_secret' => 'whsec_hs']);
    hsSeedDeposit('pay_sig', 10_000, 'USD');

    $this->call(
        'POST',
        '/api/webhooks/hyperswitch',
        [],
        [],
        [],
        ['HTTP_X_WEBHOOK_SIGNATURE_512' => 'deadbeef', 'CONTENT_TYPE' => 'application/json'],
        (string) json_encode(hsPayload('payment_succeeded', 'pay_sig', 'evt_sig')),
    )->assertStatus(401);

    expect(HyperSwitchDepositIntent::where('hyperswitch_payment_id', 'pay_sig')->value('status'))
        ->toBe(HyperSwitchDepositIntent::STATUS_PENDING);
});

it('credits only once across two different event_ids for the same payment', function () {
    [$account] = hsSeedDeposit('pay_two_evt', 15_000, 'USD');

    // Same payment, two distinct event_ids. The locked intent-status claim must
    // let only the first through (lockForUpdate serializes; the second reads
    // status=completed and no-ops) — no double credit.
    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_succeeded', 'pay_two_evt', 'evt_a', 15_000, 'USD'))->assertOk();
    $this->postJson('/api/webhooks/hyperswitch', hsPayload('payment_succeeded', 'pay_two_evt', 'evt_b', 15_000, 'USD'))->assertOk();

    expect(AccountBalance::where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance'))
        ->toBe(15_000);
});
