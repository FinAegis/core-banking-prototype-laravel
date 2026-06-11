<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('has the sponsored fee columns after migrations', function (): void {
    expect(Schema::hasColumns('wallet_send_records', ['sponsored_fee_raw', 'sponsored_fee_asset']))->toBeTrue();
});

it('round-trips sponsored fee values as decimal strings (no float coercion)', function (): void {
    $user = User::factory()->create();

    $record = WalletSendRecord::create([
        'public_id'         => 'pi_send_feeroundtrip',
        'user_id'           => $user->id,
        'network'           => 'base',
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => '0xsender',
        'recipient_address' => '0xrecipient',
        'status'            => 'confirmed',
        'confirmed_at'      => now(),
        // 256-bit-scale value: must survive storage untouched as a string.
        'sponsored_fee_raw'   => '115792089237316195423570985008687907853269984665640564039457584007913129639935',
        'sponsored_fee_asset' => 'ETH-BASE',
    ]);

    $record->refresh();

    expect($record->sponsored_fee_raw)
        ->toBe('115792089237316195423570985008687907853269984665640564039457584007913129639935')
        ->and($record->sponsored_fee_asset)->toBe('ETH-BASE');
});

it('defaults sponsored fee columns to null for unsponsored sends', function (): void {
    $user = User::factory()->create();

    $record = WalletSendRecord::create([
        'public_id'         => 'pi_send_feedefaults',
        'user_id'           => $user->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => 'sender-base58',
        'recipient_address' => 'recipient-base58',
        'status'            => 'pending',
    ]);

    $record->refresh();

    expect($record->sponsored_fee_raw)->toBeNull()
        ->and($record->sponsored_fee_asset)->toBeNull();
});
