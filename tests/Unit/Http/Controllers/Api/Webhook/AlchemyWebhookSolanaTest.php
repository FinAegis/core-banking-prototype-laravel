<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Models\WebhookEndpoint;
use App\Http\Controllers\Api\Webhook\AlchemyWebhookController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesSolanaTestTables;

uses(TestCase::class, CreatesSolanaTestTables::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    $this->createSolanaTestTables();

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
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

/**
 * Build an Alchemy ADDRESS_ACTIVITY webhook payload for a Solana SPL token transfer.
 *
 * @return array<string, mixed>
 */
function makeAlchemySolanaTokenPayload(
    string $hash,
    string $from,
    string $to,
    string $value = '25.50',
    string $mint = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
    string $network = 'sol-mainnet',
): array {
    return [
        'type'  => 'ADDRESS_ACTIVITY',
        'event' => [
            'network'  => $network,
            'activity' => [
                [
                    'category'    => 'token',
                    'hash'        => $hash,
                    'fromAddress' => $from,
                    'toAddress'   => $to,
                    'value'       => $value,
                    'asset'       => 'USDC',
                    'rawContract' => [
                        'address' => $mint,
                    ],
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

    $request = Request::create('/api/webhooks/alchemy/address-activity', 'POST', [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $signature,
        'CONTENT_TYPE'             => 'application/json',
    ], $body);

    return $request;
}

it('ignores Solana webhooks and returns ignored status', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => '7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU',
        'public_key' => '7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU',
        'is_active'  => true,
    ]);

    $payload = makeAlchemySolanaTokenPayload(
        'alchemy_sol_sig_001',
        'ExternalSender123',
        '7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU',
        '25.50',
    );

    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    $data = $response->getData(true);
    expect($data['status'])->toBe('ignored');
    expect($data['reason'])->toBe('solana handled by helius');
});

it('verifies HMAC signature using DB-stored keys', function (): void {
    $payload = makeAlchemySolanaTokenPayload(
        'alchemy_auth_001',
        'From',
        'To',
    );

    $controller = app(AlchemyWebhookController::class);

    // Send with wrong signature
    $body = (string) json_encode($payload);
    $wrongSignature = hash_hmac('sha256', $body, 'wrong-key');

    $request = Request::create('/api/webhooks/alchemy/address-activity', 'POST', [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $wrongSignature,
        'CONTENT_TYPE'             => 'application/json',
    ], $body);

    $response = $controller->handle($request);

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true)['error'])->toBe('Invalid signature');
});

it('accepts valid signature from DB-stored signing key', function (): void {
    $payload = makeAlchemySolanaTokenPayload(
        'alchemy_valid_sig_001',
        'From',
        'To',
    );

    $controller = app(AlchemyWebhookController::class);
    $request = makeSignedAlchemyRequest($payload);
    $response = $controller->handle($request);

    // Should not be 401 — signature is valid
    expect($response->getStatusCode())->toBe(200);
    // Solana payloads are now ignored
    expect($response->getData(true)['status'])->toBe('ignored');
});
