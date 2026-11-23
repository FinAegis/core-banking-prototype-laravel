<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use RuntimeException;

/**
 * Reads a smart account's next nonce from the ERC-4337 EntryPoint.
 *
 * The chain is the authority here: an operation with nonce N has run (or
 * another one with nonce N has) exactly when the EntryPoint's next nonce is
 * above N.
 */
class EntryPointNonces
{
    /** getNonce(address,uint192) */
    private const string GET_NONCE_SELECTOR = '0x35567e1a';

    public function __construct(
        private readonly EthRpcClient $rpc,
    ) {
    }

    /**
     * The next nonce for the account's default key (0).
     *
     * @throws RpcException when the chain cannot be read
     * @throws RuntimeException when the answer is not a nonce
     */
    public function sequence(string $sender, SupportedNetwork $network): int
    {
        $address = str_pad(substr(strtolower($sender), 2), 64, '0', STR_PAD_LEFT);

        $result = $this->rpc->ethCall($network, [
            'to'   => $network->getEntryPointAddress(),
            'data' => self::GET_NONCE_SELECTOR . $address . str_repeat('0', 64),
        ]);

        $hex = str_starts_with($result, '0x') ? substr($result, 2) : $result;
        $digits = ltrim($hex, '0');

        if ($hex === '' || ctype_xdigit($hex) === false || strlen($digits) > 15) {
            throw new RuntimeException("EntryPoint.getNonce answered '{$result}', which is not a nonce.");
        }

        return $digits === '' ? 0 : (int) hexdec($digits);
    }
}
