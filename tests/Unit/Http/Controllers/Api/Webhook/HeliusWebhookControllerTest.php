<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Webhook\HeliusWebhookController;
use App\Jobs\ProcessHeliusWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['services.helius.webhook_secret' => '']);
    config(['cache.default' => 'array']);
    Queue::fake();
});

/** @return array<string, mixed> */
function makeHeliusTx(string $signature, string $from, string $to, string $amount = '10.5', string $mint = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'): array
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
function makeNativeSolTx(string $signature, string $from, string $to, int $lamports = 1000000000): array
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

it('dispatches job and returns queued for valid payload', function (): void {
    $controller = app(HeliusWebhookController::class);
    $tx = makeHeliusTx('sig_incoming_001', 'SenderAddr456', 'ReceiverAddr123', '25.50');

    $request = Request::create('/api/webhooks/helius/solana', 'POST', [], [], [], [], (string) json_encode([$tx]));
    $request->headers->set('Content-Type', 'application/json');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['status'])->toBe('queued');

    Queue::assertPushed(ProcessHeliusWebhookJob::class, 1);
});

it('dispatches job with multiple transactions', function (): void {
    $controller = app(HeliusWebhookController::class);
    $tx1 = makeHeliusTx('sig_001', 'Sender1', 'Receiver1');
    $tx2 = makeNativeSolTx('sig_002', 'Sender2', 'Receiver2');

    $request = Request::create('/api/webhooks/helius/solana', 'POST', [], [], [], [], (string) json_encode([$tx1, $tx2]));
    $request->headers->set('Content-Type', 'application/json');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    Queue::assertPushed(ProcessHeliusWebhookJob::class, 1);
});

it('rejects requests with wrong webhook secret', function (): void {
    config(['services.helius.webhook_secret' => 'correct-secret']);

    $controller = app(HeliusWebhookController::class);
    $request = Request::create('/api/webhooks/helius/solana', 'POST');
    $request->headers->set('Authorization', 'wrong-secret');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(401);
    Queue::assertNotPushed(ProcessHeliusWebhookJob::class);
});

it('rejects requests without authorization header when secret is set', function (): void {
    config(['services.helius.webhook_secret' => 'my-secret']);

    $controller = app(HeliusWebhookController::class);
    $request = Request::create('/api/webhooks/helius/solana', 'POST');

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(401);
    Queue::assertNotPushed(ProcessHeliusWebhookJob::class);
});
