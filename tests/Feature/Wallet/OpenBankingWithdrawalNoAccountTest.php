<?php

declare(strict_types=1);

use App\Models\User;

// initiate() called getBalance() on $user->accounts()->first() without a
// null check, so a user without an account got a 500 instead of an error.

it('sends a user without an account back with an error', function (): void {
    config(['ledger.direct_funding_enabled' => true]);
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->from('/wallet/withdraw/openbanking')
        ->post('/wallet/withdraw/openbanking/initiate', [
            'bank_code' => 'paysera',
            'amount'    => 25,
            'currency'  => 'EUR',
        ])
        ->assertRedirect('/wallet/withdraw/openbanking')
        ->assertSessionHas('error', 'No account found.');
});
