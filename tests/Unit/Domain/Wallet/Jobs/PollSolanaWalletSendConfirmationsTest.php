<?php

declare(strict_types=1);

use App\Domain\Wallet\Jobs\PollSolanaWalletSendConfirmations;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
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

/**
 * @param array<string, mixed> $overrides
 */
function makeSolanaSendRecord(array $overrides = []): WalletSendRecord
{
    return WalletSendRecord::create(array_merge([
        'public_id'         => 'pi_send_' . uniqid(),
        'user_id'           => 1,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z',
        'recipient_address' => 'BobxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV',
        'status'            => 'submitted',
        'tx_hash'           => '5xSolanaSig' . uniqid(),
        'submitted_at'      => now(),
    ], $overrides));
}

/**
 * @param array{confirmationStatus: string, err: array<int|string, mixed>|null}|null $entry
 */
function mockHeliusRpc(string $signature, ?array $entry): HeliusRpcClient
{
    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getSignatureStatuses')
        ->once()
        ->with([$signature])
        ->andReturn([$signature => $entry]);

    return $rpc;
}

it('flips a submitted Solana send to confirmed when the cluster reports it confirmed', function (): void {
    $record = makeSolanaSendRecord();

    $rpc = mockHeliusRpc((string) $record->tx_hash, ['confirmationStatus' => 'finalized', 'err' => null]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('confirmed')
        ->and($record->confirmed_at)->not->toBeNull();
});

it('flips a submitted Solana send to failed when the cluster reports an error', function (): void {
    $record = makeSolanaSendRecord();

    $rpc = mockHeliusRpc((string) $record->tx_hash, [
        'confirmationStatus' => 'confirmed',
        'err'                => ['InstructionError' => [0, 'InsufficientFundsForFee']],
    ]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('failed')
        ->and($record->error_code)->toBe('SOLANA_TX_FAILED')
        ->and($record->error_message)->not->toBeNull()
        ->and($record->failed_at)->not->toBeNull();
});

it('marks a stale unknown send as dropped', function (): void {
    $record = makeSolanaSendRecord(['submitted_at' => now()->subMinutes(10)]);

    $rpc = mockHeliusRpc((string) $record->tx_hash, null);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('failed')
        ->and($record->error_code)->toBe('SOLANA_TX_DROPPED')
        ->and($record->failed_at)->not->toBeNull();
});

it('leaves a recently submitted unknown send alone (still within blockhash window)', function (): void {
    $record = makeSolanaSendRecord(['submitted_at' => now()->subMinute()]);

    $rpc = mockHeliusRpc((string) $record->tx_hash, null);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('submitted')
        ->and($record->failed_at)->toBeNull();
});

it('leaves a send still being processed alone', function (): void {
    $record = makeSolanaSendRecord();

    $rpc = mockHeliusRpc((string) $record->tx_hash, ['confirmationStatus' => 'processed', 'err' => null]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('submitted')
        ->and($record->confirmed_at)->toBeNull()
        ->and($record->failed_at)->toBeNull();
});

it('ignores EVM records and non-submitted records', function (): void {
    $evm = makeSolanaSendRecord(['network' => 'polygon']);
    $pending = makeSolanaSendRecord(['status' => 'pending']);

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('getSignatureStatuses');

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    expect($evm->refresh()->status)->toBe('submitted')
        ->and($pending->refresh()->status)->toBe('pending');
});

it('logs and continues when the RPC throws', function (): void {
    $record = makeSolanaSendRecord();

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getSignatureStatuses')
        ->once()
        ->andThrow(new RuntimeException('Helius RPC down'));

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('submitted');
});

it('does not drop an unknown send before its blockhash has expired', function (): void {
    $record = makeSolanaSendRecord(['submitted_at' => now()->subMinutes(10), 'metadata' => ['last_valid_block_height' => 1000]]);

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getBlockHeight')->once()->andReturn(1000);
    $rpc->shouldReceive('getSignatureStatuses')->once()->andReturn([(string) $record->tx_hash => null]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    expect($record->refresh()->status)->toBe('submitted');
});

it('drops an unknown send once the chain is past its last valid block height', function (): void {
    $record = makeSolanaSendRecord(['submitted_at' => now()->subMinutes(10), 'metadata' => ['last_valid_block_height' => 1000]]);

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getBlockHeight')->once()->ordered()->andReturn(1001);
    $rpc->shouldReceive('getSignatureStatuses')->once()->ordered()->andReturn([(string) $record->tx_hash => null]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    $record->refresh();
    expect($record->status)->toBe('failed')
        ->and($record->error_code)->toBe('SOLANA_TX_DROPPED');
});

it('keeps waiting when the block height cannot be read', function (): void {
    $record = makeSolanaSendRecord(['submitted_at' => now()->subMinutes(10), 'metadata' => ['last_valid_block_height' => 1000]]);

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getBlockHeight')->once()->andThrow(new RuntimeException('RPC down'));
    $rpc->shouldReceive('getSignatureStatuses')->once()->andReturn([(string) $record->tx_hash => null]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    expect($record->refresh()->status)->toBe('submitted');
});

it('keeps checking an uncertain Solana send after the two-hour window', function (): void {
    $record = makeSolanaSendRecord([
        'submitted_at' => now()->subHours(3),
        'metadata'     => ['submit_outcome' => 'unknown', 'last_valid_block_height' => 1000],
    ]);

    /** @var HeliusRpcClient&Mockery\MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getBlockHeight')->andReturn(5000);
    $rpc->shouldReceive('getSignatureStatuses')->once()
        ->andReturn([(string) $record->tx_hash => ['confirmationStatus' => 'finalized', 'err' => null]]);

    (new PollSolanaWalletSendConfirmations())->handle($rpc);

    expect($record->refresh()->status)->toBe('confirmed');
});
