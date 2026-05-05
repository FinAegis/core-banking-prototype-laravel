<?php

declare(strict_types=1);

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Helpers\Crypto\EvmKeyHelper;
use App\Domain\Wallet\Services\Send\ServerOnlyUserOpSigner;
use Elliptic\EC;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
});

function makeFinalisedUserOp(): UserOperation
{
    return new UserOperation(
        sender: '0x742d35cc6634c0532925a3b844bc454e4438f44e',
        nonce: 0,
        initCode: '0x',
        callData: '0xb61d27f6' . str_repeat('0', 8 * 64),
        callGasLimit: 100000,
        verificationGasLimit: 200000,
        preVerificationGas: 50000,
        maxFeePerGas: 100_000_000_000,
        maxPriorityFeePerGas: 30_000_000_000,
        paymasterAndData: '0x',
        signature: '0x',
    );
}

test('signature is exactly 0x + 130 hex chars (65 bytes)', function (): void {
    $signer = new ServerOnlyUserOpSigner();
    $userOp = makeFinalisedUserOp();

    $sig = $signer->signUserOp($userOp, SupportedNetwork::POLYGON, 42);

    expect($sig)->toStartWith('0x');
    expect(strlen($sig))->toBe(2 + 130); // 65 bytes
});

test('v byte is 27 or 28', function (): void {
    $signer = new ServerOnlyUserOpSigner();
    $userOp = makeFinalisedUserOp();

    $sig = $signer->signUserOp($userOp, SupportedNetwork::POLYGON, 42);
    $vHex = substr($sig, -2);
    $v = (int) hexdec($vHex);

    expect($v)->toBeIn([27, 28]);
});

test('signing the same UserOp twice yields identical signatures (deterministic RFC6979)', function (): void {
    $signer = new ServerOnlyUserOpSigner();
    $userOp = makeFinalisedUserOp();

    $a = $signer->signUserOp($userOp, SupportedNetwork::POLYGON, 42);
    $b = $signer->signUserOp($userOp, SupportedNetwork::POLYGON, 42);

    expect($a)->toBe($b);
});

test('signing a different UserOp yields a different signature', function (): void {
    $signer = new ServerOnlyUserOpSigner();
    $userOpA = makeFinalisedUserOp();
    $userOpB = $userOpA->withGasAndSignature(
        callGasLimit: $userOpA->callGasLimit + 1,
        verificationGasLimit: $userOpA->verificationGasLimit,
        preVerificationGas: $userOpA->preVerificationGas,
        maxFeePerGas: $userOpA->maxFeePerGas,
        maxPriorityFeePerGas: $userOpA->maxPriorityFeePerGas,
        paymasterAndData: $userOpA->paymasterAndData,
        signature: '0x',
    );

    $sigA = $signer->signUserOp($userOpA, SupportedNetwork::POLYGON, 42);
    $sigB = $signer->signUserOp($userOpB, SupportedNetwork::POLYGON, 42);

    expect($sigA)->not->toBe($sigB);
});

test('recovered public key from signature matches the derived public key', function (): void {
    $signer = new ServerOnlyUserOpSigner();
    $userOp = makeFinalisedUserOp();
    $userId = 42;

    $sig = $signer->signUserOp($userOp, SupportedNetwork::POLYGON, $userId);
    $hashHex = ServerOnlyUserOpSigner::computeUserOpHash($userOp, SupportedNetwork::POLYGON);

    // Decode r, s, v
    $sigBody = substr($sig, 2);
    $r = substr($sigBody, 0, 64);
    $s = substr($sigBody, 64, 64);
    $v = (int) hexdec(substr($sigBody, 128, 2));
    $recoveryParam = $v - 27;

    $ec = new EC('secp256k1');
    $recoveredPub = $ec->recoverPubKey($hashHex, ['r' => $r, 's' => $s], $recoveryParam);

    $recoveredHex = $recoveredPub->encode('hex', false);
    if (str_starts_with($recoveredHex, '04')) {
        $recoveredHex = substr($recoveredHex, 2);
    }
    $recoveredHex = str_pad($recoveredHex, 128, '0', STR_PAD_LEFT);

    $expected = EvmKeyHelper::deriveForUser($userId, (string) config('app.key'));
    $expectedPubHex = substr($expected['publicKey'], 2);

    expect($recoveredHex)->toBe($expectedPubHex);
});

test('computeUserOpHash is deterministic and 64 hex chars', function (): void {
    $userOp = makeFinalisedUserOp();
    $a = ServerOnlyUserOpSigner::computeUserOpHash($userOp, SupportedNetwork::POLYGON);
    $b = ServerOnlyUserOpSigner::computeUserOpHash($userOp, SupportedNetwork::POLYGON);

    expect($a)->toBe($b);
    expect(strlen($a))->toBe(64);
    expect($a)->toMatch('/^[0-9a-f]{64}$/');
});

test('computeUserOpHash differs across networks (chainId is part of the outer hash)', function (): void {
    $userOp = makeFinalisedUserOp();

    $polygon = ServerOnlyUserOpSigner::computeUserOpHash($userOp, SupportedNetwork::POLYGON);
    $base = ServerOnlyUserOpSigner::computeUserOpHash($userOp, SupportedNetwork::BASE);

    expect($polygon)->not->toBe($base);
});
