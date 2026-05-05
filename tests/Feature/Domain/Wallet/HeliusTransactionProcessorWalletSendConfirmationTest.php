<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Traits\CreatesSolanaTestTables;

uses(CreatesSolanaTestTables::class);

beforeEach(function (): void {
    $this->createSolanaTestTables();

    // Real user satisfies activity_feed_items.user_id FK without disabling SQLite FK enforcement
    $this->user = User::factory()->create(['id' => 42]);

    Schema::dropIfExists('wallet_send_records');
    Schema::create('wallet_send_records', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('public_id', 64)->unique();
        $table->unsignedBigInteger('user_id');
        $table->string('network', 20);
        $table->string('asset', 10);
        $table->decimal('amount', 30, 8);
        $table->string('sender_address', 128);
        $table->string('recipient_address', 128);
        $table->string('status', 20)->default('pending');
        $table->string('tx_hash', 128)->nullable();
        $table->string('user_op_hash', 128)->nullable();
        $table->string('idempotency_key', 128)->nullable()->unique();
        $table->string('quote_id', 64)->nullable();
        $table->string('error_code', 50)->nullable();
        $table->text('error_message')->nullable();
        $table->json('metadata')->nullable();
        $table->dateTime('submitted_at')->nullable();
        $table->dateTime('confirmed_at')->nullable();
        $table->dateTime('failed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

it('flips a submitted Solana wallet_send_records to confirmed when its tx hash arrives via webhook', function (): void {
    $signature = '5xVgGJzqFVmLqnRkLGJpKfMoNu3ssKYfQYz4XbUmSqJxHfrx7c2yBz8tNvMwQs9X';

    // Pre-existing wallet send awaiting confirmation
    $sendRecord = WalletSendRecord::create([
        'public_id'         => 'pi_send_outboundtest',
        'user_id'           => 42,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.50000000',
        'sender_address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'recipient_address' => 'BobxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV',
        'status'            => 'submitted',
        'tx_hash'           => $signature,
        'submitted_at'      => now(),
    ]);

    // BlockchainAddress + tx record (must be on-curve so HeliusTransactionProcessor doesn't reject)
    $blockchainAddress = BlockchainAddress::create([
        'uuid'       => '01234567-89ab-4def-0123-456789abcdef',
        'user_uuid'  => 'user-uuid-42',
        'address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'public_key' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'chain'      => 'solana',
        'is_active'  => true,
    ]);

    // Outbound (sender = our address) Helius enhanced tx payload
    $tx = [
        'signature'      => $signature,
        'fee'            => 5000,
        'tokenTransfers' => [[
            'fromUserAccount' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'toUserAccount'   => 'BobxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV',
            'mint'            => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', // USDC
            'tokenAmount'     => '1.5',
        ]],
    ];

    $processor = app(HeliusTransactionProcessor::class);
    $processor->processTransaction(
        'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        $blockchainAddress,
        42,
        $tx,
    );

    $sendRecord->refresh();
    expect($sendRecord->status)->toBe('confirmed')
        ->and($sendRecord->confirmed_at)->not->toBeNull();
});

it('does not touch wallet_send_records on incoming (receive) Solana tx', function (): void {
    $signature = '6xa9999999999999999999999999999999999999999999999999999999999999999999999999999999999999';

    $sendRecord = WalletSendRecord::create([
        'public_id'         => 'pi_send_other',
        'user_id'           => 42,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'recipient_address' => 'BobxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV',
        'status'            => 'submitted',
        'tx_hash'           => $signature,
        'submitted_at'      => now(),
    ]);

    $blockchainAddress = BlockchainAddress::create([
        'uuid'       => '11111111-2222-4333-8444-555555555555',
        'user_uuid'  => 'user-uuid-42',
        'address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'public_key' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'chain'      => 'solana',
        'is_active'  => true,
    ]);

    // Incoming tx: our address is the recipient, not the sender
    $tx = [
        'signature'      => $signature,
        'fee'            => 5000,
        'tokenTransfers' => [[
            'fromUserAccount' => 'BobxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV',
            'toUserAccount'   => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
            'mint'            => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            'tokenAmount'     => '1.0',
        ]],
    ];

    app(HeliusTransactionProcessor::class)->processTransaction(
        'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        $blockchainAddress,
        42,
        $tx,
    );

    $sendRecord->refresh();
    // Outbound record stays submitted — we don't confirm it from a receive event
    expect($sendRecord->status)->toBe('submitted')
        ->and($sendRecord->confirmed_at)->toBeNull();
});
