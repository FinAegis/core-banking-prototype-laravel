<?php

declare(strict_types=1);

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Exceptions\InvalidSendStateException;
use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\EvmUserOpSubmitter;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

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

afterEach(function (): void {
    Schema::dropIfExists('wallet_send_records');
});

/**
 * Build a fresh pending wallet_send_record with a realistic UserOp blob in
 * metadata, ready for the submitter to attach a signature.
 */
function makePendingEvmRecord(string $network = 'polygon', string $userOpHash = '0xabc'): WalletSendRecord
{
    $user = User::factory()->create();

    $userOp = [
        'sender'               => '0x1111111111111111111111111111111111111111',
        'nonce'                => '0x0',
        'initCode'             => '0x',
        'callData'             => '0xb61d27f6' . str_repeat('00', 100),
        'callGasLimit'         => '0x' . dechex(120_000),
        'verificationGasLimit' => '0x' . dechex(200_000),
        'preVerificationGas'   => '0x' . dechex(60_000),
        'maxFeePerGas'         => '0x' . dechex(40_000_000_000),
        'maxPriorityFeePerGas' => '0x' . dechex(2_000_000_000),
        'paymasterAndData'     => '0x' . str_repeat('ab', 100),
        'signature'            => '0x',
    ];

    return WalletSendRecord::create([
        'public_id'         => 'pi_send_' . Str::random(20),
        'user_id'           => $user->id,
        'network'           => $network,
        'asset'             => 'USDC',
        'amount'            => '1.50000000',
        'sender_address'    => '0x1111111111111111111111111111111111111111',
        'recipient_address' => '0x2222222222222222222222222222222222222222',
        'status'            => WalletSendRecord::STATUS_PENDING,
        'user_op_hash'      => $userOpHash,
        'metadata'          => [
            'user_op'     => $userOp,
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'chain_id'    => 137,
        ],
    ]);
}

it('attaches signature, submits to bundler, and flips pending → submitted', function (): void {
    $record = makePendingEvmRecord('polygon', '0xdeadbeef');
    $signature = '0x' . str_repeat('cd', 200); // any 0x-prefixed hex blob

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('submitUserOperation')
        ->once()
        ->withArgs(function (UserOperation $userOp, SupportedNetwork $network) use ($signature): bool {
            return $network === SupportedNetwork::POLYGON
                && $userOp->signature === $signature
                && $userOp->sender === '0x1111111111111111111111111111111111111111';
        })
        ->andReturn('0xdeadbeef');

    $submitter = new EvmUserOpSubmitter($bundler);
    $updated = $submitter->submit($record, $signature);

    expect($updated->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($updated->submitted_at)->not->toBeNull()
        ->and($updated->error_code)->toBeNull();
});

it('returns the record unchanged when already submitted (idempotent re-entry)', function (): void {
    $record = makePendingEvmRecord('polygon', '0xab');
    $record->status = WalletSendRecord::STATUS_SUBMITTED;
    $record->submitted_at = now()->subMinutes(5);
    $record->save();

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    $submitter = new EvmUserOpSubmitter($bundler);
    $updated = $submitter->submit($record, '0xff');

    expect($updated->status)->toBe(WalletSendRecord::STATUS_SUBMITTED);
});

it('returns the record unchanged when already confirmed', function (): void {
    $record = makePendingEvmRecord('polygon', '0xab');
    $record->status = WalletSendRecord::STATUS_CONFIRMED;
    $record->save();

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    $submitter = new EvmUserOpSubmitter($bundler);
    $updated = $submitter->submit($record, '0xff');

    expect($updated->status)->toBe(WalletSendRecord::STATUS_CONFIRMED);
});

it('throws InvalidSignatureException when signature is missing 0x prefix', function (): void {
    $record = makePendingEvmRecord();

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    $submitter = new EvmUserOpSubmitter($bundler);
    $submitter->submit($record, 'abcdef');
})->throws(InvalidSignatureException::class);

it('throws InvalidSignatureException when signature contains non-hex characters', function (): void {
    $record = makePendingEvmRecord();

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    $submitter = new EvmUserOpSubmitter($bundler);
    $submitter->submit($record, '0xZZZ-not-hex');
})->throws(InvalidSignatureException::class);

it('marks the record failed with BUNDLER_REJECTED when the bundler throws', function (): void {
    $record = makePendingEvmRecord('base', '0xfeedface');
    $signature = '0x' . str_repeat('aa', 64);

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('submitUserOperation')
        ->once()
        ->andThrow(new RuntimeException('bundler RPC: AA10 sender already constructed'));

    $submitter = new EvmUserOpSubmitter($bundler);
    $updated = $submitter->submit($record, $signature);

    expect($updated->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($updated->error_code)->toBe('BUNDLER_REJECTED')
        ->and($updated->error_message)->toContain('AA10')
        ->and($updated->failed_at)->not->toBeNull();
});

it('throws InvalidSendStateException when metadata is missing user_op', function (): void {
    $record = makePendingEvmRecord();
    $record->metadata = ['some_other_key' => 'value'];
    $record->save();

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    $submitter = new EvmUserOpSubmitter($bundler);
    $submitter->submit($record, '0xff');
})->throws(InvalidSendStateException::class);

it('updates user_op_hash when bundler reports a different hash than expected', function (): void {
    $record = makePendingEvmRecord('polygon', '0xexpected');
    $signature = '0x' . str_repeat('11', 64);

    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('submitUserOperation')
        ->once()
        ->andReturn('0xactualfromBundler');

    $submitter = new EvmUserOpSubmitter($bundler);
    $updated = $submitter->submit($record, $signature);

    expect($updated->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($updated->user_op_hash)->toBe('0xactualfromBundler');

    $metadata = $updated->metadata ?? [];
    expect($metadata['expected_user_op_hash'] ?? null)->toBe('0xexpected')
        ->and($metadata['bundler_user_op_hash'] ?? null)->toBe('0xactualfromBundler');
});
