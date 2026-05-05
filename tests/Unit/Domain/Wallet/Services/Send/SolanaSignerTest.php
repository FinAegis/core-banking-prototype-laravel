<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Helpers\SolanaAddressHelper;
use App\Domain\Wallet\Services\Send\SolanaSigner;

uses(Tests\TestCase::class);

test('signMessage is deterministic for identical inputs', function (): void {
    $signer = new SolanaSigner();
    $message = 'hello solana ' . str_repeat('x', 100);

    $a = $signer->signMessage(42, 'app-key-1', $message);
    $b = $signer->signMessage(42, 'app-key-1', $message);

    expect($a)->toBe($b);
});

test('signMessage produces 64-byte ed25519 signature', function (): void {
    $signer = new SolanaSigner();

    $sig = $signer->signMessage(1, 'k', random_bytes(80));

    expect(strlen($sig))->toBe(64);
});

test('signature verifies against the user-derived public key', function (): void {
    $signer = new SolanaSigner();
    $userId = 7;
    $appKey = 'verify-test-key';
    $message = 'message-bytes-' . random_bytes(32);

    $sig = $signer->signMessage($userId, $appKey, $message);
    $pubkey = $signer->getPublicKey($userId, $appKey);

    if ($sig === '' || $pubkey === '') {
        throw new RuntimeException('Signer returned empty signature or pubkey');
    }

    expect(sodium_crypto_sign_verify_detached($sig, $message, $pubkey))->toBeTrue();
});

test('getPublicKey matches SolanaAddressHelper derivation', function (): void {
    $signer = new SolanaSigner();
    $userId = 99;
    $appKey = 'consistency-key';

    $rawPubkey = $signer->getPublicKey($userId, $appKey);
    $signerAddress = Base58::encode($rawPubkey);
    $helperAddress = SolanaAddressHelper::deriveForUser($userId, $appKey);

    expect($signerAddress)->toBe($helperAddress)
        ->and(strlen($rawPubkey))->toBe(32);
});

test('different users produce different signatures for the same message', function (): void {
    $signer = new SolanaSigner();
    $message = 'same-message';

    $sigA = $signer->signMessage(1, 'k', $message);
    $sigB = $signer->signMessage(2, 'k', $message);

    expect($sigA)->not->toBe($sigB);
});
