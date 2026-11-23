<?php

declare(strict_types=1);

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\EntryPointNonces;
use App\Domain\Wallet\Jobs\PollEvmWalletSendConfirmations;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
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
        $table->string('sponsored_fee_raw', 78)->nullable();
        $table->string('sponsored_fee_asset', 16)->nullable();
        $table->string('error_code', 50)->nullable();
        $table->text('error_message')->nullable();
        $table->json('metadata')->nullable();
        $table->dateTime('submitted_at')->nullable();
        $table->dateTime('confirmed_at')->nullable();
        $table->dateTime('failed_at')->nullable();
        $table->timestamps();
    });
});

function makeSubmittedRecord(string $network, ?string $userOpHash): WalletSendRecord
{
    return WalletSendRecord::create([
        'public_id'         => 'pi_send_' . uniqid(),
        'user_id'           => 1,
        'network'           => $network,
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => '0xsender',
        'recipient_address' => '0xrecipient',
        'status'            => 'submitted',
        'user_op_hash'      => $userOpHash,
        'submitted_at'      => now(),
    ]);
}

it('flips submitted EVM record to confirmed when bundler reports a receipt', function (): void {
    $record = makeSubmittedRecord('polygon', '0xUserOpHashConfirmed');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->with('0xUserOpHashConfirmed')
        ->andReturn([
            'status'  => 'confirmed',
            'tx_hash' => '0xrealTxHash123',
            'receipt' => ['success' => true, 'gasUsed' => 0, 'blockNumber' => 1],
        ]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('confirmed')
        ->and($record->tx_hash)->toBe('0xrealTxHash123')
        ->and($record->confirmed_at)->not->toBeNull();
});

it('flips submitted EVM record to failed when bundler reports a reverted receipt', function (): void {
    $record = makeSubmittedRecord('base', '0xUserOpHashReverted');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->with('0xUserOpHashReverted')
        ->andReturn([
            'status'  => 'failed',
            'tx_hash' => '0xrevertedTxHash',
            'receipt' => null,
        ]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('failed')
        ->and($record->error_code)->toBe('BUNDLER_REVERTED')
        ->and($record->failed_at)->not->toBeNull();
});

it('leaves submitted record alone when bundler returns pending', function (): void {
    $record = makeSubmittedRecord('arbitrum', '0xUserOpPending');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->andReturn(['status' => 'pending', 'tx_hash' => null, 'receipt' => null]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('submitted')
        ->and($record->confirmed_at)->toBeNull()
        ->and($record->failed_at)->toBeNull();
});

it('does not touch Solana records (those are handled by Helius webhook)', function (): void {
    $record = makeSubmittedRecord('solana', null);
    $record->user_op_hash = null;
    $record->tx_hash = '5xa...someSig';
    $record->save();

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('getUserOperationStatus');

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('submitted'); // unchanged
});

it('skips records older than 2 hours (avoids unbounded backlog)', function (): void {
    $record = makeSubmittedRecord('polygon', '0xUserOpStale');
    $record->submitted_at = now()->subHours(3);
    $record->save();

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('getUserOperationStatus');

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('submitted');
});

it('logs and continues when bundler throws', function (): void {
    $record = makeSubmittedRecord('polygon', '0xBundlerThrows');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->andThrow(new RuntimeException('Bundler RPC down'));

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('submitted'); // unchanged — caught & logged
});

it('persists the sponsored fee from the bundler actualGasCost (hex wei → decimal string)', function (): void {
    $record = makeSubmittedRecord('polygon', '0xUserOpWithCost');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->andReturn([
            'status'  => 'confirmed',
            'tx_hash' => '0xcostTxHash',
            'receipt' => [
                'success'     => true,
                'gasUsed'     => 150000,
                'blockNumber' => 1,
                // 0x2e90edd000 = 200_000_000_000 wei
                'actualGasCost'     => '0x2e90edd000',
                'txGasUsed'         => '0x5208',
                'effectiveGasPrice' => '0x3b9aca00',
            ],
        ]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('confirmed')
        ->and($record->sponsored_fee_raw)->toBe('200000000000')
        ->and($record->sponsored_fee_asset)->toBe('MATIC');
});

it('falls back to gasUsed * effectiveGasPrice when actualGasCost is absent', function (): void {
    $record = makeSubmittedRecord('base', '0xUserOpFallbackCost');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->andReturn([
            'status'  => 'confirmed',
            'tx_hash' => '0xfallbackTxHash',
            'receipt' => [
                'success'     => true,
                'gasUsed'     => 21000,
                'blockNumber' => 1,
                // 0x5208 = 21000 gas * 0x3b9aca00 = 1 gwei → 21_000_000_000_000 wei
                'actualGasCost'     => null,
                'txGasUsed'         => '0x5208',
                'effectiveGasPrice' => '0x3b9aca00',
            ],
        ]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('confirmed')
        ->and($record->sponsored_fee_raw)->toBe('21000000000000')
        ->and($record->sponsored_fee_asset)->toBe('ETH-BASE');
});

it('confirms without a sponsored fee when the receipt has no usable cost fields', function (): void {
    $record = makeSubmittedRecord('arbitrum', '0xUserOpNoCostFields');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')
        ->once()
        ->andReturn([
            'status'  => 'confirmed',
            'tx_hash' => '0xnoCostTxHash',
            'receipt' => ['success' => true, 'gasUsed' => 0, 'blockNumber' => 1],
        ]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class));

    $record->refresh();
    expect($record->status)->toBe('confirmed')
        ->and($record->sponsored_fee_raw)->toBeNull()
        ->and($record->sponsored_fee_asset)->toBeNull();
});

/**
 * A send whose broadcast outcome was unknown, prepared $minutesSincePrepare
 * minutes ago with a verifying-paymaster sponsorship valid until
 * $validUntilAfterPrepare seconds after prepare (0 = no expiry).
 */
function makeUncertainRecord(int $minutesSincePrepare, int $validUntilAfterPrepare = 600, int $nonce = 7): WalletSendRecord
{
    $preparedAt = now()->subMinutes($minutesSincePrepare);
    $validUntil = $validUntilAfterPrepare === 0 ? 0 : $preparedAt->getTimestamp() + $validUntilAfterPrepare;

    $record = makeSubmittedRecord('arbitrum', '0xUncertain');
    $record->sender_address = '0x1111111111111111111111111111111111111111';
    $record->metadata = [
        'submit_outcome' => 'unknown',
        'submit_error'   => 'RPC connection failed: cURL error 28',
        'user_op'        => [
            'sender'           => '0x1111111111111111111111111111111111111111',
            'nonce'            => '0x' . dechex($nonce),
            'paymasterAndData' => '0x' . str_repeat('ab', 20) . '00'
                . str_pad(dechex($validUntil), 12, '0', STR_PAD_LEFT) . str_repeat('0', 12) . str_repeat('cd', 65),
        ],
    ];
    $record->created_at = $preparedAt;
    $record->submitted_at = $preparedAt->copy()->addSeconds(20);
    $record->save();

    return $record;
}

/** @return BundlerInterface&Mockery\MockInterface */
function pendingBundler(): BundlerInterface
{
    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')->andReturn(['status' => 'pending', 'tx_hash' => null, 'receipt' => null]);

    return $bundler;
}

/** @return EntryPointNonces&Mockery\MockInterface */
function nonceReader(): EntryPointNonces
{
    /** @var EntryPointNonces&Mockery\MockInterface $nonces */
    $nonces = Mockery::mock(EntryPointNonces::class);

    return $nonces;
}

it('fails an uncertain send once its sponsorship has expired and the operation never ran', function (): void {
    $record = makeUncertainRecord(16);
    $nonces = nonceReader();
    $nonces->shouldReceive('sequence')->once()->with('0x1111111111111111111111111111111111111111', SupportedNetwork::ARBITRUM)->andReturn(7);

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    $record->refresh();
    expect($record->status)->toBe('failed')
        ->and($record->error_code)->toBe('BUNDLER_NOT_ACCEPTED')
        ->and($record->error_message)->toBe('This transfer did not reach the network. No funds were moved.')
        ->and($record->failed_at)->not->toBeNull();
});

it('keeps waiting while the sponsorship can still be used', function (): void {
    $record = makeUncertainRecord(12);
    $nonces = nonceReader();
    $nonces->shouldNotReceive('sequence');

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    expect($record->refresh()->status)->toBe('submitted');
});

it('waits for a longer sponsorship written in the paymaster data', function (): void {
    $record = makeUncertainRecord(20, validUntilAfterPrepare: 3600);
    $nonces = nonceReader();
    $nonces->shouldNotReceive('sequence');

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    expect($record->refresh()->status)->toBe('submitted');
});

it('never fails an uncertain send whose sponsorship has no expiry', function (): void {
    $record = makeUncertainRecord(180, validUntilAfterPrepare: 0);
    $nonces = nonceReader();
    $nonces->shouldNotReceive('sequence');

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    expect($record->refresh()->status)->toBe('submitted');
});

it('leaves an uncertain send for review when its nonce was used but no receipt was found', function (): void {
    $record = makeUncertainRecord(16);
    $nonces = nonceReader();
    $nonces->shouldReceive('sequence')->once()->andReturn(8);

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);
    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    $record->refresh();
    expect($record->status)->toBe('submitted')
        ->and($record->metadata['submit_review'] ?? null)->toBe('nonce_used_without_receipt');
});

it('keeps waiting on an uncertain send when the nonce cannot be read', function (): void {
    $record = makeUncertainRecord(16);
    $nonces = nonceReader();
    $nonces->shouldReceive('sequence')->once()->andThrow(new RuntimeException('RPC down'));

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    expect($record->refresh()->status)->toBe('submitted');
});

it('never expires a send the bundler accepted', function (): void {
    $record = makeSubmittedRecord('polygon', '0xAccepted');
    $record->created_at = now()->subMinutes(30);
    $record->submitted_at = now()->subMinutes(30);
    $record->save();
    $nonces = nonceReader();
    $nonces->shouldNotReceive('sequence');

    (new PollEvmWalletSendConfirmations())->handle(pendingBundler(), app(SponsorshipCostTracker::class), $nonces);

    expect($record->refresh()->status)->toBe('submitted');
});

it('keeps checking an uncertain send after the two-hour window', function (): void {
    $record = makeUncertainRecord(180, validUntilAfterPrepare: 0);

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('getUserOperationStatus')->once()->with('0xUncertain')
        ->andReturn(['status' => 'confirmed', 'tx_hash' => '0xtx', 'receipt' => null]);

    (new PollEvmWalletSendConfirmations())->handle($bundler, app(SponsorshipCostTracker::class), nonceReader());

    expect($record->refresh()->status)->toBe('confirmed');
});
