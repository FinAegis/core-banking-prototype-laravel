<?php

declare(strict_types=1);

namespace Tests\MultiConnection\CardIssuance;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawalPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Withdrawals, their audit rows and cards live on the TENANT connection, while
// users and blockchain_addresses live on the default one. The start, the money
// steps and the confirm must work across two real MySQL sessions without a
// default-connection transaction around tenant writes (CLAUDE.md pitfall).
it('starts, runs and confirms a withdrawal across the two connections', function () {
    config([
        'cache.default'                                      => 'array',
        'cardissuance.issuers.lepo.base_url'                 => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'                  => 'k',
        'cardissuance.providers.lepo_pilot_only'             => false,
        'cardissuance.providers.lepo_withdrawals_enabled'    => true,
        'cardissuance.providers.lepo_withdrawal_networks'    => 'arbitrum',
        'cardissuance.providers.lepo_withdrawal_chain_codes' => 'arbitrum:ARB',
    ]);
    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    Bus::fake([AdvanceLepoWithdrawals::class]);
    Http::preventStrayRequests();
    Http::fake(function (Request $r) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/cards/quantum-topup/estimate')         => Http::response(['usdToUsdc' => ['baseCurrency' => 'USD', 'rate' => '0.998', 'fee' => '0.25', 'feeCurrency' => 'USD']]),
            str_ends_with($path, '/transfers/withdrawals/fee-and-quota')  => Http::response(['data' => ['fees' => [['amount' => '1.00', 'currency' => 'USDC']]]]),
            str_ends_with($path, '/cards/unload-card')                    => Http::response(['success' => true, 'requestedAmount' => 50.0, 'netAmount' => 50.0, 'fee' => 0, 'transactionId' => 'un-1']),
            str_ends_with($path, '/quantum-usd-to-crypto-exchange')       => Http::response(['success' => true, 'sourceAmount' => 50.0, 'cryptoAmountReceived' => 49.6505, 'fee' => 0.25, 'tradeId' => 'tr-1']),
            str_ends_with($path, '/transfers/withdrawals/crypto')         => Http::response(['success' => true, 'verificationToken' => 'tok-1', 'otpRequired' => true, 'transactionId' => 'wd-1'], 201),
            str_ends_with($path, '/transfers/withdrawals/crypto/confirm') => Http::response(['success' => true, 'transactionId' => 'wd-1', 'status' => 'PROCESSING', 'actualAmount' => 48.65]),
            str_ends_with($path, '/users/12345/assets')                   => Http::response(['assets' => []]),
            str_ends_with($path, '/users/12345')                          => Http::response(['email' => 'ada@example.com']),
            default                                                       => Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '62.10', 'pending' => '0', 'frozen' => '0']]),
        };
    });

    $user = User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $card = Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => 'active', 'issuer_card_token' => '777']);
    $address = BlockchainAddress::create(['user_uuid' => $user->uuid, 'chain' => 'arbitrum', 'address' => '0x' . str_repeat('6', 40), 'public_key' => 'x', 'is_active' => true,
        'metadata'                                    => ['provider' => 'privy', 'wallet_kind' => 'privy_smart_account']]);
    $address->forceFill(['created_at' => now()->subDays(2)])->save();

    $withdrawal = app(LepoWithdrawals::class)->start($user, $card, '50.00', false, 'arbitrum', false, ['ip' => '127.0.0.1']);
    expect($withdrawal->status)->toBe('unloading')->and($withdrawal->destination_address_uuid)->toBe($address->uuid);

    $pipeline = app(LepoWithdrawalPipeline::class);
    foreach (range(1, 3) as $step) {
        $pipeline->advance($withdrawal);
    }
    expect($withdrawal->refresh()->status)->toBe('awaiting_code');

    [$confirmed, $checking] = app(LepoWithdrawals::class)->confirm($withdrawal, 'AB12CD34');
    expect($confirmed->status)->toBe('sending')->and($checking)->toBeFalse();

    $stored = CardWithdrawal::query()->whereKey($withdrawal->id)->firstOrFail();
    expect($stored->status)->toBe('sending')
        ->and(CardWithdrawalEvent::query()->where('withdrawal_id', $withdrawal->id)->count())->toBeGreaterThan(5);
});
