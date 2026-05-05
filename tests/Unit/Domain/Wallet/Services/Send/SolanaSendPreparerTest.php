<?php

declare(strict_types=1);

use App\Domain\Wallet\Exceptions\IdempotencyConflictException;
use App\Domain\Wallet\Exceptions\InvalidAddressException;
use App\Domain\Wallet\Exceptions\InvalidAssetException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use App\Domain\Wallet\Services\Send\SolanaSendPreparer;
use App\Domain\Wallet\Services\Send\SolanaTransferBuilder;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

const SEND_USDC_MINT = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';

beforeEach(function (): void {
    config([
        'wallet.solana.compute_unit_limit'         => 200000,
        'wallet.solana.priority_fee_microlamports' => 1000,
        'wallet.solana.commitment'                 => 'confirmed',
    ]);

    Schema::dropIfExists('wallet_send_records');
    Schema::create('wallet_send_records', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('public_id', 64)->unique();
        $table->unsignedBigInteger('user_id');
        $table->string('network', 20);
        $table->string('asset', 10);
        $table->decimal('amount', 30, 8);
        $table->string('sender_address', 128);
        $table->string('recipient_address', 128);
        $table->string('status', 20)->default('pending');
        $table->string('tx_hash', 128)->nullable();
        $table->string('user_op_hash', 128)->nullable();
        $table->string('idempotency_key', 128)->nullable()->unique();
        $table->string('quote_id', 64)->nullable();
        $table->string('error_code', 50)->nullable();
        $table->text('error_message')->nullable();
        $table->json('metadata')->nullable();
        $table->dateTime('submitted_at')->nullable();
        $table->dateTime('confirmed_at')->nullable();
        $table->dateTime('failed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('wallet_send_records');
});

/**
 * Generate a valid 32-byte ed25519 pubkey, Base58-encoded.
 */
function makeSendPubkey(string $seed): string
{
    $kp = sodium_crypto_sign_seed_keypair(hash('sha256', $seed, true));

    return Base58::encode(sodium_crypto_sign_publickey($kp));
}

it('builds an unsigned message and persists a pending record on the happy path', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('preparer-sender-1');
    $recipient = makeSendPubkey('preparer-recipient-1');

    $blockhash = Base58::encode(random_bytes(32));

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getLatestBlockhash')
        ->once()
        ->andReturn(['blockhash' => $blockhash, 'lastValidBlockHeight' => 12345]);

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $result = $preparer->prepare(
        $user,
        $sender,
        $recipient,
        'USDC',
        '1.5',
        idempotencyKey: null,
        quoteId: null,
    );

    expect($result['record'])->toBeInstanceOf(WalletSendRecord::class)
        ->and($result['record']->status)->toBe(WalletSendRecord::STATUS_PENDING)
        ->and($result['record']->network)->toBe('solana')
        ->and($result['record']->asset)->toBe('USDC')
        ->and($result['record']->sender_address)->toBe($sender)
        ->and($result['record']->recipient_address)->toBe($recipient)
        ->and((string) $result['record']->amount)->toBe('1.50000000')
        ->and($result['payload']['kind'])->toBe('solana_tx')
        ->and($result['payload']['network'])->toBe('solana')
        ->and($result['payload']['recent_blockhash'])->toBe($blockhash)
        ->and($result['payload']['last_valid_block_height'])->toBe(12345)
        ->and($result['payload']['message_bytes_base64'])->not->toBe('')
        // Round-trip: decoded message must be non-empty bytes
        ->and(base64_decode($result['payload']['message_bytes_base64'], true))
            ->not->toBe(false)
            ->and(strlen((string) base64_decode($result['payload']['message_bytes_base64'], true)))
            ->toBeGreaterThan(0);

    // Metadata stored for submitter's later use
    $metadata = $result['record']->metadata ?? [];
    expect($metadata['recent_blockhash'])->toBe($blockhash)
        ->and($metadata['last_valid_block_height'])->toBe(12345)
        ->and($metadata['mint'])->toBe(SEND_USDC_MINT)
        ->and($metadata['atomic_amount'])->toBe('1500000');
});

it('returns the existing record when the same idempotency key is reused with the same body', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('idem-sender');
    $recipient = makeSendPubkey('idem-recipient');
    $blockhash = Base58::encode(random_bytes(32));

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    // First call: blockhash fetched once
    $rpc->shouldReceive('getLatestBlockhash')
        ->once()
        ->andReturn(['blockhash' => $blockhash, 'lastValidBlockHeight' => 999]);

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $first = $preparer->prepare($user, $sender, $recipient, 'USDC', '2.5', 'idem-key-1', null);
    $second = $preparer->prepare($user, $sender, $recipient, 'USDC', '2.5', 'idem-key-1', null);

    expect($second['record']->id)->toBe($first['record']->id)
        ->and($second['payload']['recent_blockhash'])->toBe($blockhash)
        ->and($second['payload']['last_valid_block_height'])->toBe(999);
});

it('throws IdempotencyConflictException when key is reused with a different recipient', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('conflict-sender');
    $recipientA = makeSendPubkey('conflict-A');
    $recipientB = makeSendPubkey('conflict-B');
    $blockhash = Base58::encode(random_bytes(32));

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getLatestBlockhash')
        ->once()
        ->andReturn(['blockhash' => $blockhash, 'lastValidBlockHeight' => 1]);

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $preparer->prepare($user, $sender, $recipientA, 'USDC', '1.0', 'dupe-key', null);

    $preparer->prepare($user, $sender, $recipientB, 'USDC', '1.0', 'dupe-key', null);
})->throws(IdempotencyConflictException::class);

it('throws IdempotencyConflictException when key is reused with a different amount', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('amount-sender');
    $recipient = makeSendPubkey('amount-recipient');
    $blockhash = Base58::encode(random_bytes(32));

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('getLatestBlockhash')->once()
        ->andReturn(['blockhash' => $blockhash, 'lastValidBlockHeight' => 1]);

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $preparer->prepare($user, $sender, $recipient, 'USDC', '1.0', 'dupe-amount', null);
    $preparer->prepare($user, $sender, $recipient, 'USDC', '2.0', 'dupe-amount', null);
})->throws(IdempotencyConflictException::class);

it('throws InvalidAssetException for an unsupported asset symbol', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('unsupported-sender');
    $recipient = makeSendPubkey('unsupported-recipient');

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    // Should never be called — we fail before fetching the blockhash.
    $rpc->shouldNotReceive('getLatestBlockhash');

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $preparer->prepare($user, $sender, $recipient, 'DOGE', '1.0', null, null);
})->throws(InvalidAssetException::class);

it('throws InvalidAddressException for a bad recipient address', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('bad-recipient-sender');

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('getLatestBlockhash');

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    // Not 32 bytes after base58 decode
    $preparer->prepare($user, $sender, 'shortbad', 'USDC', '1.0', null, null);
})->throws(InvalidAddressException::class);

it('throws InvalidAddressException for a bad sender address', function (): void {
    $user = User::factory()->create();
    $recipient = makeSendPubkey('bad-sender-recipient');

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('getLatestBlockhash');

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $preparer->prepare($user, '', $recipient, 'USDC', '1.0', null, null);
})->throws(InvalidAddressException::class);

it('throws InvalidAssetException when the amount is non-positive or malformed', function (): void {
    $user = User::factory()->create();
    $sender = makeSendPubkey('amount-malformed-sender');
    $recipient = makeSendPubkey('amount-malformed-recipient');

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('getLatestBlockhash');

    $preparer = new SolanaSendPreparer($rpc, new SolanaTransferBuilder());

    $preparer->prepare($user, $sender, $recipient, 'USDC', 'not-a-number', null, null);
})->throws(InvalidAssetException::class);
