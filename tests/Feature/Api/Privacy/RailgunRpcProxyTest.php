<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

// Non-custodial RPC proxy: the device hits a SHORT-LIVED SIGNED URL (minted by
// engine-config); the proxy injects the server-side provider key and forwards
// only whitelisted read methods. No provider key ever reaches the client.

function signedRpcUrl(string $network = 'polygon', int $u = 1): string
{
    return URL::temporarySignedRoute('api.privacy.rpc', now()->addHour(), ['network' => $network, 'u' => $u]);
}

beforeEach(function () {
    config(['privacy.railgun.engine.rpc_upstream' => [
        'polygon'  => 'https://upstream.example/SECRETKEY',
        'ethereum' => '', // not configured
    ]]);
});

it('forwards a whitelisted JSON-RPC method to the upstream and returns the result', function () {
    Http::fake(['upstream.example/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x10'], 200)]);

    $this->postJson(signedRpcUrl('polygon'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []])
        ->assertOk()
        ->assertJsonPath('result', '0x10');

    Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://upstream.example/'));
});

it('blocks a non-whitelisted method and never calls upstream', function () {
    Http::fake();

    $this->postJson(signedRpcUrl('polygon'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_accounts'])
        ->assertStatus(403);

    Http::assertNothingSent();
});

it('blocks a non-whitelisted method hidden inside a batch request', function () {
    Http::fake();

    $this->postJson(signedRpcUrl('polygon'), [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'personal_sign'],
    ])->assertStatus(403);

    Http::assertNothingSent();
});

it('rejects an unsigned URL (signed middleware)', function () {
    Http::fake();

    $this->postJson('/api/v1/privacy/rpc/polygon', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber'])
        ->assertStatus(403);

    Http::assertNothingSent();
});

it('returns 404 when the network has no upstream configured', function () {
    Http::fake();

    $this->postJson(signedRpcUrl('ethereum'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber'])
        ->assertStatus(404);

    Http::assertNothingSent();
});

it('rejects an empty JSON-RPC batch instead of forwarding it', function () {
    Http::fake();

    $this->postJson(signedRpcUrl('polygon'), [])->assertStatus(400);

    Http::assertNothingSent();
});
