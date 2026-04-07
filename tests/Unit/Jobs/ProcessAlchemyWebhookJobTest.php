<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Jobs\ProcessAlchemyWebhookJob;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesSolanaTestTables;

uses(TestCase::class, CreatesSolanaTestTables::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    $this->createSolanaTestTables();

    // Create smart_accounts table for tests
    if (! Schema::hasTable('smart_accounts')) {
        Schema::create('smart_accounts', function ($table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('owner_address', 42);
            $table->string('account_address', 42);
            $table->string('network', 20);
            $table->boolean('deployed')->default(false);
            $table->string('deploy_tx_hash', 66)->nullable();
            $table->unsignedBigInteger('nonce')->default(0);
            $table->unsignedInteger('pending_ops')->default(0);
            $table->timestamps();
            $table->unique(['owner_address', 'network']);
            $table->index('account_address');
        });
    }

    Event::fake([WalletBalanceUpdated::class]);
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
    Schema::dropIfExists('smart_accounts');
});

/**
 * Build an Alchemy EVM payload.
 *
 * @param array<int, array<string, mixed>> $activities
 * @return array<string, mixed>
 */
function makeEvmPayload(string $network, array $activities): array
{
    return [
        'type'  => 'ADDRESS_ACTIVITY',
        'event' => [
            'network'  => $network,
            'activity' => $activities,
        ],
    ];
}

it('processes USDC token transfer for known blockchain address', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'ethereum',
        'address'    => '0x2222222222222222222222222222222222222222',
        'public_key' => '0x2222222222222222222222222222222222222222',
        'is_active'  => true,
    ]);

    $payload = makeEvmPayload('eth-mainnet', [
        [
            'category'    => 'token',
            'hash'        => '0xabc123',
            'fromAddress' => '0x1111111111111111111111111111111111111111',
            'toAddress'   => '0x2222222222222222222222222222222222222222',
            'value'       => '100.00',
            'asset'       => 'USDC',
        ],
    ]);

    /** @var WalletBalanceProviderInterface&Mockery\MockInterface $balanceProvider */
    $balanceProvider = Mockery::mock(WalletBalanceProviderInterface::class);
    $balanceProvider->shouldReceive('invalidateCache');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessAlchemyWebhookJob($payload);
    $job->handle($balanceProvider, $pushService);

    Event::assertDispatched(WalletBalanceUpdated::class, function (WalletBalanceUpdated $event) use ($user): bool {
        return $event->userId === $user->id && $event->chainId === 'ethereum';
    });
});

it('resolves user from smart_accounts table', function (): void {
    $user = User::factory()->create();
    SmartAccount::create([
        'user_id'         => $user->id,
        'owner_address'   => '0x3333333333333333333333333333333333333333',
        'account_address' => '0x4444444444444444444444444444444444444444',
        'network'         => 'polygon',
        'deployed'        => true,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);

    $payload = makeEvmPayload('polygon-mainnet', [
        [
            'category'    => 'erc20',
            'hash'        => '0xdef456',
            'fromAddress' => '0x5555555555555555555555555555555555555555',
            'toAddress'   => '0x4444444444444444444444444444444444444444',
            'value'       => '50.00',
            'asset'       => 'USDT',
        ],
    ]);

    /** @var WalletBalanceProviderInterface&Mockery\MockInterface $balanceProvider */
    $balanceProvider = Mockery::mock(WalletBalanceProviderInterface::class);
    $balanceProvider->shouldReceive('invalidateCache');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessAlchemyWebhookJob($payload);
    $job->handle($balanceProvider, $pushService);

    Event::assertDispatched(WalletBalanceUpdated::class, function (WalletBalanceUpdated $event) use ($user): bool {
        return $event->userId === $user->id && $event->chainId === 'polygon';
    });
});

it('filters out spam tokens (non-USDC/USDT)', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'ethereum',
        'address'    => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'public_key' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'is_active'  => true,
    ]);

    $payload = makeEvmPayload('eth-mainnet', [
        [
            'category'    => 'token',
            'hash'        => '0xspam1',
            'fromAddress' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'toAddress'   => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'value'       => '999999',
            'asset'       => 'SCAM_TOKEN',
        ],
    ]);

    /** @var WalletBalanceProviderInterface&Mockery\MockInterface $balanceProvider */
    $balanceProvider = Mockery::mock(WalletBalanceProviderInterface::class);
    $balanceProvider->shouldNotReceive('invalidateCache');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldNotReceive('sendTransactionReceived');
    $pushService->shouldNotReceive('sendTransactionSent');

    $job = new ProcessAlchemyWebhookJob($payload);
    $job->handle($balanceProvider, $pushService);

    Event::assertNotDispatched(WalletBalanceUpdated::class);
});

it('skips activities with removed flag (block reorg)', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'ethereum',
        'address'    => '0xcccccccccccccccccccccccccccccccccccccccc',
        'public_key' => '0xcccccccccccccccccccccccccccccccccccccccc',
        'is_active'  => true,
    ]);

    $payload = makeEvmPayload('eth-mainnet', [
        [
            'category'    => 'token',
            'hash'        => '0xreorged',
            'fromAddress' => '0xdddddddddddddddddddddddddddddddddddddddd',
            'toAddress'   => '0xcccccccccccccccccccccccccccccccccccccccc',
            'value'       => '100.00',
            'asset'       => 'USDC',
            'removed'     => true,
        ],
    ]);

    /** @var WalletBalanceProviderInterface&Mockery\MockInterface $balanceProvider */
    $balanceProvider = Mockery::mock(WalletBalanceProviderInterface::class);
    $balanceProvider->shouldNotReceive('invalidateCache');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldNotReceive('sendTransactionReceived');

    $job = new ProcessAlchemyWebhookJob($payload);
    $job->handle($balanceProvider, $pushService);

    Event::assertNotDispatched(WalletBalanceUpdated::class);
});

it('deduplicates notifications per user within batch', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'ethereum',
        'address'    => '0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
        'public_key' => '0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
        'is_active'  => true,
    ]);

    $payload = makeEvmPayload('eth-mainnet', [
        [
            'category'    => 'token',
            'hash'        => '0xtx1',
            'fromAddress' => '0xfffffffffffffffffffffffffffffffffffffff1',
            'toAddress'   => '0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
            'value'       => '50.00',
            'asset'       => 'USDC',
        ],
        [
            'category'    => 'token',
            'hash'        => '0xtx2',
            'fromAddress' => '0xfffffffffffffffffffffffffffffffffffffff2',
            'toAddress'   => '0xeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
            'value'       => '25.00',
            'asset'       => 'USDT',
        ],
    ]);

    /** @var WalletBalanceProviderInterface&Mockery\MockInterface $balanceProvider */
    $balanceProvider = Mockery::mock(WalletBalanceProviderInterface::class);
    $balanceProvider->shouldReceive('invalidateCache');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessAlchemyWebhookJob($payload);
    $job->handle($balanceProvider, $pushService);

    // Only one broadcast despite two activities for same user
    Event::assertDispatched(WalletBalanceUpdated::class, 1);
});
