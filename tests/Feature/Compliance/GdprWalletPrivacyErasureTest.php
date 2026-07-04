<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\GdprService;
use App\Domain\Privacy\Models\RailgunWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Wave 1A — blockchain_address_transactions (address-linked financial PII that
// escaped the user-FK schema coverage guard) and railgun_wallets (a pseudonymous
// 0zk identity) are now covered by GDPR export + erasure. The schema guard
// cannot see the tx table (no user FK), so this asserts the behaviour directly.

it('exports and erases blockchain transactions and railgun wallets', function () {
    Http::fake(); // fresh user has no processor links; keep any fan-out offline

    $user = User::factory()->create();
    $addressUuid = (string) Str::uuid();

    DB::table('blockchain_addresses')->insert([
        'uuid'       => $addressUuid,
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'SoLtEsT1111111111111111111111111111111111111',
        'public_key' => 'test-pk',
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('blockchain_address_transactions')->insert([
        'uuid'         => (string) Str::uuid(),
        'address_uuid' => $addressUuid,
        'tx_hash'      => 'txhash-abc',
        'type'         => 'receive',
        'amount'       => '1.5',
        'fee'          => '0',
        'from_address' => 'FROMADDR',
        'to_address'   => 'SoLtEsT1111111111111111111111111111111111111',
        'chain'        => 'solana',
        'status'       => 'confirmed',
        'metadata'     => json_encode(['note' => 'private memo']),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('railgun_wallets')->insert([
        'id'                 => (string) Str::uuid(),
        'user_id'            => $user->id,
        'railgun_address'    => '0zk-test-' . $user->id,
        'encrypted_mnemonic' => null,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    $service = app(GdprService::class);

    // Export includes the tx history and the 0zk address.
    $export = json_encode($service->exportUserData($user));
    expect($export)->toContain('txhash-abc');
    expect($export)->toContain('0zk-test-' . $user->id);

    // Erasure strips the tx free-text metadata and deletes the railgun row.
    $service->deleteUserData($user);

    $metadata = DB::table('blockchain_address_transactions')
        ->where('address_uuid', $addressUuid)
        ->value('metadata');
    expect($metadata)->toBeNull();
    expect(RailgunWallet::where('user_id', $user->id)->exists())->toBeFalse();
});
