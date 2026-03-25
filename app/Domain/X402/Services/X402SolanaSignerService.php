<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\X402SignerInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Solana signer for creating x402 SPL Token transfer payloads.
 *
 * In production, this delegates to an HSM or secure enclave for Ed25519 signing.
 * The demo implementation creates properly structured payloads with
 * placeholder signatures for testing the full protocol flow.
 */
class X402SolanaSignerService implements X402SignerInterface
{
    private ?string $signerAddress = null;

    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private readonly string $signerKeyId = 'default',
    ) {
    }

    /**
     * Create a Solana SPL Token transfer authorization payload.
     *
     * @param array<string, mixed> $extra Protocol-specific extensions (e.g. token program)
     * @return array<string, mixed> The payload with signature and transfer instruction
     */
    public function signTransferAuthorization(
        string $network,
        string $to,
        string $amount,
        string $asset,
        int $maxTimeoutSeconds,
        array $extra = [],
    ): array {
        $from = $this->getAddress();
        $nonce = bin2hex(random_bytes(32));
        $validBefore = (string) (time() + $maxTimeoutSeconds);

        Log::info('x402: Creating Solana SPL Token transfer authorization', [
            'from'    => $from,
            'to'      => $to,
            'amount'  => $amount,
            'network' => $network,
            'asset'   => $asset,
        ]);

        $signature = $this->sign($network, $asset, [
            'from'        => $from,
            'to'          => $to,
            'amount'      => $amount,
            'validBefore' => $validBefore,
            'nonce'       => $nonce,
        ]);

        return [
            'signature'   => $signature,
            'transaction' => [
                'from'         => $from,
                'to'           => $to,
                'amount'       => $amount,
                'mint'         => $asset,
                'validBefore'  => $validBefore,
                'nonce'        => $nonce,
                'tokenProgram' => $extra['token_program']
                    ?? (string) config('x402.solana_programs.token_program', 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'),
            ],
        ];
    }

    /**
     * Get the signer's Solana wallet address (base58 public key).
     */
    public function getAddress(): string
    {
        if ($this->signerAddress === null) {
            $this->signerAddress = (string) config(
                'x402.client.solana_signer_address',
                '11111111111111111111111111111111',
            );
        }

        return $this->signerAddress;
    }

    /**
     * Sign a Solana transaction using Ed25519.
     *
     * @param array<string, string> $message The transfer parameters
     */
    private function sign(string $network, string $mint, array $message): string
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'X402SolanaSignerService demo signer must not be used in production. '
                . 'Bind a real X402SignerInterface implementation (e.g., HSM-backed Ed25519 signer).'
            );
        }

        // In production: Ed25519 signing via HSM or KeyManagement service
        // Demo mode: return a deterministic placeholder signature
        $dataToSign = json_encode([
            'network' => $network,
            'mint'    => $mint,
            'message' => $message,
        ], JSON_THROW_ON_ERROR);

        // Ed25519 signatures are 64 bytes (128 hex chars)
        return substr(hash('sha512', $dataToSign), 0, 128);
    }
}
