<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Exceptions\IdempotencyConflictException;
use App\Domain\Wallet\Exceptions\InvalidAddressException;
use App\Domain\Wallet\Exceptions\InvalidAmountException;
use App\Domain\Wallet\Exceptions\InvalidAssetException;
use App\Domain\Wallet\Exceptions\NetworkDisabledException;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use Illuminate\Support\Str;
use kornrunner\Keccak;
use Throwable;

/**
 * Step 1 of the non-custodial EVM (ERC-4337) send flow.
 *
 * Builds an unsigned UserOperation v0.6 for an ERC-20 stablecoin transfer
 * from the user's Privy smart account, sponsors it via Pimlico's verifying
 * paymaster, computes the v0.6 `userOpHash`, and persists a pending
 * {@see WalletSendRecord}. Mobile signs the hash with Privy's smart-wallet
 * signer (which produces the WebAuthn-formatted signature blob the smart
 * account contract expects) and POSTs to {@see EvmUserOpSubmitter::submit()}.
 *
 * Idempotency: same `idempotency_key` + matching body returns the existing
 * record + payload reconstructed from stored metadata. Mismatched body throws
 * {@see IdempotencyConflictException}.
 */
class EvmUserOpPreparer
{
    /**
     * Default gas params used as a starting point before sponsorship returns
     * adjusted values. Pimlico's `pm_sponsorUserOperation` overwrites these.
     */
    private const DEFAULT_CALL_GAS_LIMIT = 100_000;

    private const DEFAULT_VERIFICATION_GAS_LIMIT = 150_000;

    private const DEFAULT_PRE_VERIFICATION_GAS = 50_000;

    private const DEFAULT_MAX_FEE_PER_GAS = 30_000_000_000;        // 30 gwei

    private const DEFAULT_MAX_PRIORITY_FEE_PER_GAS = 1_000_000_000; // 1 gwei

    public function __construct(
        private readonly BundlerInterface $bundler,
        private readonly PaymasterInterface $paymaster,
        private readonly SmartAccountService $smartAccountService,
    ) {
    }

    /**
     * Prepare an unsigned, paymaster-sponsored UserOperation v0.6.
     *
     * @return array{
     *   record: WalletSendRecord,
     *   payload: array{
     *     kind: string,
     *     user_op_hash: string,
     *     user_op: array<string, string>,
     *     entry_point: string,
     *     chain_id: int,
     *     network: string
     *   }
     * }
     */
    public function prepare(
        User $user,
        string $senderSmartAccountAddress,
        string $recipientAddress,
        string $assetSymbol,
        string $networkKey,
        string $amountMajor,
        ?string $idempotencyKey,
        ?string $quoteId,
    ): array {
        $assetSymbol = strtoupper(trim($assetSymbol));
        $networkKey = strtolower(trim($networkKey));

        $this->assertNetworkEnabled($networkKey);
        $supportedNetwork = $this->resolveSupportedNetwork($networkKey);
        $tokenContract = $this->resolveTokenContract($assetSymbol, $networkKey);

        $this->assertValidAddress($senderSmartAccountAddress, 'sender');
        $this->assertValidAddress($recipientAddress, 'recipient');

        $decimals = EvmTokens::DECIMALS[$assetSymbol];
        $validatedAmount = $this->validateAmount($amountMajor, $decimals);
        $atomicAmount = $this->toAtomicUnits($validatedAmount, $decimals);
        $normalizedAmount = bcadd($validatedAmount, '0', 8);

        // Idempotency lookup: same key + same body → return previous record + reconstructed payload.
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = WalletSendRecord::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof WalletSendRecord) {
                $this->assertIdempotentMatch(
                    $existing,
                    $recipientAddress,
                    $assetSymbol,
                    $networkKey,
                    $normalizedAmount,
                );

                return [
                    'record'  => $existing,
                    'payload' => $this->payloadFromRecord($existing),
                ];
            }
        }

        // Build the inner ERC-20 + outer execute() calldata.
        $erc20CallData = Erc20Transfer::encodeTransferCallData($recipientAddress, $atomicAmount);
        $outerCallData = UserOpCallDataBuilder::buildExecute($tokenContract, $erc20CallData);

        // Init code: query the relayer's SmartAccountService for an existing
        // record. Privy auto-deploys on first user-initiated tx via its own
        // bundler path, so when WE submit through Pimlico we expect the account
        // to already exist on-chain. We default to '0x' (no init code) and rely
        // on the existing relayer infra if it has a record. If no record is
        // found we still proceed — the bundler will reject undeployed accounts
        // with a clear error which surfaces back to the user.
        $initCode = $this->resolveInitCode($senderSmartAccountAddress, $networkKey);

        // Nonce: for v0.6 + counterfactual deployment + minimal-signer wallets,
        // a nonce of 0 is correct on the very first send. The relayer's
        // SmartAccountService tracks nonce locally for deployed accounts;
        // prefer that over an on-chain read here (which would require an
        // EntryPoint.getNonce() RPC call). If the local record is missing,
        // fall back to 0 — the bundler will return AA25 and we surface
        // PAYMASTER_REJECTED to the caller.
        $nonce = $this->resolveNonce($senderSmartAccountAddress, $networkKey);

        $unsignedOp = new UserOperation(
            sender: $senderSmartAccountAddress,
            nonce: $nonce,
            initCode: $initCode,
            callData: $outerCallData,
            callGasLimit: self::DEFAULT_CALL_GAS_LIMIT,
            verificationGasLimit: self::DEFAULT_VERIFICATION_GAS_LIMIT,
            preVerificationGas: self::DEFAULT_PRE_VERIFICATION_GAS,
            maxFeePerGas: self::DEFAULT_MAX_FEE_PER_GAS,
            maxPriorityFeePerGas: self::DEFAULT_MAX_PRIORITY_FEE_PER_GAS,
            paymasterAndData: '0x',
            signature: '0x',
        );

        // Resolve EntryPoint address from the bundler. For ERC-4337 v0.6 this
        // is constant across chains, but going through the bundler keeps us
        // in sync with the relayer's network config (some chains override
        // the default via the relayer.networks.* config keys).
        $entryPoint = $this->bundler->getEntryPointAddress($supportedNetwork);
        $chainId = EvmTokens::NETWORK_CHAIN_IDS[$networkKey];

        try {
            $sponsorship = $this->paymaster->sponsor($unsignedOp, $supportedNetwork, $entryPoint);
        } catch (Throwable $e) {
            // Persist a failed record so the caller (controller) can surface a
            // structured error response without making a second decision tree.
            $failed = WalletSendRecord::create([
                'public_id'         => 'pi_send_' . Str::random(20),
                'user_id'           => $user->id,
                'network'           => $networkKey,
                'asset'             => $assetSymbol,
                'amount'            => $normalizedAmount,
                'sender_address'    => $senderSmartAccountAddress,
                'recipient_address' => $recipientAddress,
                'status'            => WalletSendRecord::STATUS_FAILED,
                'idempotency_key'   => ($idempotencyKey !== null && $idempotencyKey !== '') ? $idempotencyKey : null,
                'quote_id'          => $quoteId,
                'error_code'        => 'PAYMASTER_REJECTED',
                'error_message'     => $e->getMessage(),
                'failed_at'         => now(),
                'metadata'          => [
                    'entry_point' => $entryPoint,
                    'chain_id'    => $chainId,
                ],
            ]);

            return [
                'record'  => $failed,
                'payload' => [
                    'kind'         => 'userop',
                    'user_op_hash' => '',
                    'user_op'      => $unsignedOp->toArray(),
                    'entry_point'  => $entryPoint,
                    'chain_id'     => $chainId,
                    'network'      => $networkKey,
                ],
            ];
        }

        $sponsoredOp = $unsignedOp->withGasAndSignature(
            callGasLimit: $sponsorship['callGasLimit'],
            verificationGasLimit: $sponsorship['verificationGasLimit'],
            preVerificationGas: $sponsorship['preVerificationGas'],
            maxFeePerGas: $sponsorship['maxFeePerGas'],
            maxPriorityFeePerGas: $sponsorship['maxPriorityFeePerGas'],
            paymasterAndData: $sponsorship['paymasterAndData'],
            signature: '0x',
        );

        $userOpHash = $this->computeV06UserOpHash($sponsoredOp, $entryPoint, $chainId);

        $userOpArray = $sponsoredOp->toArray();

        $record = WalletSendRecord::create([
            'public_id'         => 'pi_send_' . Str::random(20),
            'user_id'           => $user->id,
            'network'           => $networkKey,
            'asset'             => $assetSymbol,
            'amount'            => $normalizedAmount,
            'sender_address'    => $senderSmartAccountAddress,
            'recipient_address' => $recipientAddress,
            'status'            => WalletSendRecord::STATUS_PENDING,
            'user_op_hash'      => $userOpHash,
            'idempotency_key'   => ($idempotencyKey !== null && $idempotencyKey !== '') ? $idempotencyKey : null,
            'quote_id'          => $quoteId,
            'metadata'          => [
                'user_op'       => $userOpArray,
                'entry_point'   => $entryPoint,
                'chain_id'      => $chainId,
                'atomic_amount' => $atomicAmount,
                'token_address' => $tokenContract,
            ],
        ]);

        return [
            'record'  => $record,
            'payload' => [
                'kind'         => 'userop',
                'user_op_hash' => $userOpHash,
                'user_op'      => $userOpArray,
                'entry_point'  => $entryPoint,
                'chain_id'     => $chainId,
                'network'      => $networkKey,
            ],
        ];
    }

    /**
     * Reconstruct the prepare-payload from a previously-stored record's
     * metadata, used on idempotent re-entry.
     *
     * @return array{
     *   kind: string,
     *   user_op_hash: string,
     *   user_op: array<string, string>,
     *   entry_point: string,
     *   chain_id: int,
     *   network: string
     * }
     */
    private function payloadFromRecord(WalletSendRecord $record): array
    {
        $metadata = $record->metadata ?? [];

        /** @var array<string, string> $userOp */
        $userOp = is_array($metadata['user_op'] ?? null) ? $metadata['user_op'] : [];

        return [
            'kind'         => 'userop',
            'user_op_hash' => (string) ($record->user_op_hash ?? ''),
            'user_op'      => $userOp,
            'entry_point'  => (string) ($metadata['entry_point'] ?? EvmTokens::ENTRY_POINT_V06),
            'chain_id'     => (int) ($metadata['chain_id'] ?? 0),
            'network'      => $record->network,
        ];
    }

    /**
     * @param  numeric-string  $normalizedAmount
     */
    private function assertIdempotentMatch(
        WalletSendRecord $existing,
        string $recipientAddress,
        string $assetSymbol,
        string $networkKey,
        string $normalizedAmount,
    ): void {
        $sameRecipient = strtolower((string) $existing->recipient_address) === strtolower($recipientAddress);
        $sameAsset = strtoupper((string) $existing->asset) === $assetSymbol;
        $sameNetwork = strtolower((string) $existing->network) === $networkKey;

        // Existing amount column is a decimal string from MySQL; treat as
        // numeric-string so bccomp accepts it.
        /** @var numeric-string $existingAmount */
        $existingAmount = (string) $existing->amount;
        $sameAmount = bccomp($existingAmount, $normalizedAmount, 8) === 0;

        if (! ($sameRecipient && $sameAsset && $sameNetwork && $sameAmount)) {
            throw new IdempotencyConflictException(
                'Idempotency key reuse with mismatched body — refusing to replay.',
            );
        }
    }

    private function assertNetworkEnabled(string $networkKey): void
    {
        /** @var array<int, string> $enabled */
        $enabled = (array) config('wallet.evm.enabled_networks', []);
        $enabled = array_values(array_map('strtolower', array_map('strval', $enabled)));

        if (! in_array($networkKey, $enabled, true)) {
            throw new NetworkDisabledException(
                "EVM network '{$networkKey}' is not enabled for outbound send.",
            );
        }

        if (! isset(EvmTokens::NETWORK_CHAIN_IDS[$networkKey])) {
            throw new NetworkDisabledException(
                "EVM network '{$networkKey}' has no chain id mapping.",
            );
        }
    }

    private function resolveSupportedNetwork(string $networkKey): SupportedNetwork
    {
        $network = SupportedNetwork::tryFrom($networkKey);

        if ($network === null) {
            throw new NetworkDisabledException(
                "EVM network '{$networkKey}' is not a SupportedNetwork enum case.",
            );
        }

        return $network;
    }

    private function resolveTokenContract(string $assetSymbol, string $networkKey): string
    {
        if (! isset(EvmTokens::DECIMALS[$assetSymbol])) {
            throw new InvalidAssetException(
                "Unsupported EVM send asset: {$assetSymbol}. Supported: USDC, USDT.",
            );
        }

        $contract = EvmTokens::contractFor($assetSymbol, $networkKey);

        if ($contract === null) {
            throw new InvalidAssetException(
                "Asset {$assetSymbol} is not deployed on EVM network '{$networkKey}'.",
            );
        }

        return $contract;
    }

    private function assertValidAddress(string $address, string $label): void
    {
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $address) !== 1) {
            throw new InvalidAddressException(
                "EVM {$label} address must match 0x-prefixed 20-byte hex; got '{$address}'.",
            );
        }
    }

    /**
     * Validate the major-unit amount string and return it normalized as a
     * numeric-string suitable for bcmath. Rejects non-numeric, negative,
     * empty, and excess-precision inputs (e.g. '1.5000001' on USDC).
     *
     * @return numeric-string
     */
    private function validateAmount(string $amountMajor, int $decimals): string
    {
        $trimmed = trim($amountMajor);

        if ($trimmed === '' || preg_match('/^\d+(\.\d+)?$/', $trimmed) !== 1) {
            throw new InvalidAmountException("Invalid amount: '{$amountMajor}'");
        }

        // Reject more decimal places than the asset supports — bcmul would
        // silently truncate, which we don't want for money.
        if (str_contains($trimmed, '.')) {
            $fractional = substr($trimmed, strpos($trimmed, '.') + 1);
            if (strlen($fractional) > $decimals) {
                throw new InvalidAmountException(
                    "Amount '{$amountMajor}' has more decimal places than the asset supports ({$decimals}).",
                );
            }
        }

        if (! is_numeric($trimmed)) {
            throw new InvalidAmountException("Invalid amount: '{$amountMajor}'");
        }

        return $trimmed;
    }

    /**
     * Convert a validated major-unit decimal amount to atomic units (uint256
     * decimal string) for the asset's decimals. Caller MUST validate via
     * {@see validateAmount()} first.
     *
     * @param  numeric-string  $amountMajor
     * @return numeric-string
     */
    private function toAtomicUnits(string $amountMajor, int $decimals): string
    {
        /** @var numeric-string $multiplier */
        $multiplier = bcpow('10', (string) $decimals);
        $scaled = bcmul($amountMajor, $multiplier, 0);

        if (str_contains($scaled, '.')) {
            // Defensive — bcmul with scale=0 must not produce decimals.
            throw new InvalidAmountException("Amount '{$amountMajor}' did not normalize to an integer.");
        }

        if (bccomp($scaled, '0', 0) <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        return $scaled;
    }

    /**
     * Resolve the smart-account init code for a (sender, network) pair.
     *
     * For Privy-managed accounts, deployment is handled out-of-band on the
     * Privy bundler the first time the user moves funds. When OUR pipeline
     * submits via Pimlico we therefore expect the account to be deployed
     * already; we default to '0x' (no init code). If the relayer's
     * SmartAccountService has a local record showing the account is NOT yet
     * deployed AND a factory init code is available, we attach it so the
     * bundler can deploy on-the-fly.
     */
    private function resolveInitCode(string $sender, string $networkKey): string
    {
        try {
            $account = $this->smartAccountService->getByAccountAddress($sender, $networkKey);
        } catch (Throwable) {
            return '0x';
        }

        if ($account === null) {
            // No local record — assume Privy already deployed. Bundler will
            // reject if the assumption is wrong.
            return '0x';
        }

        if ($account->deployed) {
            return '0x';
        }

        try {
            $code = $this->smartAccountService->getInitCode((string) $account->owner_address, $networkKey);
        } catch (Throwable) {
            return '0x';
        }

        if ($code === '' || $code === '0x') {
            return '0x';
        }

        return str_starts_with($code, '0x') ? $code : ('0x' . $code);
    }

    /**
     * Resolve the next nonce for the smart account. Falls back to 0 when no
     * local tracking record is present (Privy-managed accounts may sit outside
     * our SmartAccount table).
     */
    private function resolveNonce(string $sender, string $networkKey): int
    {
        try {
            $account = $this->smartAccountService->getByAccountAddress($sender, $networkKey);
        } catch (Throwable) {
            return 0;
        }

        if ($account === null) {
            return 0;
        }

        return (int) ($account->nonce ?? 0);
    }

    /**
     * Compute the ERC-4337 v0.6 userOpHash.
     *
     * Spec:
     *   packed = keccak256(abi.encode(
     *     sender, nonce,
     *     keccak256(initCode), keccak256(callData),
     *     callGasLimit, verificationGasLimit, preVerificationGas,
     *     maxFeePerGas, maxPriorityFeePerGas,
     *     keccak256(paymasterAndData),
     *   ))
     *   userOpHash = keccak256(abi.encode(packed, entryPoint, chainId))
     *
     * abi.encode of static types: each value occupies a 32-byte big-endian
     * slot. Addresses are left-padded; uint256 values are left-padded. The
     * three keccak256 results are 32 bytes each (no extra padding needed).
     */
    private function computeV06UserOpHash(UserOperation $op, string $entryPoint, int $chainId): string
    {
        $senderHex = self::pad32Hex(self::stripHex($op->sender));
        $nonceHex = self::uintToPaddedHex($op->nonce);
        $initCodeHash = Keccak::hash(self::hexToBin($op->initCode), 256);
        $callDataHash = Keccak::hash(self::hexToBin($op->callData), 256);
        $callGasLimitHex = self::uintToPaddedHex($op->callGasLimit);
        $verificationGasLimitHex = self::uintToPaddedHex($op->verificationGasLimit);
        $preVerificationGasHex = self::uintToPaddedHex($op->preVerificationGas);
        $maxFeePerGasHex = self::uintToPaddedHex($op->maxFeePerGas);
        $maxPriorityFeePerGasHex = self::uintToPaddedHex($op->maxPriorityFeePerGas);
        $paymasterHash = Keccak::hash(self::hexToBin($op->paymasterAndData), 256);

        $encodedInner = $senderHex
            . $nonceHex
            . $initCodeHash
            . $callDataHash
            . $callGasLimitHex
            . $verificationGasLimitHex
            . $preVerificationGasHex
            . $maxFeePerGasHex
            . $maxPriorityFeePerGasHex
            . $paymasterHash;

        $packedHash = Keccak::hash(self::hexToBin('0x' . $encodedInner), 256);

        $entryPointHex = self::pad32Hex(self::stripHex($entryPoint));
        $chainIdHex = self::uintToPaddedHex($chainId);

        $finalEncoded = $packedHash . $entryPointHex . $chainIdHex;
        $userOpHash = Keccak::hash(self::hexToBin('0x' . $finalEncoded), 256);

        return '0x' . $userOpHash;
    }

    private static function stripHex(string $value): string
    {
        return str_starts_with($value, '0x') || str_starts_with($value, '0X')
            ? substr($value, 2)
            : $value;
    }

    private static function pad32Hex(string $hexNoPrefix): string
    {
        return str_pad(strtolower($hexNoPrefix), 64, '0', STR_PAD_LEFT);
    }

    private static function uintToPaddedHex(int $value): string
    {
        if ($value < 0) {
            throw new InvalidAmountException('Cannot encode negative value as uint256.');
        }

        return str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
    }

    private static function hexToBin(string $hex): string
    {
        $clean = self::stripHex($hex);

        if ($clean === '') {
            return '';
        }

        if ((strlen($clean) & 1) === 1) {
            $clean = '0' . $clean;
        }

        $bin = hex2bin($clean);

        return $bin === false ? '' : $bin;
    }
}
