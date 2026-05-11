<?php

declare(strict_types=1);

use App\Domain\Pricing\Services\QuoteSigner;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // Set a test pepper so the signer doesn't throw RuntimeException.
    config(['services.pricing.quote_pepper' => 'test-pepper-' . str_repeat('a', 32)]);
});

it('sign() returns a 64-char lowercase hex string', function (): void {
    $signer = new QuoteSigner();

    $signature = $signer->sign(
        id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        userId: '42',
        kind: 'send',
        expiresAtUnix: 1_716_000_000,
        responsePayloadHash: str_repeat('ab', 32),
    );

    expect($signature)->toHaveLength(64);
    expect($signature)->toMatch('/^[0-9a-f]{64}$/');
});

it('sign() is deterministic — same inputs produce same output', function (): void {
    $signer = new QuoteSigner();

    $args = [
        'id'                  => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        'userId'              => '99',
        'kind'                => 'subscription_initial',
        'expiresAtUnix'       => 1_716_100_000,
        'responsePayloadHash' => hash('sha256', 'test-payload'),
    ];

    $sig1 = $signer->sign(...$args);
    $sig2 = $signer->sign(...$args);

    expect($sig1)->toBe($sig2);
});

it('verify() returns true for a valid signature', function (): void {
    $signer = new QuoteSigner();

    $id = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    $userId = '7';
    $kind = 'swap';
    $ts = 1_716_200_000;
    $hash = hash('sha256', '{"test":"payload"}');

    $sig = $signer->sign($id, $userId, $kind, $ts, $hash);

    expect($signer->verify($id, $userId, $kind, $ts, $hash, $sig))->toBeTrue();
});

it('verify() returns false for a tampered signature', function (): void {
    $signer = new QuoteSigner();

    $id = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    $userId = '7';
    $kind = 'swap';
    $ts = 1_716_200_000;
    $hash = hash('sha256', '{"test":"payload"}');

    $sig = $signer->sign($id, $userId, $kind, $ts, $hash);
    $tampered = str_repeat('f', 64); // All-F signature is almost certainly wrong.

    expect($signer->verify($id, $userId, $kind, $ts, $hash, $tampered))->toBeFalse();
});

it('verify() returns false when the canonical form differs (different kind)', function (): void {
    $signer = new QuoteSigner();

    $id = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    $userId = '7';
    $ts = 1_716_200_000;
    $hash = hash('sha256', 'same-payload');

    $sig = $signer->sign($id, $userId, 'send', $ts, $hash);

    // Verify with a different kind — should fail.
    expect($signer->verify($id, $userId, 'swap', $ts, $hash, $sig))->toBeFalse();
});

it('payloadHash() returns a 64-char sha256 hex', function (): void {
    $signer = new QuoteSigner();
    $hash = $signer->payloadHash('{"quoteId":"abc"}');

    expect($hash)->toHaveLength(64);
    expect($hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('throws RuntimeException when pepper is empty', function (): void {
    config(['services.pricing.quote_pepper' => '']);

    $signer = new QuoteSigner();

    expect(fn () => $signer->sign('id', 'uid', 'send', 0, 'hash'))
        ->toThrow(RuntimeException::class, 'PRICING_QUOTE_PEPPER is not configured');
});
