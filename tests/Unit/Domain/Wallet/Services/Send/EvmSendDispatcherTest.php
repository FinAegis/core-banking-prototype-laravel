<?php

declare(strict_types=1);

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\EvmSendDispatcher;
use App\Domain\Wallet\Services\Send\ServerOnlyUserOpSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    config()->set('wallet.evm.enabled_networks', ['polygon', 'base', 'arbitrum', 'ethereum']);
    config()->set('wallet.evm.fee_token', 'USDC');
});

/**
 * @param  array<string, mixed>|null $overrides
 * @return array<string, mixed>
 */
function makeDispatcherWithMocks(?array $overrides = null): array
{
    $overrides ??= [];

    /** @var SmartAccountService&Mockery\MockInterface $accounts */
    $accounts = $overrides['accounts'] ?? Mockery::mock(SmartAccountService::class);
    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = $overrides['bundler'] ?? Mockery::mock(BundlerInterface::class);
    /** @var PaymasterInterface&Mockery\MockInterface $paymaster */
    $paymaster = $overrides['paymaster'] ?? Mockery::mock(PaymasterInterface::class);

    $signer = $overrides['signer'] ?? new ServerOnlyUserOpSigner();

    $dispatcher = new EvmSendDispatcher($accounts, $bundler, $paymaster, $signer);

    return [
        'dispatcher' => $dispatcher,
        'accounts'   => $accounts,
        'bundler'    => $bundler,
        'paymaster'  => $paymaster,
        'signer'     => $signer,
    ];
}

test('disabled network is rejected with NETWORK_DISABLED', function (): void {
    config()->set('wallet.evm.enabled_networks', ['polygon']);
    $user = User::factory()->create();

    ['dispatcher' => $d] = makeDispatcherWithMocks();
    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'arbitrum',
        '1.0',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED);
    expect($record->error_code)->toBe('NETWORK_DISABLED');
});

test('unknown asset is rejected with INVALID_ASSET', function (): void {
    $user = User::factory()->create();

    ['dispatcher' => $d] = makeDispatcherWithMocks();
    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'NOTACOIN',
        'polygon',
        '1.0',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED);
    expect($record->error_code)->toBe('INVALID_ASSET');
});

test('zero amount is rejected with INVALID_AMOUNT', function (): void {
    $user = User::factory()->create();

    ['dispatcher' => $d] = makeDispatcherWithMocks();
    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'polygon',
        '0',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED);
    expect($record->error_code)->toBe('INVALID_AMOUNT');
});

test('successful USDC send on polygon creates a submitted record', function (): void {
    $user = User::factory()->create();

    /** @var SmartAccountService&Mockery\MockInterface $accounts */
    $accounts = Mockery::mock(SmartAccountService::class);
    $smartAccount = new SmartAccount([
        'user_id'         => $user->id,
        'owner_address'   => '0xownerowner',
        'account_address' => '0xabcdef0000000000000000000000000000000001',
        'network'         => 'polygon',
        'deployed'        => false,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);
    $accounts->shouldReceive('getOrCreateAccount')->once()->andReturn($smartAccount);
    $accounts->shouldReceive('needsInitCode')->once()->andReturn(false); // skip initCode in test
    $accounts->shouldReceive('incrementPendingOps')->once();

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('estimateUserOperationGas')->once()->andReturn([
        'callGasLimit'         => 100000,
        'verificationGasLimit' => 200000,
        'preVerificationGas'   => 50000,
    ]);
    $bundler->shouldReceive('submitUserOperation')->once()->andReturn('0x' . str_repeat('a', 64));

    /** @var PaymasterInterface&Mockery\MockInterface $paymaster */
    $paymaster = Mockery::mock(PaymasterInterface::class);
    $paymaster->shouldReceive('getPaymasterData')->once()->andReturn('0xdeadbeef');

    ['dispatcher' => $d] = makeDispatcherWithMocks([
        'accounts'  => $accounts,
        'bundler'   => $bundler,
        'paymaster' => $paymaster,
    ]);

    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'polygon',
        '1.5',
        idempotencyKey: 'idem-key-1',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_SUBMITTED);
    expect($record->user_op_hash)->toBe('0x' . str_repeat('a', 64));
    expect($record->submitted_at)->not->toBeNull();
    expect($record->error_code)->toBeNull();
    expect($record->sender_address)->toBe('0xabcdef0000000000000000000000000000000001');
    expect($record->idempotency_key)->toBe('idem-key-1');
});

test('idempotency: same key returns existing record without re-dispatch', function (): void {
    $user = User::factory()->create();

    $existing = WalletSendRecord::create([
        'public_id'         => 'pi_send_existing',
        'user_id'           => $user->id,
        'network'           => 'polygon',
        'asset'             => 'USDC',
        'amount'            => '1.50000000',
        'sender_address'    => '0xpriorprior',
        'recipient_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'status'            => WalletSendRecord::STATUS_SUBMITTED,
        'idempotency_key'   => 'idem-replay',
    ]);

    /** @var SmartAccountService&Mockery\MockInterface $accounts */
    $accounts = Mockery::mock(SmartAccountService::class);
    $accounts->shouldNotReceive('getOrCreateAccount');

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldNotReceive('submitUserOperation');

    /** @var PaymasterInterface&Mockery\MockInterface $paymaster */
    $paymaster = Mockery::mock(PaymasterInterface::class);

    ['dispatcher' => $d] = makeDispatcherWithMocks([
        'accounts' => $accounts, 'bundler' => $bundler, 'paymaster' => $paymaster,
    ]);

    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'polygon',
        '1.5',
        idempotencyKey: 'idem-replay',
    );

    expect($record->id)->toBe($existing->id);
    expect($record->status)->toBe(WalletSendRecord::STATUS_SUBMITTED);
});

test('bundler error during submit marks record as BUNDLER_REJECTED', function (): void {
    $user = User::factory()->create();

    /** @var SmartAccountService&Mockery\MockInterface $accounts */
    $accounts = Mockery::mock(SmartAccountService::class);
    $smartAccount = new SmartAccount([
        'user_id'         => $user->id,
        'owner_address'   => '0xownerowner',
        'account_address' => '0xabcdef0000000000000000000000000000000001',
        'network'         => 'polygon',
        'deployed'        => false,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);
    $accounts->shouldReceive('getOrCreateAccount')->once()->andReturn($smartAccount);
    $accounts->shouldReceive('needsInitCode')->once()->andReturn(false);

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('estimateUserOperationGas')->once()->andReturn([
        'callGasLimit'         => 100000,
        'verificationGasLimit' => 200000,
        'preVerificationGas'   => 50000,
    ]);
    $bundler->shouldReceive('submitUserOperation')->once()->andThrow(new RuntimeException('bundler down'));

    /** @var PaymasterInterface&Mockery\MockInterface $paymaster */
    $paymaster = Mockery::mock(PaymasterInterface::class);
    $paymaster->shouldReceive('getPaymasterData')->once()->andReturn('0xdeadbeef');

    ['dispatcher' => $d] = makeDispatcherWithMocks([
        'accounts' => $accounts, 'bundler' => $bundler, 'paymaster' => $paymaster,
    ]);

    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'polygon',
        '1.0',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED);
    expect($record->error_code)->toBe('BUNDLER_REJECTED');
    expect($record->error_message)->toContain('bundler down');
});

test('paymaster error marks record as PAYMASTER_REJECTED', function (): void {
    $user = User::factory()->create();

    /** @var SmartAccountService&Mockery\MockInterface $accounts */
    $accounts = Mockery::mock(SmartAccountService::class);
    $smartAccount = new SmartAccount([
        'user_id'         => $user->id,
        'owner_address'   => '0xownerowner',
        'account_address' => '0xabcdef0000000000000000000000000000000001',
        'network'         => 'polygon',
        'deployed'        => false,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);
    $accounts->shouldReceive('getOrCreateAccount')->once()->andReturn($smartAccount);
    $accounts->shouldReceive('needsInitCode')->once()->andReturn(false);

    /** @var BundlerInterface&Mockery\MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    $bundler->shouldReceive('estimateUserOperationGas')->once()->andReturn([
        'callGasLimit'         => 100000,
        'verificationGasLimit' => 200000,
        'preVerificationGas'   => 50000,
    ]);

    /** @var PaymasterInterface&Mockery\MockInterface $paymaster */
    $paymaster = Mockery::mock(PaymasterInterface::class);
    $paymaster->shouldReceive('getPaymasterData')->once()->andThrow(new RuntimeException('sponsorship denied'));

    ['dispatcher' => $d] = makeDispatcherWithMocks([
        'accounts' => $accounts, 'bundler' => $bundler, 'paymaster' => $paymaster,
    ]);

    $record = $d->dispatch(
        $user,
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'USDC',
        'polygon',
        '1.0',
    );

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED);
    expect($record->error_code)->toBe('PAYMASTER_REJECTED');
});
