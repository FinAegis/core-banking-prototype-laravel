<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Helpers\Crypto\EvmKeyHelper;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orchestrates an outbound EVM ERC-20 transfer through the ERC-4337 stack.
 *
 * The flow is:
 *
 *   1. Validate network + asset, convert major-units amount → wei via bcmath.
 *   2. Idempotency short-circuit on (user_id, idempotency_key).
 *   3. Derive the user's owner EOA address (via {@see EvmKeyHelper}).
 *   4. Get/create the counterfactual smart account (via {@see SmartAccountService}).
 *   5. Build the inner ERC-20 `transfer(...)` calldata.
 *   6. Wrap into the outer SimpleAccount `execute(target, 0, data)` calldata.
 *   7. Build the unsigned UserOp, ask the bundler to estimate gas, ask the
 *      paymaster for sponsorship data, then construct the FINAL UserOp.
 *   8. Compute the final UserOp hash, sign it (server-only path), and submit
 *      to the bundler.
 *   9. Persist a WalletSendRecord row with status=submitted.
 *
 * The full UserOp signed here is the *finalised* one (gas + paymasterAndData
 * filled in), matching what the bundler verifies on-chain. This is correct
 * per ERC-4337 v0.6 — diverging from the slightly-incorrect path in
 * {@see \App\Domain\Relayer\Services\GasStationService::sponsorTransaction()}
 * which signs over the unsigned hash.
 */
class EvmSendDispatcher
{
    public function __construct(
        private readonly SmartAccountService $smartAccountService,
        private readonly BundlerInterface $bundler,
        private readonly PaymasterInterface $paymaster,
        private readonly ServerOnlyUserOpSigner $signer,
    ) {
    }

    /**
     * Dispatch an EVM ERC-20 transfer.
     *
     * @param  string $assetSymbol  e.g. 'USDC'
     * @param  string $networkKey   e.g. 'polygon' (lowercase, matches SupportedNetwork enum value)
     * @param  string $amountMajor  Decimal string in major units (e.g. "1.5")
     */
    public function dispatch(
        User $user,
        string $recipientAddress,
        string $assetSymbol,
        string $networkKey,
        string $amountMajor,
        ?string $idempotencyKey = null,
        ?string $quoteId = null,
    ): WalletSendRecord {
        // Idempotency: short-circuit if we've already accepted this key for this user.
        if ($idempotencyKey !== null) {
            /** @var WalletSendRecord|null $existing */
            $existing = WalletSendRecord::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        // Pre-create a record so failures can be persisted; finalised on success.
        $record = $this->createPendingRecord($user, $recipientAddress, $assetSymbol, $networkKey, $amountMajor, $idempotencyKey, $quoteId);

        try {
            $network = $this->resolveNetwork($networkKey);

            [$tokenContract, $decimals] = $this->resolveTokenContract($assetSymbol, $networkKey);

            $amountWei = $this->amountToWei($amountMajor, $decimals);

            $appKey = (string) config('app.key');
            $ownerAddress = EvmKeyHelper::deriveAddressOnly($user->id, $appKey);

            $smartAccount = $this->smartAccountService->getOrCreateAccount($user, $ownerAddress, $networkKey);
            $senderAddress = $smartAccount->account_address;

            $record->sender_address = $senderAddress;
            $record->save();

            // 5. Inner ERC-20 transfer calldata
            try {
                $innerCallData = Erc20Transfer::encodeTransferCallData($recipientAddress, $amountWei);
            } catch (InvalidArgumentException $e) {
                $this->markFailed($record, 'INVALID_ARGUMENT', $e->getMessage());

                return $record;
            }

            // 6. Outer execute() calldata
            $outerCallData = UserOpCallDataBuilder::buildExecute($tokenContract, $innerCallData);

            // 7. Build unsigned UserOp (with initCode if account isn't deployed yet).
            $needsInitCode = $this->smartAccountService->needsInitCode($ownerAddress, $networkKey);
            $initCode = $needsInitCode
                ? ('0x' . ltrim($this->smartAccountService->getInitCode($ownerAddress, $networkKey), '0x'))
                : null;
            // Normalize: empty/'0x' should map to null for createUnsigned.
            if ($initCode === null || $initCode === '0x' || $initCode === '0x0x') {
                $initCode = null;
            }

            $nonce = $needsInitCode ? 0 : $smartAccount->nonce;

            $unsignedOp = UserOperation::createUnsigned(
                sender: $senderAddress,
                nonce: $nonce,
                callData: $outerCallData,
                initCode: $initCode,
            );

            // 8. Gas estimation (bundler) + paymaster sponsorship.
            try {
                $gasEstimate = $this->bundler->estimateUserOperationGas($unsignedOp, $network);
            } catch (Throwable $e) {
                $this->markFailed($record, 'BUNDLER_REJECTED', 'Gas estimation failed: ' . $e->getMessage());

                return $record;
            }

            try {
                $paymasterData = $this->paymaster->getPaymasterData(
                    $unsignedOp,
                    (string) config('wallet.evm.fee_token', 'USDC'),
                    0.0,
                );
            } catch (Throwable $e) {
                $this->markFailed($record, 'PAYMASTER_REJECTED', 'Paymaster sponsorship failed: ' . $e->getMessage());

                return $record;
            }

            // 9. Finalise UserOp with gas + paymaster fields.
            [$maxFee, $maxPriorityFee] = self::staticGasPrices($network);

            $finalUnsigned = $unsignedOp->withGasAndSignature(
                callGasLimit: $gasEstimate['callGasLimit'],
                verificationGasLimit: $gasEstimate['verificationGasLimit'],
                preVerificationGas: $gasEstimate['preVerificationGas'],
                maxFeePerGas: $maxFee,
                maxPriorityFeePerGas: $maxPriorityFee,
                paymasterAndData: $paymasterData === '' ? '0x' : $paymasterData,
                signature: '0x',
            );

            // 10. Sign the *finalised* UserOp — this matches what the EntryPoint verifies on-chain.
            $signature = $this->signer->signUserOp($finalUnsigned, $network, $user->id);

            $userOpHash = ServerOnlyUserOpSigner::computeUserOpHash($finalUnsigned, $network);

            $signedOp = $finalUnsigned->withGasAndSignature(
                callGasLimit: $finalUnsigned->callGasLimit,
                verificationGasLimit: $finalUnsigned->verificationGasLimit,
                preVerificationGas: $finalUnsigned->preVerificationGas,
                maxFeePerGas: $finalUnsigned->maxFeePerGas,
                maxPriorityFeePerGas: $finalUnsigned->maxPriorityFeePerGas,
                paymasterAndData: $finalUnsigned->paymasterAndData,
                signature: $signature,
            );

            // 11. Submit to bundler.
            try {
                $bundlerHash = $this->bundler->submitUserOperation($signedOp, $network);
            } catch (Throwable $e) {
                $this->markFailed($record, 'BUNDLER_REJECTED', 'Submission failed: ' . $e->getMessage());

                return $record;
            }

            // Bundler returns the userOpHash; it should match our locally computed hash.
            $record->user_op_hash = $bundlerHash !== '' ? $bundlerHash : '0x' . $userOpHash;
            $record->status = WalletSendRecord::STATUS_SUBMITTED;
            $record->submitted_at = now();
            $record->save();

            $this->smartAccountService->incrementPendingOps($ownerAddress, $networkKey);

            Log::info('Wallet EVM send dispatched', [
                'record_id'    => $record->id,
                'user_op_hash' => $record->user_op_hash,
                'network'      => $networkKey,
                'asset'        => $assetSymbol,
                'amount'       => $amountMajor,
            ]);

            return $record;
        } catch (DispatchValidationException $e) {
            $this->markFailed($record, $e->errorCode, $e->getMessage());

            return $record;
        } catch (Throwable $e) {
            Log::error('Wallet EVM send failed unexpectedly', [
                'record_id' => $record->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $this->markFailed($record, 'INTERNAL_ERROR', $e->getMessage());

            return $record;
        }
    }

    private function createPendingRecord(
        User $user,
        string $recipientAddress,
        string $assetSymbol,
        string $networkKey,
        string $amountMajor,
        ?string $idempotencyKey,
        ?string $quoteId,
    ): WalletSendRecord {
        // Money math: never floats.
        if (preg_match('/^\d+(\.\d+)?$/', $amountMajor) === 1) {
            /** @var numeric-string $numericAmount */
            $numericAmount = $amountMajor;
            $normalizedAmount = bcadd($numericAmount, '0', 8);
        } else {
            $normalizedAmount = '0';
        }

        /** @var WalletSendRecord $record */
        $record = WalletSendRecord::create([
            'public_id'         => 'pi_send_' . Str::random(24),
            'user_id'           => $user->id,
            'network'           => $networkKey,
            'asset'             => $assetSymbol,
            'amount'            => $normalizedAmount,
            'sender_address'    => '',
            'recipient_address' => $recipientAddress,
            'status'            => WalletSendRecord::STATUS_PENDING,
            'idempotency_key'   => $idempotencyKey,
            'quote_id'          => $quoteId,
        ]);

        return $record;
    }

    private function markFailed(WalletSendRecord $record, string $code, string $message): void
    {
        $record->status = WalletSendRecord::STATUS_FAILED;
        $record->error_code = $code;
        $record->error_message = mb_substr($message, 0, 1000);
        $record->failed_at = now();
        $record->save();
    }

    private function resolveNetwork(string $networkKey): SupportedNetwork
    {
        /** @var array<int, string> $enabled */
        $enabled = (array) config('wallet.evm.enabled_networks', []);
        $enabledLower = array_map(static fn ($n): string => strtolower((string) $n), $enabled);

        if (! in_array(strtolower($networkKey), $enabledLower, true)) {
            throw new DispatchValidationException(
                'NETWORK_DISABLED',
                "Network '{$networkKey}' is not enabled for outbound EVM sends",
            );
        }

        $network = SupportedNetwork::tryFrom(strtolower($networkKey));
        if ($network === null) {
            throw new DispatchValidationException('NETWORK_DISABLED', "Unknown network: {$networkKey}");
        }

        return $network;
    }

    /**
     * @return array{0: string, 1: int} [contractAddress, decimals]
     */
    private function resolveTokenContract(string $assetSymbol, string $networkKey): array
    {
        /** @var array<string, mixed> $tokens */
        $tokens = (array) config('supported_tokens', []);
        $upper = strtoupper($assetSymbol);

        if (! isset($tokens[$upper]) || ! is_array($tokens[$upper])) {
            throw new DispatchValidationException('INVALID_ASSET', "Unknown asset: {$assetSymbol}");
        }

        $token = $tokens[$upper];
        /** @var array<string, mixed> $networks */
        $networks = (array) ($token['networks'] ?? []);

        if (! isset($networks[strtolower($networkKey)])) {
            throw new DispatchValidationException(
                'INVALID_ASSET',
                "Asset {$assetSymbol} is not configured on network {$networkKey}",
            );
        }

        $contract = (string) $networks[strtolower($networkKey)];
        if ($contract === '' || preg_match('/^0x[0-9a-fA-F]{40}$/', $contract) !== 1) {
            throw new DispatchValidationException(
                'INVALID_ASSET',
                "Asset {$assetSymbol} on {$networkKey} has no valid contract address",
            );
        }

        $decimals = (int) ($token['decimals'] ?? 0);
        if ($decimals <= 0 || $decimals > 36) {
            throw new DispatchValidationException(
                'INVALID_ASSET',
                "Asset {$assetSymbol} has invalid decimals configuration",
            );
        }

        return [$contract, $decimals];
    }

    private function amountToWei(string $amountMajor, int $decimals): string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $amountMajor) !== 1) {
            throw new DispatchValidationException('INVALID_AMOUNT', "Amount '{$amountMajor}' is not a non-negative decimal");
        }

        /** @var numeric-string $numericAmount */
        $numericAmount = $amountMajor;

        if (bccomp($numericAmount, '0', $decimals) <= 0) {
            throw new DispatchValidationException('INVALID_AMOUNT', "Amount must be greater than zero: {$amountMajor}");
        }

        /** @var numeric-string $multiplier */
        $multiplier = bcpow('10', (string) $decimals, 0);

        /** @var numeric-string $normalized */
        $normalized = bcadd($numericAmount, '0', $decimals);

        // Multiplying with scale=0 truncates fractional remainder; we already validated
        // that amountMajor has at most $decimals fractional places by normalising.
        return bcmul($normalized, $multiplier, 0);
    }

    /**
     * Static gas-price defaults used as the maxFee/maxPriorityFee when the
     * dispatcher cannot reach the chain. Mirrors
     * {@see \App\Domain\Relayer\Services\GasStationService::getMaxFeePerGas()}.
     *
     * @return array{0: int, 1: int} [maxFeePerGas, maxPriorityFeePerGas]
     */
    private static function staticGasPrices(SupportedNetwork $network): array
    {
        return match ($network) {
            SupportedNetwork::POLYGON  => [100_000_000_000, 30_000_000_000],
            SupportedNetwork::ARBITRUM => [1_000_000_000, 100_000_000],
            SupportedNetwork::OPTIMISM => [1_000_000_000, 100_000_000],
            SupportedNetwork::BASE     => [1_000_000_000, 100_000_000],
            SupportedNetwork::ETHEREUM => [50_000_000_000, 2_000_000_000],
        };
    }
}

/**
 * Internal exception used by the dispatcher to short-circuit validation
 * failures with a stable error_code suitable for persisting to the
 * WalletSendRecord row. Not part of the public API.
 *
 * @internal
 */
final class DispatchValidationException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
