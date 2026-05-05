<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Helpers\Crypto\Base58;
use InvalidArgumentException;
use SodiumException;

/**
 * Builds serialized Solana legacy transaction messages for SPL token transfers.
 *
 * Encodes a single user-signed transaction containing:
 *   1. ComputeBudget::SetComputeUnitLimit
 *   2. ComputeBudget::SetComputeUnitPrice
 *   3. (optional) AssociatedTokenAccount::Create — when the recipient ATA is missing
 *   4. SplToken::Transfer (NOT TransferChecked — keeping this minimal)
 *
 * The legacy message format is documented at
 * https://docs.solana.com/developing/programming-model/transactions and uses
 * Solana "shortvec" compact-array length encoding throughout. Account keys
 * are sorted by:
 *   1. signer + writable
 *   2. signer + readonly
 *   3. non-signer + writable
 *   4. non-signer + readonly
 * with the message header counting each class.
 *
 * Output: `buildSplTransfer()` / `buildUnsignedTransferMessage()` return the
 * UNSIGNED legacy message bytes — the bytes that get ed25519-signed by the
 * client (Privy) to produce the final transaction. The message can be
 * sandwiched with `serializeSignedTransaction()` once a signature is supplied
 * to produce the wire-format base64 transaction Helius accepts.
 */
class SolanaTransferBuilder
{
    public const TOKEN_PROGRAM_ID = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';

    public const ASSOCIATED_TOKEN_PROGRAM_ID = 'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL';

    public const SYSTEM_PROGRAM_ID = '11111111111111111111111111111111';

    public const SYSVAR_RENT_ID = 'SysvarRent111111111111111111111111111111111';

    public const COMPUTE_BUDGET_PROGRAM_ID = 'ComputeBudget111111111111111111111111111111';

    private const PDA_DOMAIN = 'ProgramDerivedAddress';

    /**
     * Build an SPL transfer message body ready to be signed.
     *
     * The returned `message` is the unsigned legacy-format Solana message — the
     * exact bytes the client must ed25519-sign. To get the wire-format
     * transaction Helius accepts, pass the signature back through
     * {@see self::serializeSignedTransaction()}.
     *
     * @return array{message: string, recipientAta: string}
     */
    public function buildSplTransfer(
        string $senderPubkeyBase58,
        string $recipientPubkeyBase58,
        string $mintBase58,
        int $amountAtomic,
        string $recentBlockhashBase58,
        bool $createRecipientAta,
    ): array {
        if ($amountAtomic <= 0) {
            throw new InvalidArgumentException('amountAtomic must be positive');
        }

        $senderRaw = $this->decodePubkey($senderPubkeyBase58, 'sender');
        $recipientRaw = $this->decodePubkey($recipientPubkeyBase58, 'recipient');
        $mintRaw = $this->decodePubkey($mintBase58, 'mint');
        $blockhashRaw = $this->decodePubkey($recentBlockhashBase58, 'blockhash');

        $tokenProgramRaw = Base58::decode(self::TOKEN_PROGRAM_ID);
        $assocProgramRaw = Base58::decode(self::ASSOCIATED_TOKEN_PROGRAM_ID);
        $systemProgramRaw = Base58::decode(self::SYSTEM_PROGRAM_ID);
        $rentSysvarRaw = Base58::decode(self::SYSVAR_RENT_ID);
        $computeBudgetRaw = Base58::decode(self::COMPUTE_BUDGET_PROGRAM_ID);

        $senderAtaRaw = $this->deriveAssociatedTokenAccount($senderRaw, $mintRaw, $tokenProgramRaw, $assocProgramRaw);
        $recipientAtaRaw = $this->deriveAssociatedTokenAccount($recipientRaw, $mintRaw, $tokenProgramRaw, $assocProgramRaw);

        // Account ordering: signer-writable, signer-readonly, nonsigner-writable, nonsigner-readonly.
        // For a single-signer transaction the only signer is the sender (writable, since fee is debited).
        $signerWritable = [$senderRaw];
        $signerReadonly = [];
        $nonSignerWritable = [$senderAtaRaw, $recipientAtaRaw];

        // Readonly non-signers — collect uniquely. Recipient pubkey is only needed when we create
        // their ATA (it's a passenger account on the create-ATA instruction). Mint same.
        $nonSignerReadonly = [];
        if ($createRecipientAta) {
            $nonSignerReadonly[] = $recipientRaw;
            $nonSignerReadonly[] = $mintRaw;
            $nonSignerReadonly[] = $systemProgramRaw;
            $nonSignerReadonly[] = $rentSysvarRaw;
            $nonSignerReadonly[] = $assocProgramRaw;
        }
        // Token program is referenced by the transfer instruction.
        $nonSignerReadonly[] = $tokenProgramRaw;
        // Compute budget program.
        $nonSignerReadonly[] = $computeBudgetRaw;

        // Deduplicate while preserving order.
        $nonSignerReadonly = $this->dedupePubkeys($nonSignerReadonly);

        $accountKeys = array_merge($signerWritable, $signerReadonly, $nonSignerWritable, $nonSignerReadonly);
        $accountIndex = $this->indexAccountKeys($accountKeys);

        // Build instructions in dispatch order.
        $instructions = [];

        $instructions[] = $this->encodeInstruction(
            $accountIndex[$computeBudgetRaw],
            [],
            $this->encodeSetComputeUnitLimit($this->computeUnitLimit()),
        );
        $instructions[] = $this->encodeInstruction(
            $accountIndex[$computeBudgetRaw],
            [],
            $this->encodeSetComputeUnitPrice($this->priorityFeeMicroLamports()),
        );

        if ($createRecipientAta) {
            // CreateAssociatedTokenAccount accounts:
            //   [funder (signer, writable), ata (writable), owner (readonly),
            //    mint (readonly), system_program, token_program, rent_sysvar]
            $createAtaAccounts = [
                $accountIndex[$senderRaw],
                $accountIndex[$recipientAtaRaw],
                $accountIndex[$recipientRaw],
                $accountIndex[$mintRaw],
                $accountIndex[$systemProgramRaw],
                $accountIndex[$tokenProgramRaw],
                $accountIndex[$rentSysvarRaw],
            ];
            $instructions[] = $this->encodeInstruction(
                $accountIndex[$assocProgramRaw],
                $createAtaAccounts,
                '', // empty data
            );
        }

        // SplToken::Transfer accounts: [source, destination, authority]
        $transferAccounts = [
            $accountIndex[$senderAtaRaw],
            $accountIndex[$recipientAtaRaw],
            $accountIndex[$senderRaw],
        ];
        $instructions[] = $this->encodeInstruction(
            $accountIndex[$tokenProgramRaw],
            $transferAccounts,
            $this->encodeSplTransferData($amountAtomic),
        );

        $numRequiredSignatures = count($signerWritable) + count($signerReadonly);
        $numReadonlySigned = count($signerReadonly);
        $numReadonlyUnsigned = count($nonSignerReadonly);

        $message = chr($numRequiredSignatures) . chr($numReadonlySigned) . chr($numReadonlyUnsigned);
        $message .= $this->encodeShortVec(count($accountKeys));
        foreach ($accountKeys as $key) {
            $message .= $key;
        }
        $message .= $blockhashRaw;
        $message .= $this->encodeShortVec(count($instructions));
        foreach ($instructions as $ix) {
            $message .= $ix;
        }

        return [
            'message'      => $message,
            'recipientAta' => Base58::encode($recipientAtaRaw),
        ];
    }

    /**
     * Convenience alias making the unsigned-bytes contract explicit in the
     * caller's vocabulary. Returns the raw legacy-format message bytes that
     * Privy (or any external signer) will ed25519-sign.
     *
     * @return array{message: string, recipientAta: string}
     */
    public function buildUnsignedTransferMessage(
        string $senderPubkeyBase58,
        string $recipientPubkeyBase58,
        string $mintBase58,
        int $amountAtomic,
        string $recentBlockhashBase58,
        bool $createRecipientAta,
    ): array {
        return $this->buildSplTransfer(
            $senderPubkeyBase58,
            $recipientPubkeyBase58,
            $mintBase58,
            $amountAtomic,
            $recentBlockhashBase58,
            $createRecipientAta,
        );
    }

    /**
     * Wrap unsigned message bytes + an externally-produced 64-byte signature
     * into the wire-format Solana legacy transaction (compact-array of
     * signatures || message). The result is the binary blob to base64-encode
     * and submit via {@see HeliusRpcClient::sendTransaction()}.
     *
     * Single-signer transactions only — for our SPL transfer flow the sender
     * is the lone signer.
     */
    public function serializeSignedTransaction(string $messageBytes, string $signatureBytes): string
    {
        if (strlen($signatureBytes) !== 64) {
            throw new InvalidArgumentException('Solana signature must be exactly 64 bytes');
        }

        // Compact-array of signatures: [count][sig1]...
        // For a single-signer tx this is shortvec(1) + 64-byte signature.
        return $this->encodeShortVec(1) . $signatureBytes . $messageBytes;
    }

    /**
     * Public PDA derivation helper. Useful for callers that need to compute the
     * recipient ATA without building a full message (e.g. balance pre-checks).
     */
    public function deriveAssociatedTokenAccountAddress(string $ownerBase58, string $mintBase58): string
    {
        return Base58::encode($this->deriveAssociatedTokenAccount(
            $this->decodePubkey($ownerBase58, 'owner'),
            $this->decodePubkey($mintBase58, 'mint'),
            Base58::decode(self::TOKEN_PROGRAM_ID),
            Base58::decode(self::ASSOCIATED_TOKEN_PROGRAM_ID),
        ));
    }

    /**
     * findProgramAddress for the Associated Token Account:
     *   seeds = [owner, token_program, mint], programId = associated_token_program
     */
    private function deriveAssociatedTokenAccount(
        string $ownerRaw,
        string $mintRaw,
        string $tokenProgramRaw,
        string $assocProgramRaw,
    ): string {
        for ($bump = 255; $bump >= 0; $bump--) {
            $seedBytes = $ownerRaw . $tokenProgramRaw . $mintRaw . chr($bump);
            $candidate = hash('sha256', $seedBytes . $assocProgramRaw . self::PDA_DOMAIN, binary: true);

            // PDAs are intentionally OFF-curve; if the candidate happens to be a valid
            // ed25519 point, decrement the bump and try again.
            if (! $this->isOnCurve25519($candidate)) {
                return $candidate;
            }
        }

        // Cryptographically negligible — included for type safety.
        throw new InvalidArgumentException('Unable to derive PDA: all bumps exhausted');
    }

    /**
     * Returns true if the 32-byte buffer encodes a point on the ed25519 curve.
     *
     * PHP's sodium binding does not expose `sodium_crypto_core_ed25519_is_valid_point`
     * (introduced in libsodium 1.0.16) on every distro build, so we use the
     * universally-available `sodium_crypto_sign_ed25519_pk_to_curve25519`, which
     * throws SodiumException on off-curve / non-canonical points.
     */
    private function isOnCurve25519(string $pubkey): bool
    {
        if (strlen($pubkey) !== 32) {
            return false;
        }

        try {
            sodium_crypto_sign_ed25519_pk_to_curve25519($pubkey);

            return true;
        } catch (SodiumException) {
            return false;
        }
    }

    private function decodePubkey(string $base58, string $label): string
    {
        $raw = Base58::decode($base58);
        if (strlen($raw) !== 32) {
            throw new InvalidArgumentException("Invalid {$label} pubkey: expected 32 bytes after Base58 decode, got " . strlen($raw));
        }

        return $raw;
    }

    /**
     * @param  array<int, string> $keys
     * @return array<string, int>
     */
    private function indexAccountKeys(array $keys): array
    {
        $index = [];
        foreach ($keys as $i => $key) {
            $index[$key] = $i;
        }

        return $index;
    }

    /**
     * @param  array<int, string> $keys
     * @return array<int, string>
     */
    private function dedupePubkeys(array $keys): array
    {
        $seen = [];
        $out = [];
        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $key;
        }

        return $out;
    }

    /**
     * Solana shortvec (compact-u16) length encoding.
     */
    private function encodeShortVec(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('shortvec value must be non-negative');
        }

        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $out .= chr($byte);
        } while ($value !== 0);

        return $out;
    }

    /**
     * @param  array<int, int> $accountIndices
     */
    private function encodeInstruction(int $programIdIndex, array $accountIndices, string $data): string
    {
        $out = chr($programIdIndex);
        $out .= $this->encodeShortVec(count($accountIndices));
        foreach ($accountIndices as $i) {
            $out .= chr($i);
        }
        $out .= $this->encodeShortVec(strlen($data));
        $out .= $data;

        return $out;
    }

    private function encodeSetComputeUnitLimit(int $units): string
    {
        // discriminator 0x02 + u32 LE
        return chr(0x02) . pack('V', $units);
    }

    private function encodeSetComputeUnitPrice(int $microLamports): string
    {
        // discriminator 0x03 + u64 LE
        return chr(0x03) . $this->packU64Le($microLamports);
    }

    private function encodeSplTransferData(int $amount): string
    {
        // discriminator 0x03 (Transfer) + u64 LE amount
        return chr(0x03) . $this->packU64Le($amount);
    }

    /**
     * pack('P', ...) is little-endian unsigned 64-bit, but emits zeros for
     * negative ints on 64-bit PHP. We only ever encode positive amounts so
     * the simpler form is fine, but we wrap it for clarity.
     */
    private function packU64Le(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('u64 value must be non-negative');
        }

        return pack('P', $value);
    }

    private function computeUnitLimit(): int
    {
        return (int) config('wallet.solana.compute_unit_limit', 200000);
    }

    private function priorityFeeMicroLamports(): int
    {
        return (int) config('wallet.solana.priority_fee_microlamports', 1000);
    }
}
