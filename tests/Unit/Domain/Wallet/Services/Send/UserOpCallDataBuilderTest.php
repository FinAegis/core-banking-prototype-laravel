<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\Send\Erc20Transfer;
use App\Domain\Wallet\Services\Send\UserOpCallDataBuilder;

uses(Tests\TestCase::class);

test('selector is the canonical 0xb61d27f6 for execute(address,uint256,bytes)', function (): void {
    expect(UserOpCallDataBuilder::EXECUTE_SELECTOR)->toBe('0xb61d27f6');
});

test('builds the expected layout for a USDC transfer (selector + target + 0 + offset 0x60 + length 0x44 + payload)', function (): void {
    $token = '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'; // USDC mainnet
    $inner = Erc20Transfer::encodeTransferCallData(
        '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        '1000000'
    );

    $outer = UserOpCallDataBuilder::buildExecute($token, $inner);

    // Strip 0x for layout assertions.
    expect($outer)->toStartWith('0xb61d27f6');
    $body = substr($outer, 10); // after selector

    // 1) target (32 bytes / 64 hex)
    expect(substr($body, 0, 64))->toBe(
        str_pad('a0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', 64, '0', STR_PAD_LEFT)
    );
    // 2) value = 0 (32 bytes)
    expect(substr($body, 64, 64))->toBe(str_repeat('0', 64));
    // 3) bytes-offset = 0x60 (head was 3 slots = 96 bytes)
    expect(substr($body, 128, 64))->toBe(str_pad('60', 64, '0', STR_PAD_LEFT));
    // 4) bytes length = 0x44 (68 bytes — 4 selector + 32 + 32)
    expect(substr($body, 192, 64))->toBe(str_pad('44', 64, '0', STR_PAD_LEFT));
    // 5) bytes payload — the inner calldata (without 0x), then right-padded to 32-byte boundary.
    $innerHex = substr($inner, 2);
    $expectedTail = str_pad($innerHex, (int) (ceil(strlen($innerHex) / 2 / 32) * 32 * 2), '0', STR_PAD_RIGHT);
    expect(substr($body, 256))->toBe($expectedTail);
});

test('total outer calldata length is deterministic for a given inner length', function (): void {
    $inner = Erc20Transfer::encodeTransferCallData(
        '0x' . str_repeat('a', 40),
        '1'
    );

    $outer = UserOpCallDataBuilder::buildExecute('0x' . str_repeat('b', 40), $inner);

    // 0x + selector(8) + 4 head slots * 64 + bytes-tail.
    // inner is 68 bytes (= 0x44) which fits in 96 bytes of 32-byte-aligned tail.
    // So total: 2 + 8 + 64 + 64 + 64 + 64 + 192 = 458.
    expect(strlen($outer))->toBe(458);
});

it('rejects malformed target contract address', function (): void {
    UserOpCallDataBuilder::buildExecute('0xnothex', '0xa9059cbb' . str_repeat('0', 128));
})->throws(InvalidArgumentException::class);

it('rejects inner calldata with odd hex length', function (): void {
    UserOpCallDataBuilder::buildExecute('0x' . str_repeat('a', 40), '0xabc');
})->throws(InvalidArgumentException::class);

it('rejects non-hex inner calldata', function (): void {
    UserOpCallDataBuilder::buildExecute('0x' . str_repeat('a', 40), '0xzzzz');
})->throws(InvalidArgumentException::class);
