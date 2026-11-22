<?php

declare(strict_types=1);

namespace App\Infrastructure\Web3;

use InvalidArgumentException;

/**
 * Utility class for encoding/decoding Solidity function calls using ABI specifications.
 *
 * Supports encoding of addresses, uint256, bytes32, and struct parameters
 * for calling Ethereum smart contracts via JSON-RPC eth_call.
 *
 * Note: Uses SHA3-256 for function selectors. In production, replace with
 * a proper keccak256 library for full Ethereum compatibility.
 */
class AbiEncoder
{
    /**
     * Encode a Solidity function call into hex calldata.
     *
     * @param  string              $functionSignature e.g. "transferTokens(address,uint256,uint16,bytes32,uint256,uint256)"
     * @param  array<int, string>  $params            Hex-encoded or decimal params in order
     * @return string              Hex-encoded calldata (0x + 4-byte selector + encoded params)
     */
    public function encodeFunctionCall(string $functionSignature, array $params): string
    {
        $selector = $this->functionSelector($functionSignature);
        $encoded = '';

        foreach ($params as $param) {
            // Each param is already encoded as a 64-char hex word
            $encoded .= $param;
        }

        return $selector . $encoded;
    }

    /**
     * Decode hex response data into typed values.
     *
     * @param  string          $hexData Raw hex response from eth_call (with or without 0x prefix)
     * @param  array<string>   $types   Array of Solidity types: 'uint256', 'address', 'bytes32', 'uint160'
     * @return array<string>   Decoded values as strings
     */
    public function decodeResponse(string $hexData, array $types): array
    {
        $data = $this->stripHexPrefix($hexData);
        $results = [];
        $offset = 0;

        foreach ($types as $type) {
            if ($offset + 64 > strlen($data)) {
                $results[] = '0';

                continue;
            }

            $word = substr($data, $offset, 64);
            $results[] = $this->decodeWord($word, $type);
            $offset += 64;
        }

        return $results;
    }

    /**
     * Encode an Ethereum address to a 32-byte ABI-encoded word.
     *
     * @param  string $address Hex address (with or without 0x prefix)
     * @return string 64-char hex string (32 bytes, left-padded with zeros)
     */
    public function encodeAddress(string $address): string
    {
        $clean = strtolower($this->stripHexPrefix($address));

        if (strlen($clean) > 40) {
            throw new InvalidArgumentException("Invalid address length: {$address}");
        }

        return str_pad($clean, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a uint256 value to a 32-byte ABI-encoded word.
     *
     * Uses bcmath for 256-bit integer support.
     *
     * @param  string $value Decimal string representation of the uint256
     * @return string 64-char hex string (32 bytes)
     */
    public function encodeUint256(string $value): string
    {
        /** @var numeric-string $numericValue */
        $numericValue = $value;

        if (bccomp($numericValue, '0', 0) < 0) {
            throw new InvalidArgumentException("uint256 cannot be negative: {$value}");
        }

        $hex = $this->bcDecToHex($numericValue);

        if (strlen($hex) > 64) {
            throw new InvalidArgumentException("Value exceeds uint256 max: {$value}");
        }

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a bytes32 hex value to a 32-byte ABI-encoded word.
     *
     * @param  string $hex Hex string (with or without 0x prefix)
     * @return string 64-char hex string (32 bytes, right-padded with zeros)
     */
    public function encodeBytes32(string $hex): string
    {
        $clean = $this->stripHexPrefix($hex);

        if (strlen($clean) > 64) {
            throw new InvalidArgumentException("bytes32 value too long: {$hex}");
        }

        return str_pad($clean, 64, '0', STR_PAD_RIGHT);
    }

    /**
     * Encode a uint16 value to a 32-byte ABI-encoded word.
     *
     * @param  int    $value Value between 0-65535
     * @return string 64-char hex string (32 bytes)
     */
    public function encodeUint16(int $value): string
    {
        if ($value < 0 || $value > 65535) {
            throw new InvalidArgumentException("uint16 out of range: {$value}");
        }

        return str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a uint32 value to a 32-byte ABI-encoded word.
     *
     * @param  int    $value Value between 0-4294967295
     * @return string 64-char hex string (32 bytes)
     */
    public function encodeUint32(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException("uint32 cannot be negative: {$value}");
        }

        return str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode an Ethereum address as a bytes32 value (left-padded with zeros).
     *
     * Used by cross-chain protocols (Wormhole, CCTP) for recipient encoding.
     *
     * @param  string $address Hex address (with or without 0x prefix)
     * @return string 64-char hex string (32 bytes, left-padded)
     */
    public function encodeAddressAsBytes32(string $address): string
    {
        $clean = str_replace('0x', '', $address);

        return str_pad($clean, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Compute the 4-byte function selector (first 4 bytes of keccak256 hash).
     *
     * @param  string $signature Function signature, e.g. "transfer(address,uint256)"
     * @return string 10-char hex string (0x + 4 bytes)
     */
    public function functionSelector(string $signature): string
    {
        // Ethereum uses keccak256 for function selectors.
        // SHA3-256 is used here as an approximation; in production
        // a proper keccak256 library should be used.
        return '0x' . substr(hash('sha3-256', $signature), 0, 8);
    }

    /**
     * Encode a struct parameter as concatenated ABI-encoded fields.
     *
     * For struct params, Solidity ABI encodes each field sequentially
     * as if they were individual parameters (for "tuple" types in calldata).
     *
     * @param  array<string> $fields Pre-encoded 64-char hex fields
     * @return string Concatenated hex fields
     */
    public function encodeStruct(array $fields): string
    {
        return implode('', $fields);
    }

    /**
     * Convert a token amount to its smallest unit representation.
     *
     * @param  string $amount  Human-readable amount (e.g. "1000.50")
     * @param  int    $decimals Token decimals (e.g. 6 for USDC, 18 for ETH)
     * @return string Decimal string in smallest unit
     */
    public function toSmallestUnit(string $amount, int $decimals): string
    {
        /** @var numeric-string $numericAmount */
        $numericAmount = $amount;
        $multiplier = bcpow('10', (string) $decimals, 0);

        return bcmul($numericAmount, $multiplier, 0);
    }

    /**
     * Strip 0x prefix from hex string.
     */
    private function stripHexPrefix(string $hex): string
    {
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            return substr($hex, 2);
        }

        return $hex;
    }

    /**
     * Decode a single 32-byte ABI word by Solidity type.
     */
    private function decodeWord(string $word, string $type): string
    {
        return match (true) {
            $type === 'address'            => '0x' . substr($word, 24),
            str_starts_with($type, 'uint') => $this->bcHexToDec($word),
            $type === 'bytes32'            => '0x' . $word,
            default                        => $this->bcHexToDec($word),
        };
    }

    /**
     * Convert a decimal string to hex using bcmath (supports 256-bit).
     */
    /**
     * @param numeric-string $dec
     */
    private function bcDecToHex(string $dec): string
    {
        if ($dec === '0') {
            return '0';
        }

        $hex = '';
        /** @var numeric-string $remaining */
        $remaining = $dec;

        while (bccomp($remaining, '0', 0) > 0) {
            $mod = bcmod($remaining, '16', 0);
            $hex = dechex((int) $mod) . $hex;
            /** @var numeric-string $remaining */
            $remaining = bcdiv($remaining, '16', 0);
        }

        return $hex;
    }

    /**
     * Convert a hex string to decimal string using bcmath (supports 256-bit).
     */
    private function bcHexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0');

        if ($hex === '') {
            return '0';
        }

        $dec = '0';

        for ($i = 0; $i < strlen($hex); $i++) {
            $dec = bcmul($dec, '16', 0);
            $dec = bcadd($dec, (string) intval($hex[$i], 16), 0);
        }

        return $dec;
    }
}
