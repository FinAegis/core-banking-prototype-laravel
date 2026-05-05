<?php

declare(strict_types=1);

use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use App\Domain\Wallet\Services\Send\SolanaSendSubmitter;
use App\Domain\Wallet\Services\Send\SolanaTransferBuilder;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

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
 * Build a valid 32-byte ed25519 pubkey, Base58-encoded, from a seed.
 */
function makeSubmitterPubkey(string $seed): string
{
    $kp = sodium_crypto_sign_seed_keypair(hash('sha256', $seed, true));

    return Base58::encode(sodium_crypto_sign_publickey($kp));
}

/**
 * Build a freshly-prepared pending wallet_send_record with realistic
 * Solana message bytes embedded in metadata. Used by all submitter tests.
 *
 * @return array{record: WalletSendRecord, message: string}
 */
function makePendingRecord(?User $user = null): array
{
    $user ??= User::factory()->create();
    $sender = makeSubmitterPubkey('submitter-sender-' . Str::random(6));
    $recipient = makeSubmitterPubkey('submitter-recipient-' . Str::random(6));
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildUnsignedTransferMessage(
        $sender,
        $recipient,
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        1_500_000,
        $blockhash,
        false,
    );

    $record = WalletSendRecord::create([
        'public_id'         => 'pi_send_' . Str::random(20),
        'user_id'           => $user->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.50000000',
        'sender_address'    => $sender,
        'recipient_address' => $recipient,
        'status'            => WalletSendRecord::STATUS_PENDING,
        'metadata'          => [
            'message_bytes_base64'    => base64_encode($built['message']),
            'recent_blockhash'        => $blockhash,
            'last_valid_block_height' => 4242,
            'mint'                    => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            'atomic_amount'           => '1500000',
            'recipient_ata'           => $built['recipientAta'],
        ],
    ]);

    return ['record' => $record, 'message' => $built['message']];
}

it('flips a pending record to submitted on a successful Helius broadcast', function (): void {
    ['record' => $record] = makePendingRecord();

    $sigBytes = random_bytes(64);
    $signatureBase64 = base64_encode($sigBytes);
    $expectedSignature = 'SIG_RETURNED_BY_HELIUS_xxxxxxxxxxxxxxxxxx';

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('sendTransaction')
        ->once()
        ->with(Mockery::on(function ($base64Tx) use ($sigBytes): bool {
            $decoded = base64_decode((string) $base64Tx, true);

            // Wire format: shortvec(1)=0x01 + 64-byte signature + message.
            return is_string($decoded)
                && $decoded !== ''
                && $decoded[0] === chr(1)
                && substr($decoded, 1, 64) === $sigBytes;
        }))
        ->andReturn($expectedSignature);

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());
    $result = $submitter->submit($record, $signatureBase64);

    expect($result->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($result->tx_hash)->toBe($expectedSignature)
        ->and($result->submitted_at)->not->toBeNull()
        ->and($result->error_code)->toBeNull();
});

it('returns the record unchanged when it is already submitted', function (): void {
    ['record' => $record] = makePendingRecord();
    $record->status = WalletSendRecord::STATUS_SUBMITTED;
    $record->tx_hash = 'PRE_EXISTING_TX_HASH';
    $record->save();

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('sendTransaction');

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());
    $result = $submitter->submit($record, base64_encode(random_bytes(64)));

    expect($result->status)->toBe(WalletSendRecord::STATUS_SUBMITTED)
        ->and($result->tx_hash)->toBe('PRE_EXISTING_TX_HASH');
});

it('returns the record unchanged when it is already confirmed', function (): void {
    ['record' => $record] = makePendingRecord();
    $record->status = WalletSendRecord::STATUS_CONFIRMED;
    $record->tx_hash = 'CONFIRMED_HASH';
    $record->confirmed_at = now();
    $record->save();

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('sendTransaction');

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());
    $result = $submitter->submit($record, base64_encode(random_bytes(64)));

    expect($result->status)->toBe(WalletSendRecord::STATUS_CONFIRMED)
        ->and($result->tx_hash)->toBe('CONFIRMED_HASH');
});

it('returns the record unchanged when it is already failed', function (): void {
    ['record' => $record] = makePendingRecord();
    $record->status = WalletSendRecord::STATUS_FAILED;
    $record->error_code = 'PRIOR_FAILURE';
    $record->failed_at = now();
    $record->save();

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('sendTransaction');

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());
    $result = $submitter->submit($record, base64_encode(random_bytes(64)));

    expect($result->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($result->error_code)->toBe('PRIOR_FAILURE');
});

it('throws InvalidSignatureException when the signature is not 64 bytes', function (): void {
    ['record' => $record] = makePendingRecord();

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldNotReceive('sendTransaction');

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());

    // Only 32 bytes — too short.
    $submitter->submit($record, base64_encode(random_bytes(32)));
})->throws(InvalidSignatureException::class);

it('flips the record to failed with HELIUS_REJECTED when the RPC raises SolanaRpcException', function (): void {
    ['record' => $record] = makePendingRecord();
    $signatureBase64 = base64_encode(random_bytes(64));

    /** @var HeliusRpcClient&MockInterface $rpc */
    $rpc = Mockery::mock(HeliusRpcClient::class);
    $rpc->shouldReceive('sendTransaction')
        ->once()
        ->andThrow(SolanaRpcException::fromRpcError(-32002, 'Transaction simulation failed: insufficient funds'));

    $submitter = new SolanaSendSubmitter($rpc, new SolanaTransferBuilder());
    $result = $submitter->submit($record, $signatureBase64);

    expect($result->status)->toBe(WalletSendRecord::STATUS_FAILED)
        ->and($result->error_code)->toBe('HELIUS_REJECTED')
        ->and($result->error_message)->toContain('Transaction simulation failed')
        ->and($result->failed_at)->not->toBeNull()
        ->and($result->tx_hash)->toBeNull();
});
