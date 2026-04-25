<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Seeders\BalanceSeeder;
use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @param mixed $value
 */
function bsNormalize($value): string
{
    $s = (string) $value;
    if (! is_numeric($s)) {
        return '0.0000';
    }

    /** @var numeric-string $s */
    return bcadd($s, '0', 4);
}

it('seeds USDC unshielded and shielded balances on polygon', function (): void {
    $user = User::factory()->create();
    app(WalletSeeder::class)->seed($user);

    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');

    $shielded = ShieldedBalance::where('user_id', $user->id)
        ->where('token', 'USDC')
        ->where('network', 'polygon')
        ->first();
    expect($shielded)->not->toBeNull();
    /** @var ShieldedBalance $shielded */
    expect(bsNormalize($shielded->balance))->toBe('10.0000');

    $unshielded = DB::table('token_balances')
        ->where('chain', 'polygon')
        ->where('symbol', 'USDC')
        ->whereIn('address', function ($q) use ($user): void {
            $q->select('address')
                ->from('blockchain_addresses')
                ->where('user_uuid', $user->uuid)
                ->where('chain', 'polygon');
        })
        ->first();
    expect($unshielded)->not->toBeNull();
    /** @var stdClass $unshielded */
    expect(bsNormalize($unshielded->balance))->toBe('25.0000');
});

it('is idempotent — re-seeding sets to the target, not incremental', function (): void {
    $user = User::factory()->create();
    app(WalletSeeder::class)->seed($user);

    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');
    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');

    expect(ShieldedBalance::where('user_id', $user->id)->count())->toBe(1);
    expect(DB::table('token_balances')->where('symbol', 'USDC')->count())->toBe(1);

    // Different target must overwrite, not add.
    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '50.00', shieldedUsdc: '20.00');

    $shielded = ShieldedBalance::where('user_id', $user->id)->where('token', 'USDC')->first();
    expect($shielded)->not->toBeNull();
    /** @var ShieldedBalance $shielded */
    expect(bsNormalize($shielded->balance))->toBe('20.0000');

    $unshielded = DB::table('token_balances')->where('symbol', 'USDC')->first();
    expect($unshielded)->not->toBeNull();
    /** @var stdClass $unshielded */
    expect(bsNormalize($unshielded->balance))->toBe('50.0000');
});

it('preserves created_at audit timestamp across re-seeds', function (): void {
    $user = User::factory()->create();
    app(WalletSeeder::class)->seed($user);

    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');

    $first = DB::table('token_balances')->where('symbol', 'USDC')->first();
    expect($first)->not->toBeNull();
    /** @var stdClass $first */
    $originalCreatedAt = $first->created_at;
    expect($originalCreatedAt)->not->toBeNull();

    // Sleep briefly so any erroneous overwrite produces a different timestamp.
    sleep(1);

    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '50.00', shieldedUsdc: '20.00');

    $second = DB::table('token_balances')->where('symbol', 'USDC')->first();
    expect($second)->not->toBeNull();
    /** @var stdClass $second */
    expect($second->created_at)->toBe($originalCreatedAt);
});
