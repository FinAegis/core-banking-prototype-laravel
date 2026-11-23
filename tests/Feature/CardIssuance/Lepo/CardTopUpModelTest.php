<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Models\User;
use Illuminate\Database\QueryException;

function topUpCard(): Card
{
    $user = User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified']);
    $holder->save();

    return Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'issuer' => 'lepo', 'status' => 'pending']);
}

/**
 * @param  array<model-property<CardTopUp>, mixed>  $overrides
 */
function topUpRow(Card $card, array $overrides = []): CardTopUp
{
    $topup = new CardTopUp();
    $topup->forceFill(array_merge([
        'user_id' => $card->user_id, 'card_id' => $card->id, 'provider' => 'lepo', 'source' => CardTopUp::SOURCE_APP,
        'asset'   => 'USDC', 'network' => 'arbitrum', 'amount' => '25.123456', 'deposit_address' => '0x' . str_repeat('a', 40),
        'status'  => CardTopUp::STATUS_AWAITING_DEPOSIT, 'expires_at' => now()->addHour(),
    ], $overrides))->save();

    return $topup;
}

it('stores a top-up with decimal amounts', function (): void {
    $topup = topUpRow(topUpCard());

    $fresh = $topup->fresh();
    expect($fresh?->amount)->toBe('25.123456')
        ->and($fresh?->status)->toBe('awaiting_deposit')
        ->and($fresh?->card?->id)->toBe($topup->card_id)
        ->and(CardTopUp::OPEN)->toBe(['awaiting_deposit', 'deposit_received', 'converting', 'loading'])
        ->and(CardTopUp::IN_FLIGHT)->toBe(['deposit_received', 'converting', 'loading']);
});

it('refuses the same transaction hash twice on one network', function (): void {
    $card = topUpCard();
    $hash = '0x' . str_repeat('b', 64);
    topUpRow($card, ['tx_hash' => $hash]);

    expect(fn () => topUpRow($card, ['tx_hash' => $hash]))->toThrow(QueryException::class);
    expect(topUpRow($card, ['tx_hash' => $hash, 'network' => 'polygon'])->exists)->toBeTrue();
});
