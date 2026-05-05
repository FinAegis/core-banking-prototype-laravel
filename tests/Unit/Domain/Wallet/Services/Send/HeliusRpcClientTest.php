<?php

declare(strict_types=1);

use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'wallet.solana.rpc_url'    => 'https://mainnet.helius-rpc.com',
        'wallet.solana.commitment' => 'confirmed',
        'services.helius.api_key'  => 'test-helius-key',
    ]);
});

const HELIUS_URL_PATTERN = 'mainnet.helius-rpc.com*';

test('getLatestBlockhash returns blockhash and last valid block height', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 12345],
                'value'   => [
                    'blockhash'            => 'BlockHashBase58Encoded123',
                    'lastValidBlockHeight' => 999,
                ],
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->getLatestBlockhash();

    expect($result['blockhash'])->toBe('BlockHashBase58Encoded123')
        ->and($result['lastValidBlockHeight'])->toBe(999);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'api-key=test-helius-key')
            && $request['method'] === 'getLatestBlockhash';
    });
});

test('simulateTransaction returns success when err is null', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'err'  => null,
                    'logs' => ['Program 11111111111111111111111111111111 invoke [1]'],
                ],
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->simulateTransaction('AABB');

    expect($result['success'])->toBeTrue()
        ->and($result['err'])->toBeNull()
        ->and($result['logs'])->toBe(['Program 11111111111111111111111111111111 invoke [1]']);
});

test('simulateTransaction returns failure when err is set', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'err'  => ['InstructionError' => [0, 'InsufficientFunds']],
                    'logs' => ['Program log: insufficient funds'],
                ],
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->simulateTransaction('AABB');

    expect($result['success'])->toBeFalse()
        ->and($result['err'])->toBeArray()
        ->and($result['logs'])->toContain('Program log: insufficient funds');
});

test('sendTransaction returns the tx signature on success', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => '5VERv8NMvzbJMEkV8xnrLkEaWRtSz9CosKDYjCJjBRnbJLgp8uirBgmQpjKhoR4tjF3ZpRzrFmBV6UjKdiSZkQUW',
        ]),
    ]);

    $client = new HeliusRpcClient();
    $sig = $client->sendTransaction('AABB');

    expect($sig)->toBe('5VERv8NMvzbJMEkV8xnrLkEaWRtSz9CosKDYjCJjBRnbJLgp8uirBgmQpjKhoR4tjF3ZpRzrFmBV6UjKdiSZkQUW');
});

test('sendTransaction throws SolanaRpcException on RPC error envelope', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'error'   => [
                'code'    => -32602,
                'message' => 'Transaction simulation failed: Blockhash not found',
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();

    try {
        $client->sendTransaction('AABB');
        $this->fail('Expected SolanaRpcException');
    } catch (SolanaRpcException $e) {
        expect($e->getRpcCode())->toBe(-32602)
            ->and($e->getMessage())->toContain('Blockhash not found');
    }
});

test('getSignatureStatuses keys responses by signature', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    [
                        'confirmationStatus' => 'confirmed',
                        'err'                => null,
                    ],
                    null,
                ],
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->getSignatureStatuses(['sigA', 'sigB']);

    expect($result['sigA'])->not->toBeNull()
        ->and($result['sigB'])->toBeNull();

    $sigAEntry = $result['sigA'];
    if ($sigAEntry === null) {
        throw new RuntimeException('Expected sigA entry to be present');
    }
    expect($sigAEntry['confirmationStatus'])->toBe('confirmed');
});

test('getAccountInfo returns null when account is missing', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => null,
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->getAccountInfo('SomeAccountAddress');

    expect($result)->toBeNull();
});

test('getAccountInfo returns owner and lamports when account exists', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'owner'      => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
                    'lamports'   => 2039280,
                    'data'       => ['parsed' => ['info' => []]],
                    'executable' => false,
                    'rentEpoch'  => 0,
                ],
            ],
        ]),
    ]);

    $client = new HeliusRpcClient();
    $result = $client->getAccountInfo('AnExistingAta');

    expect($result)->not->toBeNull();
    if ($result === null) {
        throw new RuntimeException('Expected account info to be present');
    }
    expect($result['owner'])->toBe('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA')
        ->and($result['lamports'])->toBe(2039280);
});

test('rpc client appends api key as query param not header', function (): void {
    Http::fake([
        HELIUS_URL_PATTERN => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['blockhash' => 'x', 'lastValidBlockHeight' => 1],
            ],
        ]),
    ]);

    (new HeliusRpcClient())->getLatestBlockhash();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '?api-key=test-helius-key')
            && ! $request->hasHeader('Authorization');
    });
});
