<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Payment\Models\PaymentDeposit;
use App\Domain\Payment\Services\HyperSwitch\HyperSwitchClient;
use App\Domain\Payment\Services\HyperSwitch\HyperSwitchPaymentService;
use App\Models\User;
use Mockery\MockInterface;

it('passes the deposit_uuid through payment metadata so the webhook can correlate it', function () {
    /** @var HyperSwitchClient&MockInterface $client */
    $client = Mockery::mock(HyperSwitchClient::class);
    $client->shouldReceive('getCustomer')->andReturn(['customer_id' => 'hs_user']); // already exists
    // The expectation only matches when the payload carries the round-tripped
    // deposit_uuid + normalised amount/currency, so a regression fails the test.
    $client->shouldReceive('createPayment')
        ->once()
        ->with(Mockery::on(function (array $payload): bool {
            return ($payload['metadata']['deposit_uuid'] ?? null) === 'deposit-uuid-1'
                && ($payload['amount'] ?? null) === 12_345
                && ($payload['currency'] ?? null) === 'USD';
        }))
        ->andReturn([
            'payment_id'    => 'pay_abc',
            'client_secret' => 'pay_abc_secret_xyz',
            'status'        => 'requires_payment_method',
        ]);

    $result = (new HyperSwitchPaymentService($client))->initiateDeposit(
        amountCents: 12_345,
        currency: 'usd',
        userUuid: 'user-uuid-1',
        userEmail: 'user@example.com',
        returnUrl: 'https://app.test/return',
        depositUuid: 'deposit-uuid-1',
        description: 'Deposit',
    );

    expect($result['payment_id'])->toBe('pay_abc')
        ->and($result['client_secret'])->toBe('pay_abc_secret_xyz');
});

it('startDeposit initiates the aggregate, records a pending intent, and returns the client secret', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);

    /** @var HyperSwitchClient&MockInterface $client */
    $client = Mockery::mock(HyperSwitchClient::class);
    $client->shouldReceive('getCustomer')->andReturn(['customer_id' => 'hs_user']);
    $client->shouldReceive('createPayment')->once()->andReturn([
        'payment_id'    => 'pay_start',
        'client_secret' => 'pay_start_secret',
        'status'        => 'requires_payment_method',
    ]);

    $result = (new HyperSwitchPaymentService($client))->startDeposit($user, 7_500, 'usd');

    expect($result['client_secret'])->toBe('pay_start_secret')
        ->and($result['payment_id'])->toBe('pay_start');

    $intent = HyperSwitchDepositIntent::where('hyperswitch_payment_id', 'pay_start')->first();
    expect($intent)->not->toBeNull()
        ->and($intent->account_uuid)->toBe($account->uuid)
        ->and($intent->amount_cents)->toBe(7_500)
        ->and($intent->currency)->toBe('USD')
        ->and($intent->status)->toBe(HyperSwitchDepositIntent::STATUS_PENDING)
        ->and(PaymentDeposit::where('aggregate_uuid', $intent->deposit_uuid)->where('event_class', 'deposit_initiated')->exists())
        ->toBeTrue();
});
