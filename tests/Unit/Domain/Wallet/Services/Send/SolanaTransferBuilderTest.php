<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Helpers\SolanaAddressHelper;
use App\Domain\Wallet\Services\Send\SolanaTransferBuilder;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'wallet.solana.compute_unit_limit'         => 200000,
        'wallet.solana.priority_fee_microlamports' => 1000,
    ]);
});

const USDC_MINT = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';

/**
 * Returns 32 random ed25519-curve public-key bytes derived from a seed string.
 * Using sodium ensures the bytes are a valid on-curve point so they cannot
 * accidentally collide with our PDA derivation logic.
 */
function makePubkey(string $seed): string
{
    $kp = sodium_crypto_sign_seed_keypair(hash('sha256', $seed, true));

    return Base58::encode(sodium_crypto_sign_publickey($kp));
}

it('produces a deterministic 32-byte off-curve PDA for ATA derivation', function (): void {
    $owner = makePubkey('owner-seed-1');
    $builder = new SolanaTransferBuilder();

    $ata1 = $builder->deriveAssociatedTokenAccountAddress($owner, USDC_MINT);
    $ata2 = $builder->deriveAssociatedTokenAccountAddress($owner, USDC_MINT);

    expect($ata1)->toBe($ata2)
        ->and(strlen(Base58::decode($ata1)))->toBe(32);

    // PDAs MUST be off the ed25519 curve.
    $rawPda = Base58::decode($ata1);
    if ($rawPda === '') {
        throw new RuntimeException('PDA decoded to empty bytes');
    }
    $isOnCurve = false;
    try {
        sodium_crypto_sign_ed25519_pk_to_curve25519($rawPda);
        $isOnCurve = true;
    } catch (SodiumException) {
        // Off-curve, as expected.
    }
    expect($isOnCurve)->toBeFalse();
});

test('different owners produce different ATAs for same mint', function (): void {
    $a = makePubkey('owner-A');
    $b = makePubkey('owner-B');
    $builder = new SolanaTransferBuilder();

    $ataA = $builder->deriveAssociatedTokenAccountAddress($a, USDC_MINT);
    $ataB = $builder->deriveAssociatedTokenAccountAddress($b, USDC_MINT);

    expect($ataA)->not->toBe($ataB);
});

test('SPL transfer message contains compute-budget instructions before transfer', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(1, 'k');
    $recipient = makePubkey('recipient');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        1_000_000,
        $blockhash,
        false,
    );

    $message = $built['message'];

    // Compute-budget program ID (raw bytes) must be present in account_keys.
    expect($message)->toContain(Base58::decode(SolanaTransferBuilder::COMPUTE_BUDGET_PROGRAM_ID));

    // SetComputeUnitLimit data: 0x02 + u32 LE 200000 = 0x02 0x40 0x0d 0x03 0x00
    $unitLimitData = chr(0x02) . pack('V', 200000);
    expect($message)->toContain($unitLimitData);

    // SetComputeUnitPrice data: 0x03 + u64 LE 1000
    $unitPriceData = chr(0x03) . pack('P', 1000);
    expect($message)->toContain($unitPriceData);
});

test('SPL transfer instruction encodes 0x03 discriminator and 8-byte LE amount', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(2, 'k');
    $recipient = makePubkey('recipient-2');
    $blockhash = Base58::encode(random_bytes(32));
    $amount = 12_345_678;

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        $amount,
        $blockhash,
        false,
    );

    // Transfer discriminator + LE amount (9 bytes total) must appear in the message.
    $transferData = chr(0x03) . pack('P', $amount);

    expect($built['message'])->toContain($transferData);
});

test('without createRecipientAta the message excludes associated-token-program', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(3, 'k');
    $recipient = makePubkey('recipient-3');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        100_000,
        $blockhash,
        false,
    );

    $assocProgramRaw = Base58::decode(SolanaTransferBuilder::ASSOCIATED_TOKEN_PROGRAM_ID);
    expect($built['message'])->not->toContain($assocProgramRaw);
});

test('with createRecipientAta the message includes associated-token-program before transfer', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(4, 'k');
    $recipient = makePubkey('recipient-4');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        100_000,
        $blockhash,
        true, // create ATA
    );

    $message = $built['message'];
    $assocProgramRaw = Base58::decode(SolanaTransferBuilder::ASSOCIATED_TOKEN_PROGRAM_ID);
    expect($message)->toContain($assocProgramRaw);

    // ATA passenger accounts (system program, rent sysvar) should also be present.
    expect($message)->toContain(Base58::decode(SolanaTransferBuilder::SYSTEM_PROGRAM_ID));
    expect($message)->toContain(Base58::decode(SolanaTransferBuilder::SYSVAR_RENT_ID));
});

test('account list ordering: header has 1 required signature and 0 readonly signers', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(5, 'k');
    $recipient = makePubkey('recipient-5');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        50_000,
        $blockhash,
        false,
    );

    $message = $built['message'];
    // First three bytes are the message header.
    $numRequiredSignatures = ord($message[0]);
    $numReadonlySigned = ord($message[1]);
    $numReadonlyUnsigned = ord($message[2]);

    expect($numRequiredSignatures)->toBe(1)
        ->and($numReadonlySigned)->toBe(0)
        // No-create branch: token program + compute-budget program = 2 readonly non-signers.
        ->and($numReadonlyUnsigned)->toBe(2);
});

test('account list ordering: with createATA, readonly-unsigned count grows', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(6, 'k');
    $recipient = makePubkey('recipient-6');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        50_000,
        $blockhash,
        true,
    );

    $numReadonlyUnsigned = ord($built['message'][2]);
    // With createATA we additionally need: recipient pubkey, mint, system_program, rent, assoc_token_program
    // → 5 + previous 2 (token + compute) = 7.
    expect($numReadonlyUnsigned)->toBe(7);
});

test('first account key in the message is the signer (sender pubkey)', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(7, 'k');
    $recipient = makePubkey('recipient-7');
    $blockhash = Base58::encode(random_bytes(32));

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        1,
        $blockhash,
        false,
    );

    $message = $built['message'];
    // header (3) + shortvec(account_key_count) — for our small lists shortvec is 1 byte.
    $offset = 4;
    $firstKey = substr($message, $offset, 32);

    expect($firstKey)->toBe(Base58::decode($sender));
});

it('rejects non-32-byte pubkey', function (): void {
    $threw = false;
    try {
        (new SolanaTransferBuilder())->buildSplTransfer(
            'shortbad', // not a 32-byte Base58 pubkey
            makePubkey('r'),
            USDC_MINT,
            1,
            Base58::encode(random_bytes(32)),
            false,
        );
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});

it('rejects zero or negative amount', function (): void {
    $threw = false;
    try {
        (new SolanaTransferBuilder())->buildSplTransfer(
            makePubkey('s'),
            makePubkey('r'),
            USDC_MINT,
            0,
            Base58::encode(random_bytes(32)),
            false,
        );
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});

test('blockhash is embedded verbatim in the message after account keys', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(8, 'k');
    $recipient = makePubkey('recipient-8');
    $blockhashBytes = random_bytes(32);
    $blockhash = Base58::encode($blockhashBytes);

    $built = (new SolanaTransferBuilder())->buildSplTransfer(
        $sender,
        $recipient,
        USDC_MINT,
        1,
        $blockhash,
        false,
    );

    expect($built['message'])->toContain($blockhashBytes);
});

test('buildUnsignedTransferMessage returns the same unsigned bytes as buildSplTransfer', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(9, 'k');
    $recipient = makePubkey('recipient-9');
    $blockhash = Base58::encode(random_bytes(32));

    $a = (new SolanaTransferBuilder())->buildSplTransfer($sender, $recipient, USDC_MINT, 7777, $blockhash, false);
    $b = (new SolanaTransferBuilder())->buildUnsignedTransferMessage($sender, $recipient, USDC_MINT, 7777, $blockhash, false);

    expect($a)->toBe($b);
});

test('serializeSignedTransaction prepends shortvec(1) + 64-byte signature to the message', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(10, 'k');
    $recipient = makePubkey('recipient-10');
    $blockhash = Base58::encode(random_bytes(32));

    $builder = new SolanaTransferBuilder();
    $built = $builder->buildSplTransfer($sender, $recipient, USDC_MINT, 100, $blockhash, false);
    $signature = random_bytes(64);

    $wire = $builder->serializeSignedTransaction($built['message'], $signature);

    // shortvec(1) for single signer is a single 0x01 byte
    expect($wire[0])->toBe(chr(1))
        // Signature follows the count byte
        ->and(substr($wire, 1, 64))->toBe($signature)
        // Then the message
        ->and(substr($wire, 65))->toBe($built['message']);
});

it('rejects a signature that is not 64 bytes', function (): void {
    $sender = SolanaAddressHelper::deriveForUser(11, 'k');
    $recipient = makePubkey('recipient-11');
    $blockhash = Base58::encode(random_bytes(32));

    $builder = new SolanaTransferBuilder();
    $built = $builder->buildSplTransfer($sender, $recipient, USDC_MINT, 1, $blockhash, false);

    $builder->serializeSignedTransaction($built['message'], random_bytes(63));
})->throws(InvalidArgumentException::class);
