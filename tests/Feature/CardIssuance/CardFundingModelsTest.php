<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardDepositAddress;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<model-property<Cardholder>, mixed>  $overrides
 */
function fundingCardholder(User $user, array $overrides = []): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill(array_merge([
        'user_id'    => $user->id,
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
        'kyc_status' => 'pending',
        'provider'   => 'lepo',
    ], $overrides));
    $cardholder->save();

    return $cardholder;
}

it('backfills fincard as the provider of existing cardholders', function (): void {
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_11_21_100000_add_provider_to_cardholders_and_create_card_funding_tables.php');
    // cards.funding_account_id and card_topups (later migrations) reference
    // the funding accounts this migration drops, so they are rolled back first.
    $cardsMigration = require database_path('migrations/2026_11_22_100000_prepare_cards_for_card_providers.php');
    $topUpsMigration = require database_path('migrations/2026_11_23_100000_create_card_topups_table.php');
    $topUpsMigration->down();
    $cardsMigration->down();
    $migration->down();

    DB::table('cardholders')->insert([
        'id'         => '0f1b2c3d-0000-4000-8000-000000000001', 'user_id' => $user->id, 'first_name' => 'Fin', 'last_name' => 'Card',
        'kyc_status' => 'verified', 'issuer_cardholder_id' => 'FC-1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('cardholders')->insert([
        'id'         => '0f1b2c3d-0000-4000-8000-000000000002', 'user_id' => $user->id, 'first_name' => 'No', 'last_name' => 'Issuer',
        'kyc_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();
    $cardsMigration->up();
    $topUpsMigration->up();

    expect(DB::table('cardholders')->where('id', '0f1b2c3d-0000-4000-8000-000000000001')->value('provider'))->toBe('fincard')
        ->and(DB::table('cardholders')->where('id', '0f1b2c3d-0000-4000-8000-000000000002')->value('provider'))->toBeNull();

    // On MySQL/MariaDB the schema changes above commit the test transaction,
    // so remove what this test wrote instead of relying on the rollback.
    DB::table('cardholders')->whereIn('id', ['0f1b2c3d-0000-4000-8000-000000000001', '0f1b2c3d-0000-4000-8000-000000000002'])->delete();
    $user->delete();
});

it('stores one funding account per user and provider', function (): void {
    $user = User::factory()->create();
    $cardholder = fundingCardholder($user);

    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo']);

    expect($account->status)->toBe('pending')
        ->and($account->currency)->toBe('USD')
        ->and($account->balance_cents)->toBe(0)
        ->and($cardholder->fresh()?->fundingAccount?->id)->toBe($account->id);

    expect(fn () => CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo']))
        ->toThrow(QueryException::class);
});

it('keeps one address per network and asset', function (): void {
    $user = User::factory()->create();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => fundingCardholder($user)->id, 'provider' => 'lepo']);

    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0x' . str_repeat('a', 40), 'is_active' => true]);

    expect($account->depositAddresses()->count())->toBe(1)
        ->and(CardDepositAddress::query()->first()?->fundingAccount?->id)->toBe($account->id);

    expect(fn () => $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0x' . str_repeat('b', 40), 'is_active' => true]))
        ->toThrow(QueryException::class);
});
