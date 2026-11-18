<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Models\User;

it('prints a users FinCard cardholder, account and cards', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $ch = new Cardholder();
    $ch->user_id = (string) $user->id;
    $ch->first_name = 'Jane';
    $ch->last_name = 'Smith';
    $ch->kyc_status = 'verified';
    $ch->kyc_stage = 'channel';
    $ch->issuer_cardholder_id = 'h-42';
    $ch->save();

    $account = new FinCardAccount();
    $account->user_id = (string) $user->id;
    $account->fincard_account_id = 'acc-42';
    $account->currency = 'USD';
    $account->balance_cents = 12345;
    $account->status = 'active';
    $account->save();

    $card = new Card();
    $card->user_id = (string) $user->id;
    $card->cardholder_id = $ch->id;
    $card->issuer_card_token = 'card-42';
    $card->issuer = 'fincard';
    $card->last4 = '4242';
    $card->network = 'visa';
    $card->status = 'active';
    $card->currency = 'USD';
    $card->balance_cents = 5000;
    $card->save();

    $this->artisan('fincard:inspect-user', ['email' => 'jane@example.com'])
        ->expectsOutputToContain('h-42')
        ->expectsOutputToContain('acc-42')
        ->expectsOutputToContain('card-42')
        ->assertExitCode(0);
});

it('errors for an unknown email', function () {
    $this->artisan('fincard:inspect-user', ['email' => 'nobody@example.com'])
        ->assertExitCode(1);
});
