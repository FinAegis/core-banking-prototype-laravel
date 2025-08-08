<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Exchange;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Exchange\Services\EnhancedExchangeRateService;
use Illuminate\Support\Facades\Log;

class QuoteTool implements MCPToolInterface
{
    public function __construct(
        private readonly EnhancedExchangeRateService $exchangeRateService
    ) {
    }

    public function getName(): string
    {
        return 'exchange.quote';
    }

    public function getCategory(): string
    {
        return 'exchange';
    }

    public function getDescription(): string
    {
        return 'Get exchange rate quote for currency pair';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'from_currency' => [
                    'type'        => 'string',
                    'description' => 'Source currency code (e.g., USD, EUR)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'to_currency' => [
                    'type'        => 'string',
                    'description' => 'Target currency code (e.g., USD, EUR)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'Optional amount to convert (default: 1)',
                    'minimum'     => 0.00000001,
                ],
                'include_fees' => [
                    'type'        => 'boolean',
                    'description' => 'Include fee calculation in quote',
                    'default'     => false,
                ],
            ],
            'required' => ['from_currency', 'to_currency'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'from_currency'     => ['type' => 'string'],
                'to_currency'       => ['type' => 'string'],
                'exchange_rate'     => ['type' => 'number'],
                'amount'            => ['type' => 'number'],
                'converted_amount'  => ['type' => 'number'],
                'formatted_rate'    => ['type' => 'string'],
                'formatted_amount'  => ['type' => 'string'],
                'fee_amount'        => ['type' => 'number'],
                'total_amount'      => ['type' => 'number'],
                'timestamp'         => ['type' => 'string'],
                'provider'          => ['type' => 'string'],
                'inverse_rate'      => ['type' => 'number'],
                'spread_percentage' => ['type' => 'number'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $fromCurrency = $parameters['from_currency'];
            $toCurrency = $parameters['to_currency'];
            $amount = (float) ($parameters['amount'] ?? 1.0);
            $includeFees = $parameters['include_fees'] ?? false;

            Log::info('MCP Tool: Getting exchange quote', [
                'from_currency'   => $fromCurrency,
                'to_currency'     => $toCurrency,
                'amount'          => $amount,
                'conversation_id' => $conversationId,
            ]);

            // Validate currencies exist
            $fromAsset = Asset::where('code', $fromCurrency)->first();
            $toAsset = Asset::where('code', $toCurrency)->first();

            if (! $fromAsset) {
                return ToolExecutionResult::failure("Invalid source currency: {$fromCurrency}");
            }

            if (! $toAsset) {
                return ToolExecutionResult::failure("Invalid target currency: {$toCurrency}");
            }

            // Check if both assets are tradeable
            if (! $fromAsset->is_tradeable || ! $toAsset->is_tradeable) {
                return ToolExecutionResult::failure('One or both currencies are not available for trading');
            }

            // Get exchange rate with fallback to external providers
            $rate = $this->exchangeRateService->getRateWithFallback($fromCurrency, $toCurrency);

            // Calculate converted amount
            $convertedAmount = $amount * $rate;

            // Calculate fees if requested (example: 0.2% exchange fee)
            $feeAmount = 0;
            $totalAmount = $convertedAmount;
            if ($includeFees) {
                $feeAmount = $convertedAmount * 0.002; // 0.2% fee
                $totalAmount = $convertedAmount - $feeAmount;
            }

            // Get inverse rate for reference
            $inverseRate = 1 / $rate;

            // Calculate spread (example: 0.1% spread)
            $spreadPercentage = 0.1;

            // Get the exchange rate record for provider info
            $exchangeRateRecord = ExchangeRate::where('from_asset_code', $fromCurrency)
                ->where('to_asset_code', $toCurrency)
                ->where('is_active', true)
                ->first();

            $provider = $exchangeRateRecord ? ($exchangeRateRecord->provider ?? 'internal') : 'calculated';

            $response = [
                'from_currency'     => $fromCurrency,
                'to_currency'       => $toCurrency,
                'exchange_rate'     => $rate,
                'amount'            => $amount,
                'converted_amount'  => round($convertedAmount, 8),
                'formatted_rate'    => $this->formatRate($rate, $fromCurrency, $toCurrency),
                'formatted_amount'  => $this->formatMoney($totalAmount, $toCurrency),
                'fee_amount'        => round($feeAmount, 8),
                'total_amount'      => round($totalAmount, 8),
                'timestamp'         => now()->toIso8601String(),
                'provider'          => $provider,
                'inverse_rate'      => round($inverseRate, 8),
                'spread_percentage' => $spreadPercentage,
            ];

            return ToolExecutionResult::success($response);
        } catch (\Exception $e) {
            Log::error('MCP Tool error: exchange.quote', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'real-time',
            'external-providers',
            'multi-currency',
            'fee-calculation',
        ];
    }

    public function isCacheable(): bool
    {
        return true; // Quotes can be cached for a short time
    }

    public function getCacheTtl(): int
    {
        return 30; // Cache for 30 seconds
    }

    public function validateInput(array $parameters): bool
    {
        // Currency validation
        foreach (['from_currency', 'to_currency'] as $field) {
            if (! isset($parameters[$field])) {
                return false;
            }

            $currency = $parameters[$field];
            if (! preg_match('/^[A-Z]{3,10}$/', $currency)) {
                return false;
            }
        }

        // Prevent same currency conversion
        if ($parameters['from_currency'] === $parameters['to_currency']) {
            return false;
        }

        // Amount validation if provided
        if (isset($parameters['amount'])) {
            $amount = $parameters['amount'];
            if (! is_numeric($amount) || $amount <= 0) {
                return false;
            }
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Exchange quotes are generally public information
        // No authentication required for basic quotes
        return true;
    }

    private function formatRate(float $rate, string $from, string $to): string
    {
        $decimals = $this->getDecimalsForRate($rate);
        $formatted = number_format($rate, $decimals);

        return "1 {$from} = {$formatted} {$to}";
    }

    private function formatMoney(float $amount, string $currency): string
    {
        $decimals = in_array($currency, ['BTC', 'ETH']) ? 8 : 2;
        $formatted = number_format($amount, $decimals);

        return "{$formatted} {$currency}";
    }

    private function getDecimalsForRate(float $rate): int
    {
        // Use more decimals for very small or very large rates
        if ($rate < 0.0001 || $rate > 10000) {
            return 8;
        } elseif ($rate < 0.01 || $rate > 1000) {
            return 6;
        } else {
            return 4;
        }
    }
}
