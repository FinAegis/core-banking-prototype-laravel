<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function gdprCardholder(User $user): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'                => $user->id,
        'provider'               => 'lepo',
        'first_name'             => 'Ada',
        'last_name'              => 'Lovelace',
        'email'                  => 'ada@example.com',
        'phone'                  => '+37060000000',
        'kyc_status'             => 'verified',
        'kyc_rejection_reason'   => null,
        'issuer_cardholder_id'   => '12345',
        'shipping_address_line1' => 'Gedimino 1',
        'shipping_city'          => 'Vilnius',
        'shipping_postal_code'   => '01103',
        'shipping_country'       => 'LT',
        'verification_data'      => ['date_of_birth' => '1990-01-01', 'nationality' => 'LT', 'questionnaire' => ['occupation' => '15-1252']],
        'verified_at'            => now(),
    ]);
    $cardholder->save();

    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active', 'balance_cents' => 2500]);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb', 'is_active' => true]);

    Card::create([
        'user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'issuer' => 'lepo', 'status' => 'active', 'issuer_card_token' => '555',
        'last4'   => '1234', 'network' => 'visa', 'currency' => 'USD', 'label' => 'Travel', 'metadata' => ['note' => 'x'],
    ]);

    CardTopUp::create([
        'user_id' => $user->id, 'card_id' => Card::query()->where('user_id', $user->id)->value('id'), 'provider' => 'lepo', 'source' => 'app',
        'asset'   => 'USDC', 'network' => 'arbitrum', 'amount' => '25.000000', 'deposit_address' => '0xarb', 'status' => 'completed',
        'tx_hash' => '0x' . str_repeat('c', 64), 'credited_cents' => 2400, 'fee_cents' => 100, 'expires_at' => now(), 'completed_at' => now(),
    ]);

    return $cardholder;
}

beforeEach(function (): void {
    Http::fake();
});

it('exports the card data a user gave the card provider', function (): void {
    $user = User::factory()->create();
    gdprCardholder($user);

    $cards = app(GdprService::class)->exportUserData($user)['cards'];

    expect($cards['cardholders'])->toHaveCount(1)
        ->and($cards['cardholders'][0])->toMatchArray([
            'provider'      => 'lepo',
            'first_name'    => 'Ada',
            'last_name'     => 'Lovelace',
            'email'         => 'ada@example.com',
            'phone'         => '+37060000000',
            'kyc_status'    => 'verified',
            'date_of_birth' => '1990-01-01',
            'nationality'   => 'LT',
        ])
        ->and($cards['cardholders'][0]['address']['line1'])->toBe('Gedimino 1')
        ->and($cards['cardholders'][0]['questionnaire'])->toBe(['occupation' => '15-1252'])
        ->and($cards['funding_accounts'][0])->toMatchArray(['provider' => 'lepo', 'status' => 'active', 'balance_cents' => 2500])
        ->and($cards['funding_accounts'][0]['deposit_addresses'][0])->toBe(['network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0xarb'])
        ->and($cards['cards'][0])->toMatchArray(['provider' => 'lepo', 'provider_card_id' => '555', 'label' => 'Travel', 'status' => 'active', 'last4' => '1234', 'currency' => 'USD'])
        ->and($cards['cards'][0])->not->toHaveKey('metadata')
        ->and($cards['topups'][0])->toMatchArray(['status' => 'completed', 'amount' => '25.000000', 'asset' => 'USDC', 'network' => 'arbitrum', 'credited_cents' => 2400, 'fee_cents' => 100])
        ->and($cards['topups'][0])->not->toHaveKey('deposit_address');
});

it('erases the personal data of a cardholder and keeps the provider record', function (): void {
    $user = User::factory()->create();
    $cardholder = gdprCardholder($user);

    app(GdprService::class)->deleteUserData($user);

    $erased = $cardholder->fresh();
    expect($erased)->not->toBeNull()
        ->and($erased?->first_name)->toBe('Erased')
        ->and($erased?->last_name)->toBe('Erased')
        ->and($erased?->email)->toBeNull()
        ->and($erased?->phone)->toBeNull()
        ->and($erased?->shipping_address_line1)->toBeNull()
        ->and($erased?->shipping_city)->toBeNull()
        ->and($erased?->shipping_postal_code)->toBeNull()
        ->and($erased?->verification_data)->toBeNull()
        // The provider's own record and the money on it stay traceable.
        ->and($erased?->issuer_cardholder_id)->toBe('12345')
        ->and($erased?->provider)->toBe('lepo')
        ->and(CardFundingAccount::query()->where('user_id', $user->id)->value('balance_cents'))->toBe(2500);

    $card = Card::query()->where('user_id', $user->id)->firstOrFail();
    expect($card->label)->toBeNull()
        ->and($card->metadata)->toBeNull()
        ->and($card->issuer_card_token)->toBe('555')
        ->and($card->status)->toBe('active');
});
