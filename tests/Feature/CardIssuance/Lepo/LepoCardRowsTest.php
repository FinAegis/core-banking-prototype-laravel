<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * @return array{0: Cardholder, 1: CardFundingAccount}
 */
function rowsCardholder(): array
{
    $user = User::factory()->create();
    $cardholder = new Cardholder();
    $cardholder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified']);
    $cardholder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);

    return [$cardholder, $account];
}

it('keeps a card row before the provider card exists', function (): void {
    [$cardholder, $account] = rowsCardholder();

    $card = Card::create([
        'user_id'            => $cardholder->user_id,
        'cardholder_id'      => $cardholder->id,
        'issuer'             => 'lepo',
        'status'             => Card::STATUS_PENDING,
        'issuer_card_token'  => null,
        'last4'              => null,
        'network'            => null,
        'funding_account_id' => $account->id,
    ]);

    expect($card->fresh()?->isOpenAtProvider())->toBeFalse()
        ->and($card->fresh()?->fundingAccount?->id)->toBe($account->id)
        ->and(Card::STATUS_PAYMENT_REQUIRED)->toBe('payment_required');
});

it('still refuses two cards with the same provider card id', function (): void {
    [$cardholder] = rowsCardholder();
    $attributes = ['user_id' => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'issuer' => 'lepo', 'status' => 'active', 'issuer_card_token' => '555', 'last4' => '1234', 'network' => 'visa'];

    Card::create($attributes);

    expect(fn () => Card::create($attributes))->toThrow(QueryException::class);
});
