<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a polygon (evm) and solana blockchain address for the user', function (): void {
    $user = User::factory()->create();

    /** @var WalletSeeder $seeder */
    $seeder = app(WalletSeeder::class);
    $seeder->seed($user);

    expect(BlockchainAddress::where('user_uuid', $user->uuid)->where('chain', 'polygon')->count())->toBe(1);
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->where('chain', 'solana')->count())->toBe(1);
});

it('is idempotent when seeded twice for the same user', function (): void {
    $user = User::factory()->create();

    /** @var WalletSeeder $seeder */
    $seeder = app(WalletSeeder::class);
    $seeder->seed($user);
    $seeder->seed($user);

    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->where('chain', 'polygon')->count())->toBe(1);
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->where('chain', 'solana')->count())->toBe(1);
});
