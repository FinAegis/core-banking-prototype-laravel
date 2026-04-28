<?php

declare(strict_types=1);

use App\Domain\MCP\Exceptions\IdempotencyKeyInFlightException;
use App\Domain\MCP\Exceptions\IdempotencyKeyReusedException;
use App\Domain\MCP\Policy\IdempotencyCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Pin the idempotency cache to the array store so the tests don't require
    // Redis to be available locally or in CI.
    config(['mcp.idempotency.cache_store' => 'array']);
    Cache::store('array')->flush();
});

it('caches first call result', function () {
    $cache = new IdempotencyCache();
    $result = $cache->remember(
        'tok_a',
        'payment.transfer',
        'idem-1',
        'args-hash-1',
        fn () => ['ok' => true, 'tx_id' => 'TX1'],
    );

    expect($result)->toBe(['ok' => true, 'tx_id' => 'TX1']);
    expect($cache->peek('tok_a', 'payment.transfer', 'idem-1'))->not->toBeNull();
});

it('returns cached result on retry with matching args without re-running', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true, 'tx_id' => 'TX1']);

    $secondRan = false;
    $second = $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', function () use (&$secondRan) {
        $secondRan = true;

        return ['ok' => true, 'tx_id' => 'TX2_should_not_run'];
    });

    expect($second)->toBe(['ok' => true, 'tx_id' => 'TX1']);
    expect($secondRan)->toBeFalse();
});

it('throws on retry with mismatched args_hash', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true]);

    expect(fn () => $cache->remember(
        'tok_a',
        'payment.transfer',
        'idem-1',
        'args-hash-DIFFERENT',
        fn () => ['ok' => true],
    ))->toThrow(IdempotencyKeyReusedException::class);
});

it('isolates cache entries by token_id', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'h', fn () => ['user' => 'a']);
    $b = $cache->remember('tok_b', 'payment.transfer', 'idem-1', 'h', fn () => ['user' => 'b']);

    expect($b)->toBe(['user' => 'b']);
});

it('isolates cache entries by tool name', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'h', fn () => ['fn' => 'transfer']);
    $other = $cache->remember('tok_a', 'sms.send', 'idem-1', 'h', fn () => ['fn' => 'sms']);

    expect($other)->toBe(['fn' => 'sms']);
});

it('throws IN_FLIGHT when a concurrent first-call holds the lock', function () {
    // Simulate a parallel request having already taken the in-flight lock by
    // pre-seeding the lock key. The TOCTOU race that this protects against:
    // request A and B both hit cache miss simultaneously; without the
    // SET-NX lock, both would execute the payment.
    Cache::store('array')->add(
        'mcp:idem:tok_a:payment.transfer:idem-conflict:lock',
        '1',
        60,
    );

    $cache = new IdempotencyCache();

    expect(fn () => $cache->remember(
        'tok_a',
        'payment.transfer',
        'idem-conflict',
        'h',
        fn () => ['should_not_run' => true],
    ))->toThrow(IdempotencyKeyInFlightException::class);
});

it('releases the lock after a successful first call', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-release', 'h', fn () => ['ok' => true]);

    // Lock should be gone — a second call with a different idempotency key
    // wouldn't conflict, but a same-key replay must hit the cached result, not
    // get IN_FLIGHT (which would be a deadlock condition).
    $second = $cache->remember(
        'tok_a',
        'payment.transfer',
        'idem-release',
        'h',
        fn () => ['this' => 'should-not-run'],
    );
    expect($second)->toBe(['ok' => true]);
});

it('releases the lock even when the callable throws', function () {
    $cache = new IdempotencyCache();

    try {
        $cache->remember('tok_a', 'payment.transfer', 'idem-throws', 'h', function () {
            throw new RuntimeException('downstream rail unavailable');
        });
    } catch (RuntimeException) {
        // expected
    }

    // The lock from the failed call must be released; otherwise a legitimate
    // retry after the failure would block for LOCK_TTL_SECONDS (60s).
    $retry = $cache->remember(
        'tok_a',
        'payment.transfer',
        'idem-throws',
        'h',
        fn () => ['ok' => true],
    );
    expect($retry)->toBe(['ok' => true]);
});
