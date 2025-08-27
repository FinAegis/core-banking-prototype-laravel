<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Services\ExchangeRateService;
use Exception;
use Workflow\Activity;

class ValidateExchangeRateActivity extends Activity
{
    public function __construct(
        private ExchangeRateService $exchangeRateService
    ) {
    }

    /**
     * Execute validate exchange rate activity.
     */
    public function execute(
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount
    ): array {
        // Get current exchange rate
        $rate = $this->exchangeRateService->getRate($fromAssetCode, $toAssetCode);

        if (! $rate) {
            throw new Exception("No exchange rate available for {$fromAssetCode} to {$toAssetCode}");
        }

        if (! $rate->isValid()) {
            throw new Exception("Exchange rate for {$fromAssetCode} to {$toAssetCode} is expired or invalid");
        }

        // Calculate target amount using the exchange rate
        $toAmountValue = $rate->convert($fromAmount->getAmount());
        $toAmount = Money::fromInt($toAmountValue);

        // Validate that the conversion is reasonable (not zero or negative)
        if ($toAmountValue <= 0) {
            throw new Exception("Invalid conversion result: {$fromAmount->getAmount()} {$fromAssetCode} converts to {$toAmountValue} {$toAssetCode}");
        }

        return [
            'exchange_rate'    => (float) $rate->rate,
            'to_amount'        => $toAmount,
            'rate_age_minutes' => $rate->getAgeInMinutes(),
            'rate_source'      => $rate->source,
            'rate_metadata'    => $rate->metadata,
        ];
    }
}
