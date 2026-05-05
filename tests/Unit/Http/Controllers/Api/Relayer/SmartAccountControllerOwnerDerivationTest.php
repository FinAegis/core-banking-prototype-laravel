<?php

declare(strict_types=1);

use App\Domain\Wallet\Helpers\Crypto\EvmKeyHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
});

/**
 * Regression test: SmartAccountController::deriveOwnerAddress() now produces
 * the same address that EvmKeyHelper does — i.e. an address backed by a real
 * secp256k1 keypair, not the legacy phantom hash.
 *
 * We invoke the private method via reflection because it's the cheapest way
 * to assert the derivation while leaving the public API untouched. If this
 * test breaks because the method is renamed/removed, that's fine — but the
 * replacement must keep using EvmKeyHelper or this regression returns.
 */
test('controller derived owner address matches EvmKeyHelper output', function (): void {
    $user = User::factory()->create();

    $controller = app(App\Http\Controllers\Api\Relayer\SmartAccountController::class);
    $reflected = new ReflectionClass($controller);
    $method = $reflected->getMethod('deriveOwnerAddress');
    $method->setAccessible(true);

    $derived = (string) $method->invoke($controller, $user);
    $expected = EvmKeyHelper::deriveAddressOnly((int) $user->id, (string) config('app.key'));

    expect($derived)->toBe($expected);
});

test('controller no longer returns the legacy phantom hash', function (): void {
    $user = User::factory()->create();

    $legacy = '0x' . substr(hash('sha3-256', $user->id . ':' . config('app.key')), 24);

    $controller = app(App\Http\Controllers\Api\Relayer\SmartAccountController::class);
    $reflected = new ReflectionClass($controller);
    $method = $reflected->getMethod('deriveOwnerAddress');
    $method->setAccessible(true);

    $derived = (string) $method->invoke($controller, $user);

    expect(strtolower($derived))->not->toBe(strtolower($legacy));
});
