<?php

declare(strict_types=1);

namespace App\Domain\Asset\Services;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Exceptions\AssetNotFoundException;
use App\Domain\Asset\Exceptions\InsufficientAssetBalanceException;
use App\Domain\Asset\Exceptions\UnsupportedAssetConversionException;
use App\Domain\Asset\Models\Asset;
use App\Domain\Shared\Contracts\AssetTransferInterface;
use App\Domain\Shared\Logging\AuditLogger;
use App\Domain\Shared\Validation\FinancialInputValidator;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssetTransferService implements AssetTransferInterface
{
    use FinancialInputValidator;
    use AuditLogger;

    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $assetCode,
        string $amount,
        string $reference = '',
        array $metadata = []
    ): string {
        // Input validation
        $this->validateUuid($fromAccountId, 'source account ID');
        $this->validateUuid($toAccountId, 'destination account ID');
        $this->validateAssetCode($assetCode);
        $this->validatePositiveAmount($amount);
        $this->validateReference($reference);
        $this->validateMetadata($metadata);

        $this->auditOperationStart('asset_transfer', [
            'from_account_id' => $fromAccountId,
            'to_account_id'   => $toAccountId,
            'asset_code'      => $assetCode,
            'amount'          => $amount,
        ]);

        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('The source and destination accounts must be different');
        }

        $this->validateAsset($assetCode);
        $amountMinor = $this->toMinorUnits($assetCode, $amount, exact: true);
        $this->validateSufficientBalance($fromAccountId, $assetCode, $amountMinor, $amount);

        $transferId = (string) Str::uuid();

        try {
            // Convert to value objects for aggregate
            $fromAccountUuid = new AccountUuid($fromAccountId);
            $toAccountUuid = new AccountUuid($toAccountId);
            $amountMoney = new Money($amountMinor);

            $aggregate = AssetTransferAggregate::retrieve($transferId);
            $aggregate->initiate(
                $fromAccountUuid,
                $toAccountUuid,
                $assetCode,
                $assetCode,
                $amountMoney,
                $amountMoney,
                1.0,
                null
            );
            // A ledger transfer has no outside step to wait for, so it completes
            // at once; the completion is what moves the balances.
            $aggregate->complete($transferId);
            $aggregate->persist();

            $this->auditOperationSuccess('asset_transfer', [
                'transfer_id'     => $transferId,
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $amount,
            ]);

            return $transferId;
        } catch (Exception $e) {
            $this->auditOperationFailure('asset_transfer', $e->getMessage(), [
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function convertAndTransfer(
        string $fromAccountId,
        string $toAccountId,
        string $fromAssetCode,
        string $toAssetCode,
        string $fromAmount,
        ?string $exchangeRate = null,
        string $reference = '',
        array $metadata = []
    ): array {
        // Input validation
        $this->validateUuid($fromAccountId, 'source account ID');
        $this->validateUuid($toAccountId, 'destination account ID');
        $this->validateAssetCode($fromAssetCode, 'source asset code');
        $this->validateAssetCode($toAssetCode, 'destination asset code');
        $this->validatePositiveAmount($fromAmount);
        $this->validateReference($reference);
        $this->validateMetadata($metadata);

        if ($exchangeRate !== null) {
            $this->validateExchangeRate($exchangeRate);
        }

        $this->auditOperationStart('asset_convert_transfer', [
            'from_account_id' => $fromAccountId,
            'to_account_id'   => $toAccountId,
            'from_asset_code' => $fromAssetCode,
            'to_asset_code'   => $toAssetCode,
            'from_amount'     => $fromAmount,
        ]);

        $this->validateAsset($fromAssetCode);
        $this->validateAsset($toAssetCode);
        $fromAmountMinor = $this->toMinorUnits($fromAssetCode, $fromAmount, exact: true);
        $this->validateSufficientBalance($fromAccountId, $fromAssetCode, $fromAmountMinor, $fromAmount);

        if ($exchangeRate === null) {
            $exchangeRate = $this->getExchangeRate($fromAssetCode, $toAssetCode);
            if ($exchangeRate === null) {
                $this->auditOperationFailure('asset_convert_transfer', 'Unsupported conversion', [
                    'from_asset_code' => $fromAssetCode,
                    'to_asset_code'   => $toAssetCode,
                ]);
                throw new UnsupportedAssetConversionException($fromAssetCode, $toAssetCode);
            }
        }

        /** @var numeric-string $a */
        $a = $fromAmount;
        /** @var numeric-string $b */
        $b = $exchangeRate;
        $toAmount = bcmul($a, $b, 8);
        // The converted amount is rounded down to the target asset's smallest unit.
        $toAmountMinor = $this->toMinorUnits($toAssetCode, $toAmount, exact: false);

        $transferId = (string) Str::uuid();

        try {
            // Convert to value objects for aggregate
            $fromAccountUuid = new AccountUuid($fromAccountId);
            $toAccountUuid = new AccountUuid($toAccountId);
            $fromAmountMoney = new Money($fromAmountMinor);
            $toAmountMoney = new Money($toAmountMinor);

            $aggregate = AssetTransferAggregate::retrieve($transferId);
            $aggregate->initiate(
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmountMoney,
                $toAmountMoney,
                (float) $exchangeRate,
                null
            );
            $aggregate->complete($transferId);
            $aggregate->persist();

            $result = [
                'transfer_id' => $transferId,
                'from_amount' => $fromAmount,
                'to_amount'   => $toAmount,
                'rate_used'   => $exchangeRate,
            ];

            $this->auditOperationSuccess('asset_convert_transfer', $result);

            return $result;
        } catch (Exception $e) {
            $this->auditOperationFailure('asset_convert_transfer', $e->getMessage(), [
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAssetDetails(string $assetCode): ?array
    {
        $this->validateAssetCode($assetCode);

        $asset = Asset::where('code', $assetCode)->first();

        if (! $asset) {
            return null;
        }

        return [
            'code'      => $asset->code,
            'name'      => $asset->name ?? $asset->code,
            'type'      => $asset->type ?? 'fiat',
            'symbol'    => $asset->symbol ?? $asset->code,
            'decimals'  => $asset->decimals ?? 2,
            'is_active' => $asset->is_active ?? true,
            'metadata'  => $asset->metadata ?? [],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAssets(?string $type = null, bool $activeOnly = true): array
    {
        $query = Asset::query();

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->map(fn ($asset) => [
            'code'      => $asset->code,
            'name'      => $asset->name ?? $asset->code,
            'type'      => $asset->type ?? 'fiat',
            'symbol'    => $asset->symbol ?? $asset->code,
            'is_active' => $asset->is_active ?? true,
        ])->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function assetExists(string $assetCode): bool
    {
        $this->validateAssetCode($assetCode);

        return Asset::where('code', $assetCode)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function validateOperation(
        string $assetCode,
        string $operation,
        array $context = []
    ): array {
        $errors = [];
        $warnings = [];

        // Validate asset code format
        try {
            $this->validateAssetCode($assetCode);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        if (! $this->assetExists($assetCode)) {
            $errors[] = "Asset {$assetCode} does not exist or is not active";
        }

        $validOperations = ['transfer', 'convert', 'deposit', 'withdraw'];
        if (! in_array($operation, $validOperations, true)) {
            $errors[] = "Invalid operation: {$operation}";
        }

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getExchangeRate(string $fromAssetCode, string $toAssetCode): ?string
    {
        $this->validateAssetCode($fromAssetCode, 'source asset code');
        $this->validateAssetCode($toAssetCode, 'target asset code');

        try {
            $rate = $this->exchangeRateService->getRate($fromAssetCode, $toAssetCode);

            return $rate === null ? null : (string) $rate->rate;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function calculateConversion(
        string $fromAssetCode,
        string $toAssetCode,
        string $amount
    ): ?array {
        $this->validateAssetCode($fromAssetCode, 'source asset code');
        $this->validateAssetCode($toAssetCode, 'target asset code');
        $this->validatePositiveAmount($amount);

        $rate = $this->getExchangeRate($fromAssetCode, $toAssetCode);

        if ($rate === null) {
            return null;
        }

        /** @var numeric-string $a */
        $a = $amount;
        /** @var numeric-string $b */
        $b = $rate;
        $convertedAmount = bcmul($a, $b, 8);

        // Assume 0.1% fee for conversions
        /** @var numeric-string $feeRate */
        $feeRate = '0.001';
        $feeAmount = bcmul($convertedAmount, $feeRate, 8);
        $netAmount = bcsub($convertedAmount, $feeAmount, 8);

        return [
            'converted_amount' => $convertedAmount,
            'rate_used'        => $rate,
            'fee_amount'       => $feeAmount,
            'net_amount'       => $netAmount,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getTransferStatus(string $transferId): ?array
    {
        $this->validateUuid($transferId, 'transfer ID');

        try {
            $aggregate = AssetTransferAggregate::retrieve($transferId);

            $fromAmount = $aggregate->getFromAmount();
            $toAmount = $aggregate->getToAmount();
            $fromAccountUuid = $aggregate->getFromAccountUuid();
            $toAccountUuid = $aggregate->getToAccountUuid();

            return [
                'id'              => $transferId,
                'status'          => (string) ($aggregate->getStatus() ?? 'unknown'),
                'from_account_id' => $fromAccountUuid !== null ? (string) $fromAccountUuid : '',
                'to_account_id'   => $toAccountUuid !== null ? (string) $toAccountUuid : '',
                'from_asset_code' => (string) ($aggregate->getFromAssetCode() ?? ''),
                'to_asset_code'   => (string) ($aggregate->getToAssetCode() ?? ''),
                'from_amount'     => $fromAmount !== null ? $this->minorUnitsToString((string) $aggregate->getFromAssetCode(), $fromAmount->getAmount()) : '0',
                'to_amount'       => $toAmount !== null ? $this->minorUnitsToString((string) $aggregate->getToAssetCode(), $toAmount->getAmount()) : '0',
                'exchange_rate'   => $aggregate->getExchangeRate() !== null ? (string) $aggregate->getExchangeRate() : null,
                'created_at'      => now()->toIso8601String(),
                'completed_at'    => null,
                'failure_reason'  => $aggregate->getFailureReason() !== null ? (string) $aggregate->getFailureReason() : null,
            ];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isConversionSupported(string $fromAssetCode, string $toAssetCode): bool
    {
        $this->validateAssetCode($fromAssetCode, 'source asset code');
        $this->validateAssetCode($toAssetCode, 'target asset code');

        return $this->getExchangeRate($fromAssetCode, $toAssetCode) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function formatAmount(string $assetCode, string $amount): string
    {
        $this->validateAssetCode($assetCode);

        $asset = Asset::where('code', $assetCode)->first();
        $decimals = $asset->decimals ?? 2;

        return number_format((float) $amount, $decimals, '.', '');
    }

    /**
     * Validate asset exists and is active.
     */
    private function validateAsset(string $assetCode): void
    {
        if (! $this->assetExists($assetCode)) {
            throw new AssetNotFoundException($assetCode);
        }
    }

    /**
     * Validate the account holds at least the amount, both in minor units.
     */
    private function validateSufficientBalance(string $accountId, string $assetCode, int $amountMinor, string $amount): void
    {
        $account = Account::where('uuid', $accountId)->first();

        if (! $account) {
            throw new InsufficientAssetBalanceException($accountId, $assetCode, $amount, '0');
        }

        $balance = $account->getBalance($assetCode);

        if ($balance < $amountMinor) {
            $available = $this->minorUnitsToString($assetCode, $balance);

            throw new InsufficientAssetBalanceException($accountId, $assetCode, $amount, $available);
        }
    }

    /**
     * Convert a decimal amount to the asset's minor units (its `precision`).
     *
     * With $exact, an amount with more decimals than the asset has is refused
     * instead of being cut; without it, extra decimals are rounded down.
     */
    private function toMinorUnits(string $assetCode, string $amount, bool $exact): int
    {
        /** @var numeric-string $value */
        $value = $amount;
        $factor = bcpow('10', (string) $this->precisionOf($assetCode));
        $minor = bcmul($value, $factor, 0);

        if ($exact && bccomp(bcmul($value, $factor, 8), $minor, 8) !== 0) {
            $precision = $this->precisionOf($assetCode);

            throw new InvalidArgumentException("{$assetCode} amounts can have at most {$precision} decimal places");
        }

        if (bccomp($minor, '0') <= 0) {
            throw new InvalidArgumentException("The amount is smaller than the smallest {$assetCode} unit");
        }

        return (int) $minor;
    }

    /**
     * Convert minor units back to a decimal amount in the asset's precision.
     */
    private function minorUnitsToString(string $assetCode, int $amount): string
    {
        $precision = $this->precisionOf($assetCode);

        return bcdiv((string) $amount, bcpow('10', (string) $precision), $precision);
    }

    private function precisionOf(string $assetCode): int
    {
        return (int) (Asset::query()->where('code', $assetCode)->value('precision') ?? 2);
    }
}
