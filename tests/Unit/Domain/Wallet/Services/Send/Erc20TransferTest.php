<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\Send\Erc20Transfer;

uses(Tests\TestCase::class);

test('selector is hardcoded to 0xa9059cbb', function (): void {
    expect(Erc20Transfer::TRANSFER_SELECTOR)->toBe('0xa9059cbb');
});

test('encodes a known reference vector for 1 USDC to a fixed address', function (): void {
    $to = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
    $amount = '1000000'; // 1 USDC at 6 decimals

    $expected = '0xa9059cbb'
        . '000000000000000000000000742d35cc6634c0532925a3b844bc454e4438f44e'
        . '00000000000000000000000000000000000000000000000000000000000f4240';

    expect(Erc20Transfer::encodeTransferCallData($to, $amount))->toBe($expected);
});

test('output length is exactly 138 chars (selector + 64 + 64 with 0x)', function (): void {
    $callData = Erc20Transfer::encodeTransferCallData('0x' . str_repeat('a', 40), '1');

    expect(strlen($callData))->toBe(138);
});

it('rejects non-numeric amount', function (): void {
    Erc20Transfer::encodeTransferCallData('0x' . str_repeat('a', 40), 'not-a-number');
})->throws(InvalidArgumentException::class);

it('rejects negative-looking amount string', function (): void {
    Erc20Transfer::encodeTransferCallData('0x' . str_repeat('a', 40), '-1');
})->throws(InvalidArgumentException::class);

it('rejects empty amount', function (): void {
    Erc20Transfer::encodeTransferCallData('0x' . str_repeat('a', 40), '');
})->throws(InvalidArgumentException::class);

it('rejects malformed address', function (): void {
    Erc20Transfer::encodeTransferCallData('0x123', '1');
})->throws(InvalidArgumentException::class);

test('lowercases the recipient address in calldata regardless of input case', function (): void {
    $upper = '0x742D35CC6634C0532925A3B844BC454E4438F44E';
    $lower = '0x742d35cc6634c0532925a3b844bc454e4438f44e';

    $cdUpper = Erc20Transfer::encodeTransferCallData($upper, '1000000');
    $cdLower = Erc20Transfer::encodeTransferCallData($lower, '1000000');

    expect($cdUpper)->toBe($cdLower);
    // Address segment lives at hex chars [10..74); strip 0x then offset 8.
    $addressSegment = substr($cdUpper, 10 + 24, 40); // pad-left zeros, then 40-char address
    expect($addressSegment)->toBe('742d35cc6634c0532925a3b844bc454e4438f44e');
});

test('encodes a large uint256 amount correctly', function (): void {
    // 1 ETH at 18 decimals = 10^18
    $amount = bcpow('10', '18', 0);
    $callData = Erc20Transfer::encodeTransferCallData('0x' . str_repeat('a', 40), $amount);

    // 10^18 in hex = 0xde0b6b3a7640000
    expect($callData)->toEndWith('0000000000000000000000000000000000000000000000000de0b6b3a7640000');
});
