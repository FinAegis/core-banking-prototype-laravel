<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Constants\SolanaTokens;
use App\Domain\Wallet\Exceptions\IdempotencyConflictException;
use App\Domain\Wallet\Exceptions\InvalidAddressException;
use App\Domain\Wallet\Exceptions\InvalidAssetException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 1 of the non-custodial Solana send flow.
 *
 * Builds the unsigned legacy-format Solana transaction message for an SPL
 * stablecoin transfer (USDC / USDT) and persists a pending {@see WalletSendRecord}.
 * Mobile receives `message_bytes_base64`, signs with Privy (ed25519), and POSTs
 * the signature to {@see SolanaSendSubmitter::submit()} which reconstructs
 * the wire-format transaction and broadcasts via Helius.
 *
 * Idempotency: if `idempotency_key` matches a previous record for this user
 * AND the request body matches, the existing record + payload (rebuilt from
 * stored metadata) is returned without re-calling Helius. A mismatch throws
 * {@see IdempotencyConflictException}.
 */
class SolanaSendPreparer
{
    /**
     * Stablecoin mint addresses on Solana mainnet, keyed by symbol.
     * Falls back to {@see SolanaTokens::KNOWN_MINTS} if available, but
     * keeps a hardcoded fallback so unit tests don't depend on the
     * constants file shape.
     */
    private const FALLBACK_MINTS = [
        'USDC' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        'USDT' => 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB',
    ];

    public function __construct(
        private readonly HeliusRpcClient $rpc,
        private readonly SolanaTransferBuilder $builder,
    ) {
    }

    /**
     * @return array{
     *   record: WalletSendRecord,
     *   payload: array{
     *     kind: string,
     *     message_bytes_base64: string,
     *     recent_blockhash: string,
     *     last_valid_block_height: int,
     *     network: string
     *   }
     * }
     */
    public function prepare(
        User $user,
        string $senderAddressBase58,
        string $recipientAddressBase58,
        string $assetSymbol,
        string $amountMajor,
        ?string $idempotencyKey,
        ?string $quoteId,
    ): array {
        $assetSymbol = strtoupper(trim($assetSymbol));

        $this->assertValidPubkey($senderAddressBase58, 'sender');
        $this->assertValidPubkey($recipientAddressBase58, 'recipient');

        $mint = $this->resolveMintForSymbol($assetSymbol);
        $decimals = $this->resolveDecimalsForSymbol($assetSymbol);

        $validatedAmount = $this->validateAmount($amountMajor);
        $atomicAmount = $this->toAtomicUnits($validatedAmount, $decimals);
        $normalizedAmount = bcadd($validatedAmount, '0', 8);

        // Idempotency lookup: same key + same body → return previous record.
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = WalletSendRecord::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof WalletSendRecord) {
                $this->assertIdempotentMatch(
                    $existing,
                    $recipientAddressBase58,
                    $assetSymbol,
                    $normalizedAmount,
                );

                return [
                    'record'  => $existing,
                    'payload' => $this->payloadFromRecord($existing),
                ];
            }
        }

        $blockhashInfo = $this->rpc->getLatestBlockhash();
        $recentBlockhash = $blockhashInfo['blockhash'];
        $lastValidBlockHeight = $blockhashInfo['lastValidBlockHeight'];

        $built = $this->builder->buildUnsignedTransferMessage(
            $senderAddressBase58,
            $recipientAddressBase58,
            $mint,
            $atomicAmount,
            $recentBlockhash,
            false, // ATA detection deferred; v1 assumes recipient ATA exists for stablecoins
        );

        $messageBytes = $built['message'];
        $messageBytesBase64 = base64_encode($messageBytes);

        $record = WalletSendRecord::create([
            'public_id'         => 'pi_send_' . Str::random(20),
            'user_id'           => $user->id,
            'network'           => 'solana',
            'asset'             => $assetSymbol,
            'amount'            => $normalizedAmount,
            'sender_address'    => $senderAddressBase58,
            'recipient_address' => $recipientAddressBase58,
            'status'            => WalletSendRecord::STATUS_PENDING,
            'idempotency_key'   => ($idempotencyKey !== null && $idempotencyKey !== '') ? $idempotencyKey : null,
            'quote_id'          => $quoteId,
            'metadata'          => [
                'message_bytes_base64'    => $messageBytesBase64,
                'recent_blockhash'        => $recentBlockhash,
                'last_valid_block_height' => $lastValidBlockHeight,
                'mint'                    => $mint,
                'atomic_amount'           => (string) $atomicAmount,
                'recipient_ata'           => $built['recipientAta'],
            ],
        ]);

        return [
            'record'  => $record,
            'payload' => [
                'kind'                    => 'solana_tx',
                'message_bytes_base64'    => $messageBytesBase64,
                'recent_blockhash'        => $recentBlockhash,
                'last_valid_block_height' => $lastValidBlockHeight,
                'network'                 => 'solana',
            ],
        ];
    }

    /**
     * @return array{
     *   kind: string,
     *   message_bytes_base64: string,
     *   recent_blockhash: string,
     *   last_valid_block_height: int,
     *   network: string
     * }
     */
    private function payloadFromRecord(WalletSendRecord $record): array
    {
        $metadata = $record->metadata ?? [];

        return [
            'kind'                    => 'solana_tx',
            'message_bytes_base64'    => (string) ($metadata['message_bytes_base64'] ?? ''),
            'recent_blockhash'        => (string) ($metadata['recent_blockhash'] ?? ''),
            'last_valid_block_height' => (int) ($metadata['last_valid_block_height'] ?? 0),
            'network'                 => 'solana',
        ];
    }

    /**
     * @param  numeric-string $normalizedAmount
     */
    private function assertIdempotentMatch(
        WalletSendRecord $existing,
        string $recipientAddressBase58,
        string $assetSymbol,
        string $normalizedAmount,
    ): void {
        // Coerce the persisted decimal back to a numeric-string before comparing.
        $existingAmount = bcadd($this->validateAmount((string) $existing->amount), '0', 8);

        $sameRecipient = $existing->recipient_address === $recipientAddressBase58;
        $sameAsset = strtoupper($existing->asset) === $assetSymbol;
        $sameAmount = bccomp($existingAmount, $normalizedAmount, 8) === 0;
        $sameNetwork = strtolower($existing->network) === 'solana';

        if (! ($sameRecipient && $sameAsset && $sameAmount && $sameNetwork)) {
            throw new IdempotencyConflictException(
                'Idempotency key reuse with mismatched body — refusing to replay.',
            );
        }
    }

    private function assertValidPubkey(string $base58, string $label): void
    {
        if ($base58 === '') {
            throw new InvalidAddressException("Solana {$label} address is empty");
        }

        try {
            $raw = Base58::decode($base58);
        } catch (InvalidArgumentException $e) {
            throw new InvalidAddressException(
                "Solana {$label} address is not valid Base58: " . $e->getMessage(),
            );
        }

        if (strlen($raw) !== 32) {
            throw new InvalidAddressException(
                "Solana {$label} address must decode to 32 bytes, got " . strlen($raw),
            );
        }
    }

    private function resolveMintForSymbol(string $symbol): string
    {
        // Prefer the central constants table when present.
        foreach (SolanaTokens::KNOWN_MINTS as $mint => $meta) {
            if ($meta['symbol'] === $symbol) {
                return $mint;
            }
        }

        if (isset(self::FALLBACK_MINTS[$symbol])) {
            return self::FALLBACK_MINTS[$symbol];
        }

        throw new InvalidAssetException(
            "Unsupported Solana send asset: {$symbol}. Supported: USDC, USDT.",
        );
    }

    private function resolveDecimalsForSymbol(string $symbol): int
    {
        foreach (SolanaTokens::KNOWN_MINTS as $meta) {
            if ($meta['symbol'] === $symbol) {
                return $meta['decimals'];
            }
        }

        // USDC and USDT are both 6 decimals on Solana mainnet.
        if (isset(self::FALLBACK_MINTS[$symbol])) {
            return 6;
        }

        throw new InvalidAssetException(
            "Unsupported Solana send asset: {$symbol}. Supported: USDC, USDT.",
        );
    }

    /**
     * Validate a major-unit decimal amount string.
     *
     * @return numeric-string
     */
    private function validateAmount(string $amountMajor): string
    {
        $trimmed = trim($amountMajor);
        if ($trimmed === '' || preg_match('/^\d+(\.\d+)?$/', $trimmed) !== 1) {
            throw new InvalidAssetException("Invalid amount: {$amountMajor}");
        }

        if (! is_numeric($trimmed)) {
            throw new InvalidAssetException("Invalid amount: {$amountMajor}");
        }

        if (bccomp($trimmed, '0', 8) <= 0) {
            throw new InvalidAssetException('Amount must be greater than zero');
        }

        return $trimmed;
    }

    /**
     * Convert a previously-validated major-unit decimal string to atomic units.
     *
     * @param  numeric-string $amountMajor
     */
    private function toAtomicUnits(string $amountMajor, int $decimals): int
    {
        $scaled = bcmul($amountMajor, bcpow('10', (string) $decimals), 0);

        // PHP_INT_MAX is well above any practical USDC/USDT atomic amount,
        // but be defensive against overflow.
        if (bccomp($scaled, (string) PHP_INT_MAX, 0) > 0) {
            throw new InvalidAssetException('Amount exceeds u64 atomic-unit range');
        }

        return (int) $scaled;
    }
}
