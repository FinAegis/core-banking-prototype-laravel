<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\WebhookEndpoint;
use App\Http\Controllers\Api\Webhook\AlchemyWebhookController;
use App\Jobs\ProcessAlchemyWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);

    // Create webhook_endpoints table for DB-based signing keys
    if (! Schema::hasTable('webhook_endpoints')) {
        Schema::create('webhook_endpoints', function ($table): void {
            $table->id();
            $table->string('provider', 20);
            $table->string('network', 30);
            $table->unsignedInteger('shard')->default(0);
            $table->string('external_webhook_id');
            $table->text('signing_key');
            $table->string('webhook_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('address_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'network', 'shard']);
            $table->index(['provider', 'network', 'is_active']);
        });
    }

    // Store signing key in DB instead of config
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'ethereum',
        'shard'               => 0,
        'external_webhook_id' => 'wh_test',
        'signing_key'         => 'test-signing-key',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 0,
    ]);

    Queue::fake();
});

/**
 * Build an Alchemy ADDRESS_ACTIVITY webhook payload.
 *
 * @return array<string, mixed>
 */
function makeAlchemyPayload(
    string $network = 'eth-mainnet',
    string $asset = 'USDC',
    string $category = 'token',
): array {
    return [
        'type'  => 'ADDRESS_ACTIVITY',
        'event' => [
            'network'  => $network,
            'activity' => [
                [
                    'category'    => $category,
                    'hash'        => '0xabc123',
                    'fromAddress' => '0x1111111111111111111111111111111111111111',
                    'toAddress'   => '0x2222222222222222222222222222222222222222',
                    'value'       => '100.00',
                    'asset'       => $asset,
                ],
            ],
        ],
    ];
}

/**
 * Create a signed request for the Alchemy webhook.
 *
 * @param array<string, mixed> $payload
 */
function makeSignedAlchemyRequest(array $payload, string $signingKey = 'test-signing-key'): Request
{
    $body = (string) json_encode($payload);
    $signature = hash_hmac('sha256', $body, $signingKey);

    return Request::create('/api/webhooks/alchemy/address-activity', 'POST', [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $signature,
        'CONTENT_TYPE'             => 'application/json',
    ], $body);
}

it('dispatches job and returns queued for valid EVM payload', function (): void {
    $payload = makeAlchemyPayload('eth-mainnet');
    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['status'])->toBe('queued');

    Queue::assertPushed(ProcessAlchemyWebhookJob::class, 1);
});

it('rejects requests with invalid signature', function (): void {
    $payload = makeAlchemyPayload();
    $body = (string) json_encode($payload);
    $wrongSignature = hash_hmac('sha256', $body, 'wrong-key');

    $request = Request::create('/api/webhooks/alchemy/address-activity', 'POST', [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $wrongSignature,
        'CONTENT_TYPE'             => 'application/json',
    ], $body);

    $controller = app(AlchemyWebhookController::class);
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true)['error'])->toBe('Invalid signature');

    Queue::assertNotPushed(ProcessAlchemyWebhookJob::class);
});

it('ignores Solana webhooks and returns ignored status', function (): void {
    $payload = makeAlchemyPayload('sol-mainnet');
    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['status'])->toBe('ignored');
    expect($response->getData(true)['reason'])->toBe('solana handled by helius');

    Queue::assertNotPushed(ProcessAlchemyWebhookJob::class);
});

it('ignores non-ADDRESS_ACTIVITY webhook types', function (): void {
    $payload = ['type' => 'MINED_TRANSACTION', 'event' => []];
    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['status'])->toBe('ignored');

    Queue::assertNotPushed(ProcessAlchemyWebhookJob::class);
});

it('accepts valid signature from DB-stored signing key', function (): void {
    $payload = makeAlchemyPayload('sol-mainnet');
    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    // Should not be 401 — signature is valid
    expect($response->getStatusCode())->toBe(200);
    // Solana payloads are now ignored
    expect($response->getData(true)['status'])->toBe('ignored');
});
