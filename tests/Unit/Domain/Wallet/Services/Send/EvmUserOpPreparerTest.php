<?php

declare(strict_types=1);

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Exceptions\IdempotencyConflictException;
use App\Domain\Wallet\Exceptions\InvalidAddressException;
use App\Domain\Wallet\Exceptions\InvalidAmountException;
use App\Domain\Wallet\Exceptions\InvalidAssetException;
use App\Domain\Wallet\Exceptions\NetworkDisabledException;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\EvmUserOpPreparer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'wallet.evm.enabled_networks' => ['polygon', 'base', 'arbitrum', 'ethereum'],
        'wallet.evm.fee_token'        => 'USDC',
    ]);

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
 * @return array{
 *   bundler: BundlerInterface&MockInterface,
 *   paymaster: PaymasterInterface&MockInterface,
 *   smartAccountService: SmartAccountService&MockInterface
 * }
 */
function makeEvmPreparerMocks(): array
{
    /** @var BundlerInterface&MockInterface $bundler */
    $bundler = Mockery::mock(BundlerInterface::class);
    // Bundler is only consulted for the EntryPoint address in the prepare path.
    $bundler->shouldReceive('getEntryPointAddress')
        ->andReturn(EvmTokens::ENTRY_POINT_V06)
        ->byDefault();

    /** @var PaymasterInterface&MockInterface $paymaster */
    $paymaster = Mockery::mock(PaymasterInterface::class);

    /** @var SmartAccountService&MockInterface $smartAccountService */
    $smartAccountService = Mockery::mock(SmartAccountService::class);
    // Default: no local account record → init code defaults to '0x', nonce = 0.
    $smartAccountService->shouldReceive('getByAccountAddress')->andReturn(null)->byDefault();

    return [
        'bundler'             => $bundler,
        'paymaster'           => $paymaster,
        'smartAccountService' => $smartAccountService,
    ];
}

/**
 * @return array{
 *   paymasterAndData: string,
 *   callGasLimit: int,
 *   verificationGasLimit: int,
 *   preVerificationGas: int,
 *   maxFeePerGas: int,
 *   maxPriorityFeePerGas: int
 * }
 */
function makeSponsorshipResult(): array
{
    return [
        'paymasterAndData'     => '0x' . str_repeat('ab', 100), // any non-empty blob
        'callGasLimit'         => 120_000,
        'verificationGasLimit' => 200_000,
        'preVerificationGas'   => 60_000,
        'maxFeePerGas'         => 40_000_000_000,
        'maxPriorityFeePerGas' => 2_000_000_000,
    ];
}

const EVM_SENDER_ADDRESS = '0x1111111111111111111111111111111111111111';
const EVM_RECIPIENT_ADDRESS = '0x2222222222222222222222222222222222222222';

it('builds a sponsored UserOp on polygon and persists a pending record', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $sponsorship = makeSponsorshipResult();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->withArgs(function ($userOp, SupportedNetwork $network, string $entryPoint): bool {
            return $network === SupportedNetwork::POLYGON
                && $entryPoint === EvmTokens::ENTRY_POINT_V06;
        })
        ->andReturn($sponsorship);

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $result = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.5',
        idempotencyKey: null,
        quoteId: null,
    );

    expect($result['record'])->toBeInstanceOf(WalletSendRecord::class)
        ->and($result['record']->status)->toBe(WalletSendRecord::STATUS_PENDING)
        ->and($result['record']->network)->toBe('polygon')
        ->and($result['record']->asset)->toBe('USDC')
        ->and((string) $result['record']->amount)->toBe('1.50000000')
        ->and($result['record']->user_op_hash)->toStartWith('0x')
        ->and(strlen((string) $result['record']->user_op_hash))->toBe(66) // '0x' + 64 hex
        ->and($result['payload']['kind'])->toBe('userop')
        ->and($result['payload']['network'])->toBe('polygon')
        ->and($result['payload']['chain_id'])->toBe(137)
        ->and($result['payload']['entry_point'])->toBe(EvmTokens::ENTRY_POINT_V06)
        ->and($result['payload']['user_op_hash'])->toBe($result['record']->user_op_hash)
        ->and($result['payload']['user_op']['paymasterAndData'])->toBe($sponsorship['paymasterAndData'])
        ->and($result['payload']['user_op']['signature'])->toBe('0x')
        ->and($result['payload']['user_op']['sender'])->toBe(EVM_SENDER_ADDRESS);

    // Metadata round-trip
    $metadata = $result['record']->metadata ?? [];
    expect($metadata['entry_point'])->toBe(EvmTokens::ENTRY_POINT_V06)
        ->and($metadata['chain_id'])->toBe(137)
        ->and($metadata['atomic_amount'])->toBe('1500000') // 1.5 USDC = 1_500_000 atomic
        ->and($metadata['token_address'])->toBe(EvmTokens::USDC['polygon'])
        ->and(is_array($metadata['user_op']))->toBeTrue();
});

it('builds a sponsored UserOp on base', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->withArgs(fn ($_op, SupportedNetwork $n) => $n === SupportedNetwork::BASE)
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $result = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'base',
        '0.5',
        null,
        null,
    );

    expect($result['record']->network)->toBe('base')
        ->and($result['payload']['chain_id'])->toBe(8453);
});

it('builds a sponsored UserOp on arbitrum', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $result = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDT',
        'arbitrum',
        '10',
        null,
        null,
    );

    expect($result['record']->network)->toBe('arbitrum')
        ->and($result['record']->asset)->toBe('USDT')
        ->and($result['payload']['chain_id'])->toBe(42161);
});

it('builds a sponsored UserOp on ethereum', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $result = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'ethereum',
        '2',
        null,
        null,
    );

    expect($result['payload']['chain_id'])->toBe(1)
        ->and($result['record']->network)->toBe('ethereum');
});

it('throws NetworkDisabledException when network is not in enabled_networks', function (): void {
    config(['wallet.evm.enabled_networks' => ['polygon']]); // base disabled

    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'base',
        '1',
        null,
        null,
    );
})->throws(NetworkDisabledException::class);

it('throws InvalidAssetException when USDT is requested on Base (no canonical contract)', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDT',
        'base',
        '1',
        null,
        null,
    );
})->throws(InvalidAssetException::class);

it('throws InvalidAssetException for an unsupported symbol', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'DAI',
        'polygon',
        '1',
        null,
        null,
    );
})->throws(InvalidAssetException::class);

it('throws InvalidAddressException for a malformed recipient address', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        'not-a-real-address',
        'USDC',
        'polygon',
        '1',
        null,
        null,
    );
})->throws(InvalidAddressException::class);

it('throws InvalidAddressException for a malformed sender address', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        '0xnotvalid',
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1',
        null,
        null,
    );
})->throws(InvalidAddressException::class);

it('throws InvalidAmountException for a non-numeric amount', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        'not-a-number',
        null,
        null,
    );
})->throws(InvalidAmountException::class);

it('throws InvalidAmountException for excess decimal precision (USDC has 6 decimals)', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();
    $mocks['paymaster']->shouldNotReceive('sponsor');

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.5000001', // 7 decimals — exceeds USDC's 6
        null,
        null,
    );
})->throws(InvalidAmountException::class);

it('records a failed row with PAYMASTER_REJECTED when sponsorship throws', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->andThrow(new RuntimeException('paymaster declined: out of funds'));

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $result = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1',
        null,
        null,
    );

    expect($result['record']->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($result['record']->error_code)->toBe('PAYMASTER_REJECTED')
        ->and($result['record']->error_message)->toContain('paymaster declined')
        ->and($result['record']->failed_at)->not->toBeNull()
        ->and($result['payload']['user_op_hash'])->toBe(''); // hash not computed when sponsorship fails
});

it('returns the existing record when the same idempotency key is reused with the same body', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once() // only the FIRST call hits the paymaster
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $first = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.0',
        'evm-idem-key',
        null,
    );

    $second = $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.0',
        'evm-idem-key',
        null,
    );

    expect($second['record']->id)->toBe($first['record']->id)
        ->and($second['payload']['user_op_hash'])->toBe($first['payload']['user_op_hash'])
        ->and($second['payload']['user_op']['paymasterAndData'])
            ->toBe($first['payload']['user_op']['paymasterAndData']);
});

it('throws IdempotencyConflictException when the same key is reused with a different recipient', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.0',
        'evm-conflict-key',
        null,
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        '0x3333333333333333333333333333333333333333', // different recipient
        'USDC',
        'polygon',
        '1.0',
        'evm-conflict-key',
        null,
    );
})->throws(IdempotencyConflictException::class);

it('throws IdempotencyConflictException when the same key is reused on a different network', function (): void {
    $user = User::factory()->create();
    $mocks = makeEvmPreparerMocks();

    $mocks['paymaster']->shouldReceive('sponsor')
        ->once()
        ->andReturn(makeSponsorshipResult());

    $preparer = new EvmUserOpPreparer(
        $mocks['bundler'],
        $mocks['paymaster'],
        $mocks['smartAccountService'],
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'polygon',
        '1.0',
        'evm-network-key',
        null,
    );

    $preparer->prepare(
        $user,
        EVM_SENDER_ADDRESS,
        EVM_RECIPIENT_ADDRESS,
        'USDC',
        'base', // different network
        '1.0',
        'evm-network-key',
        null,
    );
})->throws(IdempotencyConflictException::class);
