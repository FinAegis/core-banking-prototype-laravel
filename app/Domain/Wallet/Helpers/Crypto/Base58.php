<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Helpers\Crypto;

use InvalidArgumentException;

/**
 * Pure-PHP Base58 codec (Bitcoin/Solana alphabet) using GMP.
 *
 * Solana addresses, transaction signatures, mints, and block hashes all use
 * the Bitcoin Base58 alphabet (no '0', 'O', 'I', 'l'). This is the canonical
 * round-trip codec for binary <-> Base58 conversions.
 *
 * Leading 0x00 bytes encode to leading '1' characters and vice-versa, so the
 * round-trip preserves byte-length (e.g. the system-program 32-byte pubkey of
 * all zeros decodes to 32 NUL bytes).
 */
final class Base58
{
    public const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Encode raw bytes to Base58.
     */
    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $alphabet = self::ALPHABET;

        // Count leading zero bytes; each maps to a '1' prefix.
        $leadingZeros = 0;
        $len = strlen($bytes);
        while ($leadingZeros < $len && $bytes[$leadingZeros] === "\x00") {
            $leadingZeros++;
        }

        $num = gmp_import($bytes);
        $encoded = '';
        $base = gmp_init(58);
        $zero = gmp_init(0);

        while (gmp_cmp($num, $zero) > 0) {
            [$num, $remainder] = gmp_div_qr($num, $base);
            $encoded = $alphabet[gmp_intval($remainder)] . $encoded;
        }

        return str_repeat('1', $leadingZeros) . $encoded;
    }

    /**
     * Decode a Base58 string to raw bytes.
     *
     * @throws InvalidArgumentException If the input contains characters outside the Base58 alphabet.
     */
    public static function decode(string $base58): string
    {
        if ($base58 === '') {
            return '';
        }

        $alphabet = self::ALPHABET;
        $len = strlen($base58);

        // Count leading '1' characters; each maps to a 0x00 prefix byte.
        $leadingOnes = 0;
        while ($leadingOnes < $len && $base58[$leadingOnes] === '1') {
            $leadingOnes++;
        }

        $num = gmp_init(0);
        $base = gmp_init(58);

        for ($i = 0; $i < $len; $i++) {
            $char = $base58[$i];
            $index = strpos($alphabet, $char);
            if ($index === false) {
                throw new InvalidArgumentException(
                    "Invalid Base58 character '{$char}' at position {$i}"
                );
            }
            $num = gmp_add(gmp_mul($num, $base), $index);
        }

        // gmp_export returns '' for zero — we still want the leading-zero prefix.
        $bytes = gmp_cmp($num, gmp_init(0)) === 0 ? '' : gmp_export($num);

        return str_repeat("\x00", $leadingOnes) . $bytes;
    }
}
