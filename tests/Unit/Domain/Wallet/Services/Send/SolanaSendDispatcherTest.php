<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use App\Domain\Wallet\Services\Send\SolanaSendDispatcher;
use App\Domain\Wallet\Services\Send\SolanaSigner;
use App\Domain\Wallet\Services\Send\SolanaTransferBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'wallet.solana.rpc_url'                    => 'https://mainnet.helius-rpc.com',
        'wallet.solana.commitment'                 => 'confirmed',
        'wallet.solana.priority_fee_microlamports' => 1000,
        'wallet.solana.compute_unit_limit'         => 200000,
        'services.helius.api_key'                  => 'test-helius-key',
        'app.key'                                  => 'base64:' . base64_encode(str_repeat('a', 32)),
    ]);
});

function makeDispatcher(): SolanaSendDispatcher
{
    return new SolanaSendDispatcher(
        new HeliusRpcClient(),
        new SolanaTransferBuilder(),
        new SolanaSigner(),
    );
}

function fakeRecipient(): string
{
    $kp = sodium_crypto_sign_seed_keypair(hash('sha256', 'recipient-' . random_bytes(8), true));

    return Base58::encode(sodium_crypto_sign_publickey($kp));
}

function fakeBlockhashB58(): string
{
    return Base58::encode(random_bytes(32));
}

it('dispatches a USDC transfer and persists a submitted record with tx hash', function (): void {
    $blockhash = fakeBlockhashB58();
    $signature = '5VERv8NMvzbJMEkV8xnrLkEaWRtSz9CosKDYjCJjBRnbJLgp8uirBgmQpjKhoR4tjF3ZpRzrFmBV6UjKdiSZkQUW';

    $sequence = Http::sequence()
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['blockhash' => $blockhash, 'lastValidBlockHeight' => 12345],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => null, // recipient ATA missing → triggers create
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['err' => null, 'logs' => []],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => $signature,
        ]);
    Http::fake(['*' => $sequence]);

    $user = User::factory()->create();
    $recipient = fakeRecipient();

    $record = makeDispatcher()->dispatch($user, $recipient, 'USDC', '1.5');

    expect($record->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($record->tx_hash)->toBe($signature)
        ->and($record->network)->toBe('solana')
        ->and($record->asset)->toBe('USDC')
        ->and((float) $record->amount)->toBe(1.5)
        ->and($record->submitted_at)->not->toBeNull()
        ->and($record->error_code)->toBeNull();
});

it('returns the same record for an idempotent retry without calling RPC', function (): void {
    $user = User::factory()->create();

    $existing = WalletSendRecord::create([
        'public_id'         => 'pi_send_existing123',
        'user_id'           => $user->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.0',
        'sender_address'    => 'sender',
        'recipient_address' => 'recipient',
        'status'            => WalletSendRecord::STATUS_SUBMITTED,
        'tx_hash'           => 'existing-tx-sig',
        'idempotency_key'   => 'idem-key-1',
    ]);

    Http::fake(); // no RPC should be hit

    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', '1.0', 'idem-key-1');

    expect($record->id)->toBe($existing->id)
        ->and($record->tx_hash)->toBe('existing-tx-sig');

    Http::assertNothingSent();
});

it('rejects unknown asset symbol with INVALID_ASSET', function (): void {
    Http::fake();
    $user = User::factory()->create();

    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'NOT_A_TOKEN', '1.0');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('INVALID_ASSET');

    Http::assertNothingSent();
});

it('marks record failed when simulation rejects the transaction and never broadcasts', function (): void {
    $sequence = Http::sequence()
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['blockhash' => fakeBlockhashB58(), 'lastValidBlockHeight' => 1],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'owner'    => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
                    'lamports' => 2039280,
                    'data'     => [],
                ],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'err'  => ['InstructionError' => [2, 'InsufficientFunds']],
                    'logs' => ['Program log: insufficient funds'],
                ],
            ],
        ]);
    Http::fake(['*' => $sequence]);

    $user = User::factory()->create();

    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', '0.5');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('SIMULATION_FAILED')
        ->and($record->tx_hash)->toBeNull();

    // Confirm sendTransaction was never invoked.
    Http::assertNotSent(function ($request): bool {
        return ($request['method'] ?? null) === 'sendTransaction';
    });
});

it('captures RPC error on send and marks record failed', function (): void {
    $sequence = Http::sequence()
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['blockhash' => fakeBlockhashB58(), 'lastValidBlockHeight' => 1],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => [
                    'owner'    => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
                    'lamports' => 2039280,
                    'data'     => [],
                ],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['err' => null, 'logs' => []],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'error'   => [
                'code'    => -32000,
                'message' => 'Node is behind',
            ],
        ]);
    Http::fake(['*' => $sequence]);

    $user = User::factory()->create();
    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDT', '2.0');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('RPC_ERROR')
        ->and($record->error_message)->toContain('Node is behind')
        ->and($record->failed_at)->not->toBeNull();
});

it('rejects amount with too many fractional digits', function (): void {
    Http::fake();
    $user = User::factory()->create();

    // USDC has 6 decimals; pass 7 fractional digits.
    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', '1.0000001');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('INVALID_AMOUNT');
});

it('rejects negative or zero amount', function (): void {
    Http::fake();
    $user = User::factory()->create();

    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', '0');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('INVALID_AMOUNT');
});

it('rejects non-numeric amount', function (): void {
    Http::fake();
    $user = User::factory()->create();

    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', 'abc');

    expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($record->error_code)->toBe('INVALID_AMOUNT');
});

it('persists metadata.create_recipient_ata flag when ATA is missing', function (): void {
    $sequence = Http::sequence()
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['blockhash' => fakeBlockhashB58(), 'lastValidBlockHeight' => 1],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => null, // ATA missing
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'context' => ['slot' => 1],
                'value'   => ['err' => null, 'logs' => []],
            ],
        ])
        ->push([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => 'tx-sig',
        ]);
    Http::fake(['*' => $sequence]);

    $user = User::factory()->create();
    $record = makeDispatcher()->dispatch($user, fakeRecipient(), 'USDC', '1.0');

    expect($record->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($record->metadata)->toHaveKey('create_recipient_ata')
        ->and($record->metadata['create_recipient_ata'])->toBeTrue()
        ->and($record->metadata)->toHaveKey('recipient_ata');
});
