<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\EvmKeyHelper;
use Elliptic\EC;
use kornrunner\Keccak;

uses(Tests\TestCase::class);

test('deriveForUser is deterministic for the same userId and appKey', function (): void {
    $a = EvmKeyHelper::deriveForUser(42, 'test-app-key');
    $b = EvmKeyHelper::deriveForUser(42, 'test-app-key');

    expect($a['address'])->toBe($b['address']);
    expect($a['publicKey'])->toBe($b['publicKey']);
    expect($a['privateKey'])->toBe($b['privateKey']);
});

test('deriveForUser produces different keys for different userIds', function (): void {
    $a = EvmKeyHelper::deriveForUser(1, 'key');
    $b = EvmKeyHelper::deriveForUser(2, 'key');

    expect($a['address'])->not->toBe($b['address']);
    expect($a['privateKey'])->not->toBe($b['privateKey']);
});

test('deriveForUser produces different keys for different appKeys', function (): void {
    $a = EvmKeyHelper::deriveForUser(42, 'key-a');
    $b = EvmKeyHelper::deriveForUser(42, 'key-b');

    expect($a['address'])->not->toBe($b['address']);
});

test('deriveAddressOnly matches deriveForUser address', function (): void {
    $full = EvmKeyHelper::deriveForUser(7, 'app-key-x');
    $addressOnly = EvmKeyHelper::deriveAddressOnly(7, 'app-key-x');

    expect($addressOnly)->toBe($full['address']);
});

test('derived address is 0x-prefixed 20 bytes hex', function (): void {
    $address = EvmKeyHelper::deriveAddressOnly(123, 'k');

    expect($address)->toStartWith('0x');
    expect(strlen($address))->toBe(42);
    expect(substr($address, 2))->toMatch('/^[0-9a-fA-F]{40}$/');
});

test('derived address uses EIP-55 mixed-case checksum', function (): void {
    // Try a small range until we hit one with at least one uppercase hex letter,
    // proving the checksum is being applied (lowercase digits never round-trip
    // through toUpperCase in EIP-55, so this is a strong signal).
    $hadUppercase = false;
    foreach (range(1, 10) as $userId) {
        $address = EvmKeyHelper::deriveAddressOnly($userId, 'eip55-key');
        $hex = substr($address, 2);
        if (preg_match('/[A-F]/', $hex) === 1) {
            $hadUppercase = true;
            break;
        }
    }

    expect($hadUppercase)->toBeTrue();
});

test('public key recovers the same address as keccak256(pubkey)[12:]', function (): void {
    $derived = EvmKeyHelper::deriveForUser(99, 'recover-key');
    $publicKeyHex = substr($derived['publicKey'], 2); // strip 0x

    $hash = Keccak::hash(hex2bin($publicKeyHex), 256);
    $expectedAddress = '0x' . substr($hash, 24);

    expect(strtolower($derived['address']))->toBe(strtolower($expectedAddress));
});

test('signing with derived private key produces a valid ECDSA signature recoverable to the same public key', function (): void {
    $derived = EvmKeyHelper::deriveForUser(101, 'sign-key');
    $privKey = substr($derived['privateKey'], 2);

    $ec = new EC('secp256k1');
    $key = $ec->keyFromPrivate($privKey, 'hex');

    // Hash a known message; sign expects msg as a hex string of digest.
    $msgHashHex = Keccak::hash('hello-evm-key-helper', 256);
    $signature = $key->sign($msgHashHex, ['canonical' => true]);

    // r and s populated, recoveryParam set
    expect($signature->r)->not->toBeNull();
    expect($signature->s)->not->toBeNull();
    expect($signature->recoveryParam)->toBeIn([0, 1]);

    // Verify with the same key passes
    $verified = $key->verify($msgHashHex, $signature);
    expect($verified)->toBeTrue();

    // Recover public key from signature and confirm it matches our derived public key
    $recoveredPub = $ec->recoverPubKey($msgHashHex, $signature, $signature->recoveryParam);
    $recoveredPubHex = $recoveredPub->encode('hex', false);
    if (str_starts_with($recoveredPubHex, '04')) {
        $recoveredPubHex = substr($recoveredPubHex, 2);
    }
    $recoveredPubHex = str_pad($recoveredPubHex, 128, '0', STR_PAD_LEFT);

    expect($recoveredPubHex)->toBe(substr($derived['publicKey'], 2));
});

test('toChecksumAddress produces deterministic EIP-55 output', function (): void {
    // Vitalik's published reference: 0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359 (mixed-case)
    $expected = '0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359';
    $lower = strtolower($expected);

    expect(EvmKeyHelper::toChecksumAddress($lower))->toBe($expected);
    expect(EvmKeyHelper::toChecksumAddress($expected))->toBe($expected);
});
