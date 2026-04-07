<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Jobs\ProcessHeliusWebhookJob;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesSolanaTestTables;

uses(TestCase::class, CreatesSolanaTestTables::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    $this->createSolanaTestTables();

    Event::fake([WalletBalanceUpdated::class]);
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

/** @return array<string, mixed> */
function makeHeliusJobTx(string $signature, string $from, string $to, string $amount = '10.5', string $mint = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'): array
{
    return [
        'signature'      => $signature,
        'type'           => 'TRANSFER',
        'fee'            => 5000,
        'tokenTransfers' => [
            [
                'fromUserAccount' => $from,
                'toUserAccount'   => $to,
                'tokenAmount'     => $amount,
                'mint'            => $mint,
            ],
        ],
        'nativeTransfers' => [],
    ];
}

/** @return array<string, mixed> */
function makeNativeSolJobTx(string $signature, string $from, string $to, int $lamports = 1000000000): array
{
    return [
        'signature'       => $signature,
        'type'            => 'TRANSFER',
        'fee'             => 5000,
        'tokenTransfers'  => [],
        'nativeTransfers' => [
            [
                'fromUserAccount' => $from,
                'toUserAccount'   => $to,
                'amount'          => $lamports,
            ],
        ],
    ];
}

it('stores incoming token transaction in both tables', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'ReceiverAddr123',
        'public_key' => 'ReceiverAddr123',
        'is_active'  => true,
    ]);

    $tx = makeHeliusJobTx('sig_incoming_001', 'SenderAddr456', 'ReceiverAddr123', '25.50');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessHeliusWebhookJob([$tx]);
    $job->handle(app(HeliusTransactionProcessor::class), $pushService);

    // Verify blockchain transaction stored
    $btx = BlockchainTransaction::where('tx_hash', 'sig_incoming_001')->first();
    expect($btx)->not->toBeNull();
    assert($btx instanceof BlockchainTransaction);
    expect($btx->type)->toBe('receive');
    expect($btx->chain)->toBe('solana');
    expect($btx->status)->toBe('confirmed');
    expect($btx->from_address)->toBe('SenderAddr456');
    expect($btx->to_address)->toBe('ReceiverAddr123');

    // Verify activity feed item stored
    $feedItem = ActivityFeedItem::where('reference_id', HeliusTransactionProcessor::signatureToUuid('sig_incoming_001'))->first();
    expect($feedItem)->not->toBeNull();
    assert($feedItem instanceof ActivityFeedItem);
    expect($feedItem->activity_type->value)->toBe('transfer_in');
    expect($feedItem->asset)->toBe('USDC');
    expect($feedItem->network)->toBe('solana');
    expect($feedItem->status)->toBe('confirmed');
    expect((int) $feedItem->user_id)->toBe($user->id);
});

it('stores outgoing token transaction correctly', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'SenderAddr789',
        'public_key' => 'SenderAddr789',
        'is_active'  => true,
    ]);

    $tx = makeHeliusJobTx('sig_outgoing_001', 'SenderAddr789', 'ExternalAddr999', '5.00');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionSent')->once();

    $job = new ProcessHeliusWebhookJob([$tx]);
    $job->handle(app(HeliusTransactionProcessor::class), $pushService);

    $btx = BlockchainTransaction::where('tx_hash', 'sig_outgoing_001')->first();
    assert($btx instanceof BlockchainTransaction);
    expect($btx->type)->toBe('send');

    $feedItem = ActivityFeedItem::where('reference_id', HeliusTransactionProcessor::signatureToUuid('sig_outgoing_001'))->first();
    assert($feedItem instanceof ActivityFeedItem);
    expect($feedItem->activity_type->value)->toBe('transfer_out');
    expect((float) $feedItem->amount)->toBeLessThan(0); // Negative for outgoing
});

it('stores native SOL transfer with correct amount', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'SolReceiver',
        'public_key' => 'SolReceiver',
        'is_active'  => true,
    ]);

    $tx = makeNativeSolJobTx('sig_sol_001', 'SolSender', 'SolReceiver', 2000000000); // 2 SOL

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessHeliusWebhookJob([$tx]);
    $job->handle(app(HeliusTransactionProcessor::class), $pushService);

    $feedItem = ActivityFeedItem::where('reference_id', HeliusTransactionProcessor::signatureToUuid('sig_sol_001'))->first();
    assert($feedItem instanceof ActivityFeedItem);
    expect($feedItem->asset)->toBe('SOL');
    expect((float) $feedItem->amount)->toBeGreaterThan(1.9); // ~2.0 SOL
});

it('deduplicates transactions on retry via cache', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'DedupeAddr',
        'public_key' => 'DedupeAddr',
        'is_active'  => true,
    ]);

    $tx = makeHeliusJobTx('sig_dedupe_001', 'Sender', 'DedupeAddr');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    // First job run: processes the transaction
    $job1 = new ProcessHeliusWebhookJob([$tx]);
    $job1->handle(app(HeliusTransactionProcessor::class), $pushService);

    // Second job run (retry): should skip due to Cache::add dedup
    $job2 = new ProcessHeliusWebhookJob([$tx]);
    $job2->handle(app(HeliusTransactionProcessor::class), $pushService);

    expect(BlockchainTransaction::where('tx_hash', 'sig_dedupe_001')->count())->toBe(1);
    expect(ActivityFeedItem::where('reference_id', HeliusTransactionProcessor::signatureToUuid('sig_dedupe_001'))->count())->toBe(1);
});

it('resolves USDT token correctly', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'UsdtAddr',
        'public_key' => 'UsdtAddr',
        'is_active'  => true,
    ]);

    $tx = makeHeliusJobTx('sig_usdt_001', 'Sender', 'UsdtAddr', '100', 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB');

    /** @var PushNotificationService&Mockery\MockInterface $pushService */
    $pushService = Mockery::mock(PushNotificationService::class);
    $pushService->shouldReceive('sendTransactionReceived')->once();

    $job = new ProcessHeliusWebhookJob([$tx]);
    $job->handle(app(HeliusTransactionProcessor::class), $pushService);

    $feedItem = ActivityFeedItem::where('reference_id', HeliusTransactionProcessor::signatureToUuid('sig_usdt_001'))->first();
    assert($feedItem instanceof ActivityFeedItem);
    expect($feedItem->asset)->toBe('USDT');
});
