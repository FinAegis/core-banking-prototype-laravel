<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Traits\CreatesSolanaTestTables;

uses(CreatesSolanaTestTables::class);

beforeEach(function (): void {
    config([
        'cache.default'              => 'array',
        'services.helius.webhook_id' => 'test-webhook-id',
        'services.helius.api_key'    => 'test-api-key',
    ]);

    $this->createSolanaTestTables();
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

it('sends api-key as a query parameter on the PUT request, not in the body', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => '9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L',
        'public_key' => '9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L',
        'is_active'  => true,
    ]);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'webhookID'        => 'test-webhook-id',
            'accountAddresses' => ['9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L'],
        ], 200),
    ]);

    $count = (new HeliusWebhookSyncService())->syncAllAddresses();

    expect($count)->toBe(1);

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        // api-key MUST be in the URL query string
        if (! str_contains($request->url(), 'api-key=test-api-key')) {
            return false;
        }

        // and MUST NOT leak into the JSON body
        $body = $request->data();

        return ! array_key_exists('api-key', $body)
            && isset($body['accountAddresses'])
            && in_array('9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L', $body['accountAddresses'], true);
    });
});

it('returns 0 when the upstream Helius PUT fails', function (): void {
    $user = User::factory()->create();
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => '9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L',
        'public_key' => '9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L',
        'is_active'  => true,
    ]);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32401, 'message' => 'missing api key'],
        ], 401),
    ]);

    $count = (new HeliusWebhookSyncService())->syncAllAddresses();

    expect($count)->toBe(0);
});
