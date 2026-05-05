<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\Base58;

uses(Tests\TestCase::class);

it('round-trips random 32-byte payloads', function (): void {
    $bytes = random_bytes(32);
    $encoded = Base58::encode($bytes);
    $decoded = Base58::decode($encoded);

    expect($decoded)->toBe($bytes)
        ->and(strlen($decoded))->toBe(32);
});

it('decodes USDC mint to 32 bytes that re-encode back to the canonical mint', function (): void {
    $usdcMint = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v';
    $raw = Base58::decode($usdcMint);

    expect(strlen($raw))->toBe(32)
        ->and(Base58::encode($raw))->toBe($usdcMint);
});

it('decodes the system program ID to 32 zero bytes', function (): void {
    // The Solana System Program is the all-zeroes pubkey, encoded as 32 '1' chars.
    $systemProgram = '11111111111111111111111111111111';
    $raw = Base58::decode($systemProgram);

    expect(strlen($raw))->toBe(32)
        ->and($raw)->toBe(str_repeat("\x00", 32))
        ->and(Base58::encode($raw))->toBe($systemProgram);
});

it('decodes the token program to 32 bytes that round-trip', function (): void {
    $tokenProgram = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';
    $raw = Base58::decode($tokenProgram);

    expect(strlen($raw))->toBe(32)
        ->and(Base58::encode($raw))->toBe($tokenProgram);
});

it('rejects invalid Base58 character', function (): void {
    $threw = false;
    try {
        // '0', 'O', 'I', 'l' are excluded from the alphabet.
        Base58::decode('0OIl');
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});

it('rejects mixed-valid-and-invalid characters', function (): void {
    $threw = false;
    try {
        Base58::decode('EPj+notvalid');
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
});

it('encodes empty bytes to empty string', function (): void {
    expect(Base58::encode(''))->toBe('');
});

it('decodes empty string to empty bytes', function (): void {
    expect(Base58::decode(''))->toBe('');
});

it('preserves leading zero bytes as 1 prefix', function (): void {
    $bytes = "\x00\x00\x01\x02";
    $encoded = Base58::encode($bytes);

    expect($encoded)->toStartWith('11')
        ->and(Base58::decode($encoded))->toBe($bytes);
});

it('round-trips 16-byte payloads', function (): void {
    $bytes = random_bytes(16);
    expect(Base58::decode(Base58::encode($bytes)))->toBe($bytes);
});

it('round-trips 20-byte payloads', function (): void {
    $bytes = random_bytes(20);
    expect(Base58::decode(Base58::encode($bytes)))->toBe($bytes);
});

it('round-trips 64-byte payloads', function (): void {
    $bytes = random_bytes(64);
    expect(Base58::decode(Base58::encode($bytes)))->toBe($bytes);
});
