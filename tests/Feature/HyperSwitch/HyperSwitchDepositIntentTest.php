<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Payment\Services\HyperSwitch\HyperSwitchClient;
use App\Models\User;

it('routes POST /deposit/card through HyperSwitch when enabled', function () {
    config(['hyperswitch.enabled' => true]);

    $user = User::factory()->create();
    Account::factory()->create(['user_uuid' => $user->uuid]);

    $client = Mockery::mock(HyperSwitchClient::class);
    $client->shouldReceive('getCustomer')->andReturn(['customer_id' => 'hs_user']);
    $client->shouldReceive('createPayment')->once()->andReturn([
        'payment_id'    => 'pay_ctrl',
        'client_secret' => 'pay_ctrl_secret',
        'status'        => 'requires_payment_method',
    ]);
    app()->instance(HyperSwitchClient::class, $client);

    $this->actingAs($user)
        ->postJson(route('wallet.deposit.store'), ['amount' => 50, 'currency' => 'USD'])
        ->assertOk()
        ->assertJson([
            'processor'     => 'hyperswitch',
            'client_secret' => 'pay_ctrl_secret',
            'currency'      => 'USD',
        ]);

    expect(HyperSwitchDepositIntent::where('hyperswitch_payment_id', 'pay_ctrl')->value('amount_cents'))
        ->toBe(5_000);
});
