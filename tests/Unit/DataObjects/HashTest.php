<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Domain\Account\DataObjects\Hash;

it('can create a hash with valid sha3-512 format', function () {
    // SHA3-512 produces 128 hex characters
    $hashValue = str_repeat('a', 128);
    $hash = new Hash($hashValue);
    
    expect($hash->getHash())->toBe($hashValue);
});

it('validates hash format correctly', function () {
    // Valid 128-char hex string
    $validHash = str_repeat('abc123', 21) . 'ab'; // 128 chars
    
    expect(fn() => new Hash($validHash))->not->toThrow(InvalidArgumentException::class);
});

it('throws exception for invalid hash format', function () {
    $invalidHash = 'invalid-hash-format';
    
    expect(fn() => new Hash($invalidHash))->toThrow(InvalidArgumentException::class);
});

it('throws exception for wrong length hash', function () {
    $wrongLengthHash = str_repeat('a', 64); // SHA-256 length, not SHA3-512
    
    expect(fn() => new Hash($wrongLengthHash))->toThrow(InvalidArgumentException::class);
});

it('can compare hash equality', function () {
    $hashValue = str_repeat('abc123', 21) . 'ab';
    $hash1 = new Hash($hashValue);
    $hash2 = new Hash($hashValue);
    
    expect($hash1->equals($hash2))->toBeTrue();
});

it('can convert to array', function () {
    $hashValue = str_repeat('def456', 21) . 'de';
    $hash = new Hash($hashValue);
    
    $array = $hash->toArray();
    
    expect($array)->toHaveKey('hash');
    expect($array['hash'])->toBe($hashValue);
});