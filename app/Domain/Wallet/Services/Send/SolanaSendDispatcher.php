<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Constants\SolanaTokens;
use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Helpers\SolanaAddressHelper;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Orchestrates the Solana branch of the wallet send pipeline.
 *
 * Pipeline:
 *   1. Resolve mint + decimals from {@see SolanaTokens::KNOWN_MINTS} by symbol.
 *   2. Convert major-unit decimal string to atomic integer via bcmath.
 *   3. Honour idempotency: existing record short-circuits without re-broadcast.
 *   4. Fetch latest blockhash, derive recipient ATA, check existence.
 *   5. Build legacy transaction message, sign, splice signature, base64-encode.
 *   6. Simulate to catch insufficient-balance / rent / signer errors before broadcasting.
 *   7. Persist `WalletSendRecord(status=pending)`, broadcast, mark `submitted` with tx hash.
 *
 * Errors are persisted to the record (`error_code`, `error_message`) — the
 * dispatcher never lets PHP runtime warnings reach the API caller.
 */
class SolanaSendDispatcher
{
    public const NETWORK = 'solana';

    public function __construct(
        private readonly HeliusRpcClient $rpc,
        private readonly SolanaTransferBuilder $builder,
        private readonly SolanaSigner $signer,
    ) {
    }

    public function dispatch(
        User $user,
        string $recipientAddressBase58,
        string $assetSymbol,
        string $amountMajor,
        ?string $idempotencyKey = null,
        ?string $quoteId = null,
    ): WalletSendRecord {
        // 1. Idempotency short-circuit (best-effort: indexed unique column).
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = WalletSendRecord::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof WalletSendRecord) {
                return $existing;
            }
        }

        // 2. Resolve mint + decimals.
        $mint = $this->resolveMint($assetSymbol);
        if ($mint === null) {
            return $this->persistFailedRecord(
                $user,
                $recipientAddressBase58,
                $assetSymbol,
                $amountMajor,
                $idempotencyKey,
                $quoteId,
                'INVALID_ASSET',
                "Unsupported Solana asset: {$assetSymbol}",
            );
        }
        [$mintAddress, $decimals] = $mint;

        // 3. Convert amount → atomic.
        try {
            $amountAtomic = $this->amountToAtomic($amountMajor, $decimals);
        } catch (InvalidArgumentException $e) {
            return $this->persistFailedRecord(
                $user,
                $recipientAddressBase58,
                $assetSymbol,
                $amountMajor,
                $idempotencyKey,
                $quoteId,
                'INVALID_AMOUNT',
                $e->getMessage(),
            );
        }

        // 4. Derive sender pubkey.
        $senderAddress = SolanaAddressHelper::deriveForUser($user->id, (string) config('app.key'));

        // 5. Build, sign, simulate, broadcast — all wrapped so we never leak warnings.
        try {
            $blockhashInfo = $this->rpc->getLatestBlockhash();
            $recipientAta = $this->builder->deriveAssociatedTokenAccountAddress($recipientAddressBase58, $mintAddress);
            $accountInfo = $this->rpc->getAccountInfo($recipientAta);
            $createRecipientAta = $accountInfo === null;

            $built = $this->builder->buildSplTransfer(
                $senderAddress,
                $recipientAddressBase58,
                $mintAddress,
                $amountAtomic,
                $blockhashInfo['blockhash'],
                $createRecipientAta,
            );
            $messageBytes = $built['message'];

            $signature = $this->signer->signMessage($user->id, (string) config('app.key'), $messageBytes);
            $signedTx = $this->buildSignedTransaction([$signature], $messageBytes);
            $base64Tx = base64_encode($signedTx);

            $simulation = $this->rpc->simulateTransaction($base64Tx);
            if (! $simulation['success']) {
                return $this->persistFailedRecord(
                    $user,
                    $recipientAddressBase58,
                    $assetSymbol,
                    $amountMajor,
                    $idempotencyKey,
                    $quoteId,
                    'SIMULATION_FAILED',
                    $this->summariseSimulationFailure($simulation),
                    $senderAddress,
                    [
                        'simulation_err'          => $simulation['err'],
                        'simulation_logs'         => $simulation['logs'],
                        'create_recipient_ata'    => $createRecipientAta,
                        'recipient_ata'           => $built['recipientAta'],
                        'last_valid_block_height' => $blockhashInfo['lastValidBlockHeight'],
                    ],
                );
            }

            $record = DB::transaction(function () use (
                $user,
                $senderAddress,
                $recipientAddressBase58,
                $assetSymbol,
                $amountMajor,
                $idempotencyKey,
                $quoteId,
                $built,
                $createRecipientAta,
                $blockhashInfo,
            ): WalletSendRecord {
                return WalletSendRecord::create([
                    'public_id'         => $this->generatePublicId(),
                    'user_id'           => $user->id,
                    'network'           => self::NETWORK,
                    'asset'             => strtoupper($assetSymbol),
                    'amount'            => $amountMajor,
                    'sender_address'    => $senderAddress,
                    'recipient_address' => $recipientAddressBase58,
                    'status'            => WalletSendRecord::STATUS_PENDING,
                    'idempotency_key'   => $idempotencyKey,
                    'quote_id'          => $quoteId,
                    'metadata'          => [
                        'create_recipient_ata'    => $createRecipientAta,
                        'recipient_ata'           => $built['recipientAta'],
                        'last_valid_block_height' => $blockhashInfo['lastValidBlockHeight'],
                    ],
                ]);
            });

            try {
                $txSignature = $this->rpc->sendTransaction($base64Tx);
            } catch (SolanaRpcException $e) {
                $record->update([
                    'status'        => WalletSendRecord::STATUS_FAILED,
                    'error_code'    => 'RPC_ERROR',
                    'error_message' => $this->safeErrorMessage($e->getMessage()),
                    'failed_at'     => now(),
                ]);

                return $record->refresh();
            }

            $record->update([
                'tx_hash'      => $txSignature,
                'status'       => WalletSendRecord::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $record->refresh();
        } catch (Throwable $e) {
            // Catch-all for anything not handled above (RPC, builder, signer).
            return $this->persistFailedRecord(
                $user,
                $recipientAddressBase58,
                $assetSymbol,
                $amountMajor,
                $idempotencyKey,
                $quoteId,
                $e instanceof SolanaRpcException ? 'RPC_ERROR' : 'DISPATCH_ERROR',
                $this->safeErrorMessage($e->getMessage()),
                $senderAddress,
            );
        }
    }

    /**
     * Resolve a symbol like 'USDC' to [mintAddress, decimals]. Null if unknown.
     *
     * @return array{0: string, 1: int}|null
     */
    private function resolveMint(string $symbol): ?array
    {
        $symbolUpper = strtoupper($symbol);
        foreach (SolanaTokens::KNOWN_MINTS as $mintAddress => $info) {
            if (strtoupper($info['symbol']) === $symbolUpper) {
                return [$mintAddress, $info['decimals']];
            }
        }

        return null;
    }

    /**
     * Convert a major-unit decimal string to atomic integer using bcmath.
     *
     * Accepts forms like "1", "1.5", "0.000001". Rejects:
     *   - non-numeric input,
     *   - negatives or zero,
     *   - more fractional digits than the mint's decimals.
     */
    private function amountToAtomic(string $amountMajor, int $decimals): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(\.[0-9]+)?$/', $amountMajor) || ! is_numeric($amountMajor)) {
            throw new InvalidArgumentException("Invalid amount: '{$amountMajor}'");
        }
        // Pin the type for static analysis: we just verified the input is numeric.
        /** @var numeric-string $amountMajor */
        // Normalize to a numeric-string with `decimals` precision.
        $normalized = bcadd($amountMajor, '0', $decimals);

        if (bccomp($normalized, '0', $decimals) <= 0) {
            throw new InvalidArgumentException("Amount must be greater than zero: '{$amountMajor}'");
        }

        // Reject excess fractional precision (caller wrote more digits than the mint supports).
        $dotPos = strpos($amountMajor, '.');
        if ($dotPos !== false) {
            $fractional = substr($amountMajor, $dotPos + 1);
            if (strlen($fractional) > $decimals) {
                throw new InvalidArgumentException(
                    "Amount '{$amountMajor}' has more than {$decimals} fractional digits"
                );
            }
        }

        $multiplier = bcpow('10', (string) $decimals, 0);
        $atomicString = bcmul($normalized, $multiplier, 0);

        // PHP's int max is 9.22e18 — well above any realistic SPL transfer (USDC supply is ~1e15 atomic).
        if (bccomp($atomicString, (string) PHP_INT_MAX, 0) > 0) {
            throw new InvalidArgumentException("Amount '{$amountMajor}' exceeds maximum supported value");
        }

        return (int) $atomicString;
    }

    /**
     * Splice signatures + message bytes into the Solana wire format:
     *   shortvec(num_signatures) || signatures... || message_bytes
     *
     * @param  array<int, string> $signatures
     */
    private function buildSignedTransaction(array $signatures, string $messageBytes): string
    {
        $out = $this->encodeShortVec(count($signatures));
        foreach ($signatures as $sig) {
            if (strlen($sig) !== 64) {
                throw new InvalidArgumentException('Solana signature must be 64 bytes');
            }
            $out .= $sig;
        }

        return $out . $messageBytes;
    }

    private function encodeShortVec(int $value): string
    {
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
     * @param  array{success: bool, err: array<int|string, mixed>|null, logs: array<int, string>} $simulation
     */
    private function summariseSimulationFailure(array $simulation): string
    {
        if (is_array($simulation['err']) && $simulation['err'] !== []) {
            return 'Solana simulation rejected: ' . (string) json_encode($simulation['err']);
        }

        return 'Solana simulation rejected the transaction';
    }

    /**
     * Strip raw PHP runtime-warning text so we never bubble undefined-index /
     * undefined-variable / call-to-undefined messages back to the mobile client.
     */
    private function safeErrorMessage(string $message): string
    {
        if (preg_match('/^Undefined (variable|array key|index|property|offset)|Trying to access|Cannot access|Attempt to read|Call to undefined/i', $message) === 1) {
            return 'Internal error while dispatching Solana send';
        }

        return $message;
    }

    private function generatePublicId(): string
    {
        return 'pi_send_' . Str::lower(Str::random(20));
    }

    /**
     * @param  array<string, mixed>|null $extraMetadata
     */
    private function persistFailedRecord(
        User $user,
        string $recipientAddress,
        string $assetSymbol,
        string $amountMajor,
        ?string $idempotencyKey,
        ?string $quoteId,
        string $errorCode,
        string $errorMessage,
        ?string $senderAddress = null,
        ?array $extraMetadata = null,
    ): WalletSendRecord {
        $sender = $senderAddress ?? SolanaAddressHelper::deriveForUser($user->id, (string) config('app.key'));

        return WalletSendRecord::create([
            'public_id'         => $this->generatePublicId(),
            'user_id'           => $user->id,
            'network'           => self::NETWORK,
            'asset'             => strtoupper($assetSymbol),
            'amount'            => $amountMajor,
            'sender_address'    => $sender,
            'recipient_address' => $recipientAddress,
            'status'            => WalletSendRecord::STATUS_FAILED,
            'idempotency_key'   => $idempotencyKey,
            'quote_id'          => $quoteId,
            'error_code'        => $errorCode,
            'error_message'     => $this->safeErrorMessage($errorMessage),
            'failed_at'         => now(),
            'metadata'          => $extraMetadata,
        ]);
    }

    /**
     * Public helper: Base58-encode a derived sender pubkey for callers that need it.
     * Kept here so the dispatcher remains the single source of truth for address derivation.
     */
    public function senderAddressFor(User $user): string
    {
        $rawPubkey = $this->signer->getPublicKey($user->id, (string) config('app.key'));

        return Base58::encode($rawPubkey);
    }
}
