<?php

declare(strict_types=1);

namespace Tests\Feature\MCP;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use App\Domain\MCP\Tools\Wallet\WalletActivityTool;
use App\Domain\MCP\Tools\Wallet\WalletAddressesTool;
use App\Domain\MobilePayment\Enums\ActivityItemType;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// TestCase is already bound to tests/Feature via tests/Pest.php.

/**
 * End-to-end dispatch tests for the wallet read tools (wallet.addresses +
 * wallet.activity) through the JsonRpcRouter: catalog wiring, scope
 * enforcement, user-context requirement, happy paths against seeded rows,
 * and the wallet.activity limit clamp.
 */
function walletToolsRegisterReal(): void
{
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());
    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);

    $registry->register(new WalletAddressesTool());
    $registry->register(app(WalletActivityTool::class));
}

function walletToolsActAs(User $user): void
{
    Auth::shouldReceive('guard->user')->andReturn($user);
    Auth::shouldReceive('user')->andReturn($user);
}

/**
 * @param  array<string, mixed> $arguments
 * @param  list<string> $scopes
 * @return array<string, mixed>
 */
function walletToolsCall(string $tool, array $arguments = [], array $scopes = ['accounts:read'], ?int $userId = 1): array
{
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_wallet_test', 'cli', $userId, $scopes);

    return $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params'  => ['name' => $tool, 'arguments' => $arguments],
    ], $ctx);
}

beforeEach(function (): void {
    walletToolsRegisterReal();
});

it('lists both wallet tools in the catalog with titles, read-only flags and the accounts:read scope', function (): void {
    $catalog = (array) config('mcp.tools');

    foreach (['wallet.addresses', 'wallet.activity'] as $name) {
        expect($catalog)->toHaveKey($name);
        expect($catalog[$name]['title'])->toBeString()->not->toBe('');
        expect($catalog[$name]['scope'])->toBe('accounts:read');
        expect($catalog[$name]['is_write'])->toBeFalse();
        expect($catalog[$name]['requires_user'])->toBeTrue();
    }
});

it('exposes both wallet tools via tools/list with outputSchema and readOnlyHint', function (): void {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_wallet_test', 'cli', 1, ['accounts:read']);

    $resp = $router->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $ctx);

    $tools = collect($resp['result']['tools'])->keyBy('name');

    foreach (['wallet.addresses', 'wallet.activity'] as $name) {
        expect($tools)->toHaveKey($name);
        $tool = $tools[$name];
        expect($tool['annotations']['title'])->toBeString()->not->toBe('');
        expect($tool['annotations']['readOnlyHint'])->toBeTrue();
        expect($tool['annotations']['destructiveHint'])->toBeFalse();
        expect($tool['outputSchema']['type'] ?? null)->toBe('object');
    }
});

it('rejects both wallet tools with -32000 INSUFFICIENT_SCOPE when accounts:read is missing', function (): void {
    foreach (['wallet.addresses', 'wallet.activity'] as $name) {
        $resp = walletToolsCall($name, [], ['payments:read']);

        expect($resp['error']['code'])->toBe(-32000);
        expect($resp['error']['data']['required'])->toBe('accounts:read');
    }
});

it('rejects both wallet tools with -32006 USER_CONTEXT_REQUIRED on a client_credentials token', function (): void {
    foreach (['wallet.addresses', 'wallet.activity'] as $name) {
        $resp = walletToolsCall($name, [], ['accounts:read'], userId: null);

        expect($resp['error']['code'])->toBe(-32006);
        expect($resp['error']['data']['tool'])->toBe($name);
    }
});

it('returns -32004 when a wallet tool is disabled via its kill-switch', function (): void {
    /** @var array<string, mixed> $catalog */
    $catalog = (array) config('mcp.tools');
    $catalog['wallet.addresses']['enabled'] = false;
    config(['mcp.tools' => $catalog]);

    $resp = walletToolsCall('wallet.addresses');

    expect($resp['error']['code'])->toBe(-32004);
});

it('returns only the caller\'s registered addresses via user_uuid (never user_id)', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    walletToolsActAs($user);

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'polygon',
        'address'    => '0xabc0000000000000000000000000000000000001',
        'public_key' => 'pk-1',
        'is_active'  => true,
    ]);
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'So1anaAddre55Case5ensitive1111111111111111',
        'public_key' => 'pk-2',
        'is_active'  => false,
        'label'      => 'phone wallet',
    ]);
    BlockchainAddress::create([
        'user_uuid'  => $other->uuid,
        'chain'      => 'polygon',
        'address'    => '0xdef0000000000000000000000000000000000002',
        'public_key' => 'pk-3',
        'is_active'  => true,
    ]);

    $resp = walletToolsCall('wallet.addresses');

    expect($resp['result']['isError'])->toBeFalse();
    $data = $resp['result']['structuredContent'];

    expect($data['count'])->toBe(2);
    $byChain = collect($data['addresses'])->keyBy('chain');
    expect($byChain['polygon']['address'])->toBe('0xabc0000000000000000000000000000000000001');
    expect($byChain['polygon']['is_active'])->toBeTrue();
    expect($byChain['solana']['address'])->toBe('So1anaAddre55Case5ensitive1111111111111111');
    expect($byChain['solana']['is_active'])->toBeFalse();
    expect($byChain['solana']['label'])->toBe('phone wallet');
    // The other user's address must not leak.
    expect(collect($data['addresses'])->pluck('address'))->not->toContain('0xdef0000000000000000000000000000000000002');
});

it('filters wallet.addresses by chain when the optional argument is given', function (): void {
    $user = User::factory()->create();
    walletToolsActAs($user);

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'polygon',
        'address'    => '0xabc0000000000000000000000000000000000011',
        'public_key' => 'pk-1',
        'is_active'  => true,
    ]);
    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'base',
        'address'    => '0xabc0000000000000000000000000000000000012',
        'public_key' => 'pk-2',
        'is_active'  => true,
    ]);

    $resp = walletToolsCall('wallet.addresses', ['chain' => 'base']);

    $data = $resp['result']['structuredContent'];
    expect($data['count'])->toBe(1);
    expect($data['addresses'][0]['chain'])->toBe('base');
});

it('returns the most recent activity items for the caller with the default limit of 10', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    walletToolsActAs($user);

    foreach (range(1, 12) as $i) {
        ActivityFeedItem::create([
            'user_id'       => $user->id,
            'activity_type' => ActivityItemType::TRANSFER_IN,
            'amount'        => '1.' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'asset'         => 'USDC',
            'network'       => 'SOLANA',
            'status'        => 'completed',
            'protected'     => false,
            'occurred_at'   => now()->subMinutes($i),
        ]);
    }
    // Another user's activity must not leak.
    ActivityFeedItem::create([
        'user_id'       => $other->id,
        'activity_type' => ActivityItemType::TRANSFER_OUT,
        'amount'        => '-9.99',
        'asset'         => 'USDC',
        'network'       => 'SOLANA',
        'status'        => 'completed',
        'protected'     => false,
        'occurred_at'   => now(),
    ]);

    $resp = walletToolsCall('wallet.activity');

    expect($resp['result']['isError'])->toBeFalse();
    $data = $resp['result']['structuredContent'];

    expect($data['count'])->toBe(10);
    expect($data['has_more'])->toBeTrue();
    // Most recent first; the other user's '-9.99' row is excluded.
    expect($data['items'][0]['amount'])->not->toBe('-9.99');
    expect($data['items'][0]['type'])->toBe('transfer_in');
});

it('clamps wallet.activity limit into the 1..50 range', function (): void {
    $user = User::factory()->create();
    walletToolsActAs($user);

    foreach (range(1, 4) as $i) {
        ActivityFeedItem::create([
            'user_id'       => $user->id,
            'activity_type' => ActivityItemType::MERCHANT_PAYMENT,
            'amount'        => '-2.00',
            'asset'         => 'USDC',
            'network'       => 'SOLANA',
            'status'        => 'completed',
            'protected'     => false,
            'occurred_at'   => now()->subMinutes($i),
        ]);
    }

    // limit far above the cap: clamped to 50, returns all 4 rows, no error.
    $resp = walletToolsCall('wallet.activity', ['limit' => 500]);
    expect($resp['result']['isError'])->toBeFalse();
    expect($resp['result']['structuredContent']['count'])->toBe(4);
    expect($resp['result']['structuredContent']['has_more'])->toBeFalse();

    // limit below the floor: clamped to 1.
    $resp = walletToolsCall('wallet.activity', ['limit' => 0]);
    expect($resp['result']['isError'])->toBeFalse();
    expect($resp['result']['structuredContent']['count'])->toBe(1);

    // explicit small limit respected, has_more flags the remainder.
    $resp = walletToolsCall('wallet.activity', ['limit' => 2]);
    expect($resp['result']['structuredContent']['count'])->toBe(2);
    expect($resp['result']['structuredContent']['has_more'])->toBeTrue();
});

it('fails in-band (isError) instead of throwing when no user is authenticated', function (): void {
    // Token claims a user id that does not exist; Auth resolves to null.
    Auth::shouldReceive('guard->user')->andReturn(null);
    Auth::shouldReceive('user')->andReturn(null);

    foreach (['wallet.addresses', 'wallet.activity'] as $name) {
        $resp = walletToolsCall($name, [], ['accounts:read'], userId: 999999);

        expect($resp['result']['isError'])->toBeTrue();
        expect($resp['result']['content'][0]['text'])->toContain('user-bound bearer token');
    }
});
