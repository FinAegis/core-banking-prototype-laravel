<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardTopUpUpdated;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\Lepo\LepoDepositMatcher;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const DM_ADDRESS = '0xAaAaAaAaAaAaAaAaAaAaAaAaAaAaAaAaAaAaAaAa';

beforeEach(function (): void {
    config(['cardissuance.issuers.lepo.base_url' => 'https://lepo.test/api/v2', 'cardissuance.issuers.lepo.api_key' => 'test-key']);
    Event::fake([CardTopUpUpdated::class]);
});

function dmCard(): Card
{
    $user = User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => DM_ADDRESS, 'is_active' => true]);

    return Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => 'pending']);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function dmTopUp(Card $card, array $overrides = []): CardTopUp
{
    $topup = new CardTopUp();
    $topup->forceFill(array_merge([
        'user_id' => $card->user_id, 'card_id' => $card->id, 'funding_account_id' => $card->funding_account_id, 'provider' => 'lepo', 'source' => 'app',
        'asset'   => 'USDC', 'network' => 'arbitrum', 'amount' => '25.000000', 'deposit_address' => DM_ADDRESS,
        'status'  => 'awaiting_deposit', 'expires_at' => now()->addHour(),
    ], $overrides))->save();

    return $topup;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dmDeposit(array $overrides = []): array
{
    return array_replace_recursive([
        'id'          => 'dep-1', 'accountId' => 'acc-1', 'status' => 'COMPLETED',
        'source'      => ['type' => 'EXTERNAL_WALLET', 'chain' => 'ARBITRUM', 'address' => '0xsender'],
        'destination' => ['type' => 'CRYPTO_ASSETS', 'chain' => 'ARBITRUM', 'address' => strtolower(DM_ADDRESS)],
        'amount'      => ['amount' => 25.0, 'currency' => 'USDC'], 'createTime' => now()->toIso8601String(),
    ], $overrides);
}

it('matches a deposit by transaction hash', function (): void {
    $topup = dmTopUp(dmCard(), ['tx_hash' => '0x' . str_repeat('b', 64), 'amount' => '30.000000']);
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [dmDeposit(['amount' => ['amount' => 24.5]])], 'total' => 1])]);

    expect(app(LepoDepositMatcher::class)->detect($topup))->toBeTrue();

    $fresh = $topup->fresh();
    expect($fresh?->status)->toBe('deposit_received')
        ->and($fresh?->provider_deposit_id)->toBe('dep-1')
        ->and($fresh?->received_amount)->toBe('24.500000');
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'transactionHash=0x' . str_repeat('b', 64)) && str_contains($r->url(), 'userId=12345'));
    Event::assertDispatched(CardTopUpUpdated::class, fn (CardTopUpUpdated $e): bool => $e->status === 'deposit_received');
});

it('matches a deposit by amount within one percent', function (float $amount, bool $matches): void {
    $topup = dmTopUp(dmCard());
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [dmDeposit(['amount' => ['amount' => $amount]])]])]);

    expect(app(LepoDepositMatcher::class)->detect($topup))->toBe($matches);
})->with([[24.80, true], [25.24, true], [24.70, false], [26.0, false]]);

it('ignores a deposit that is not completed or not to this address', function (array $deposit): void {
    $topup = dmTopUp(dmCard());
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [dmDeposit($deposit)]])]);

    expect(app(LepoDepositMatcher::class)->detect($topup))->toBeFalse()
        ->and($topup->fresh()?->status)->toBe('awaiting_deposit');
})->with([
    'pending'       => [['status' => 'PENDING']],
    'other address' => [['destination' => ['address' => '0xsomeoneelse']]],
    'other network' => [['destination' => ['chain' => 'POLYGON']]],
    'other asset'   => [['amount' => ['currency' => 'USDT']]],
]);

it('never links one deposit to two top-ups', function (): void {
    $card = dmCard();
    dmTopUp($card, ['status' => 'completed', 'provider_deposit_id' => 'dep-1']);
    $topup = dmTopUp($card);
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [dmDeposit()]])]);

    expect(app(LepoDepositMatcher::class)->detect($topup))->toBeFalse();
});

it('re-opens an expired top-up when its deposit arrives late', function (): void {
    $topup = dmTopUp(dmCard(), ['status' => 'expired', 'expires_at' => now()->subHour()]);
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [dmDeposit()]])]);

    expect(app(LepoDepositMatcher::class)->detect($topup))->toBeTrue()
        ->and($topup->fresh()?->status)->toBe('deposit_received');
});

it('lists deposits that belong to no top-up', function (): void {
    $card = dmCard();
    dmTopUp($card, ['status' => 'completed', 'provider_deposit_id' => 'dep-1']);
    Http::fake(['lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => [
        dmDeposit(),
        dmDeposit(['id' => 'dep-2', 'amount' => ['amount' => 40.123456]]),
        dmDeposit(['id' => 'dep-3', 'status' => 'PENDING']),
    ]])]);

    $holder = $card->cardholder()->firstOrFail();
    expect(app(LepoDepositMatcher::class)->findUnmatched($holder))->toBe([
        ['id' => 'dep-2', 'amount' => '40.123456', 'network' => 'arbitrum', 'address' => DM_ADDRESS],
    ]);
});
