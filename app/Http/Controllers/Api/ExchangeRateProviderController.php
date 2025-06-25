<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Exchange\Services\EnhancedExchangeRateService;
use App\Domain\Exchange\Services\ExchangeRateProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Exchange Rates",
 *     description="Exchange rate provider management and currency conversion"
 * )
 */
class ExchangeRateProviderController extends Controller
{
    public function __construct(
        private readonly ExchangeRateProviderRegistry $registry,
        private readonly EnhancedExchangeRateService $service
    ) {}

    /**
     * List available exchange rate providers
     * 
     * @OA\Get(
     *     path="/api/exchange-rates/providers",
     *     operationId="listExchangeRateProviders",
     *     tags={"Exchange Rates"},
     *     summary="List all available exchange rate providers",
     *     description="Returns a list of all registered exchange rate providers with their capabilities and status",
     *     @OA\Response(
     *         response=200,
     *         description="List of providers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="ecb"),
     *                     @OA\Property(property="display_name", type="string", example="European Central Bank"),
     *                     @OA\Property(property="available", type="boolean", example=true),
     *                     @OA\Property(property="priority", type="integer", example=100),
     *                     @OA\Property(
     *                         property="capabilities",
     *                         type="object",
     *                         @OA\Property(property="supports_historical", type="boolean"),
     *                         @OA\Property(property="supports_realtime", type="boolean"),
     *                         @OA\Property(property="supports_crypto", type="boolean")
     *                     ),
     *                     @OA\Property(
     *                         property="supported_currencies",
     *                         type="array",
     *                         @OA\Items(type="string", example="EUR")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="default", type="string", nullable=true, example="ecb")
     *         )
     *     )
     * )
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $providers = [];
        
        foreach ($this->registry->all() as $name => $provider) {
            $providers[] = [
                'name' => $name,
                'display_name' => $provider->getName(),
                'available' => $provider->isAvailable(),
                'priority' => $provider->getPriority(),
                'capabilities' => $provider->getCapabilities()->toArray(),
                'supported_currencies' => $provider->getSupportedCurrencies(),
            ];
        }
        
        return response()->json([
            'data' => $providers,
            'default' => $this->registry->names()[0] ?? null,
        ]);
    }

    /**
     * Get exchange rate from a specific provider
     * 
     * @OA\Get(
     *     path="/api/exchange-rates/providers/{provider}/rate",
     *     operationId="getExchangeRate",
     *     tags={"Exchange Rates"},
     *     summary="Get exchange rate from specific provider",
     *     description="Retrieves the current exchange rate for a currency pair from the specified provider",
     *     @OA\Parameter(
     *         name="provider",
     *         in="path",
     *         required=true,
     *         description="Provider identifier",
     *         @OA\Schema(type="string", example="ecb")
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Source currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="Target currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="USD")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rate retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="from_currency", type="string", example="EUR"),
     *                 @OA\Property(property="to_currency", type="string", example="USD"),
     *                 @OA\Property(property="rate", type="number", format="float", example=1.0825),
     *                 @OA\Property(property="bid", type="number", format="float", example=1.0820),
     *                 @OA\Property(property="ask", type="number", format="float", example=1.0830),
     *                 @OA\Property(property="provider", type="string", example="ecb"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Provider is not available",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Provider is not available")
     *         )
     *     )
     * )
     * 
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function getRate(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);
        
        try {
            $providerInstance = $this->registry->get($provider);
            
            if (!$providerInstance->isAvailable()) {
                return response()->json([
                    'error' => 'Provider is not available',
                ], 503);
            }
            
            $quote = $providerInstance->getRate($validated['from'], $validated['to']);
            
            return response()->json([
                'data' => $quote->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get exchange rate',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Compare rates from all available providers
     * 
     * @OA\Get(
     *     path="/api/exchange-rates/compare",
     *     operationId="compareExchangeRates",
     *     tags={"Exchange Rates"},
     *     summary="Compare rates from all providers",
     *     description="Retrieves and compares exchange rates for a currency pair from all available providers",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Source currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="Target currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="USD")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rate comparison retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object", description="Comparison data with provider rates"),
     *             @OA\Property(property="pair", type="string", example="EUR/USD"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     )
     * )
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function compareRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);
        
        try {
            $comparison = $this->service->compareRates($validated['from'], $validated['to']);
            
            return response()->json([
                'data' => $comparison,
                'pair' => "{$validated['from']}/{$validated['to']}",
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to compare rates',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get aggregated rate from multiple providers
     * 
     * @OA\Get(
     *     path="/api/exchange-rates/aggregated",
     *     operationId="getAggregatedExchangeRate",
     *     tags={"Exchange Rates"},
     *     summary="Get aggregated rate from multiple providers",
     *     description="Calculates an aggregated exchange rate using data from multiple providers",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Source currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="Target currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="USD")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aggregated rate retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="from_currency", type="string"),
     *                 @OA\Property(property="to_currency", type="string"),
     *                 @OA\Property(property="rate", type="number", format="float"),
     *                 @OA\Property(property="bid", type="number", format="float"),
     *                 @OA\Property(property="ask", type="number", format="float"),
     *                 @OA\Property(property="provider", type="string", example="aggregated"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     )
     * )
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAggregatedRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);
        
        try {
            $quote = $this->registry->getAggregatedRate($validated['from'], $validated['to']);
            
            return response()->json([
                'data' => $quote->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get aggregated rate',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Refresh exchange rates
     * 
     * @OA\Post(
     *     path="/api/exchange-rates/refresh",
     *     operationId="refreshExchangeRates",
     *     tags={"Exchange Rates"},
     *     summary="Refresh exchange rates",
     *     description="Manually triggers a refresh of exchange rates for specified currency pairs or all active pairs",
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="pairs",
     *                 type="array",
     *                 description="Optional array of currency pairs to refresh",
     *                 @OA\Items(
     *                     type="string",
     *                     pattern="^[A-Z]{3}/[A-Z]{3}$",
     *                     example="EUR/USD"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rates refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exchange rates refreshed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="refreshed",
     *                     type="array",
     *                     @OA\Items(type="string", example="EUR/USD")
     *                 ),
     *                 @OA\Property(
     *                     property="failed",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="pair", type="string"),
     *                         @OA\Property(property="error", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     )
     * )
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairs' => 'nullable|array',
            'pairs.*' => 'string|regex:/^[A-Z]{3}\/[A-Z]{3}$/',
        ]);
        
        try {
            if (isset($validated['pairs'])) {
                // Refresh specific pairs
                $results = ['refreshed' => [], 'failed' => []];
                
                foreach ($validated['pairs'] as $pair) {
                    [$from, $to] = explode('/', $pair);
                    try {
                        $this->service->fetchAndStoreRate($from, $to);
                        $results['refreshed'][] = $pair;
                    } catch (\Exception $e) {
                        $results['failed'][] = [
                            'pair' => $pair,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            } else {
                // Refresh all active rates
                $results = $this->service->refreshAllRates();
            }
            
            return response()->json([
                'message' => 'Exchange rates refreshed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to refresh rates',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get historical rates
     * 
     * @OA\Get(
     *     path="/api/exchange-rates/historical",
     *     operationId="getHistoricalExchangeRates",
     *     tags={"Exchange Rates"},
     *     summary="Get historical exchange rates",
     *     description="Retrieves historical exchange rates for a currency pair within a specified date range",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Source currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="Target currency code (3 characters)",
     *         @OA\Schema(type="string", pattern="^[A-Z]{3}$", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         description="Start date for historical data",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         description="End date for historical data (must be after or equal to start_date)",
     *         @OA\Schema(type="string", format="date", example="2024-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historical rates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", description="Array of historical rate data"),
     *             @OA\Property(property="pair", type="string", example="EUR/USD"),
     *             @OA\Property(
     *                 property="period",
     *                 type="object",
     *                 @OA\Property(property="start", type="string", format="date"),
     *                 @OA\Property(property="end", type="string", format="date")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     )
     * )
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function historical(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        
        try {
            $rates = $this->service->getHistoricalRates(
                $validated['from'],
                $validated['to'],
                new \DateTime($validated['start_date']),
                new \DateTime($validated['end_date'])
            );
            
            return response()->json([
                'data' => $rates,
                'pair' => "{$validated['from']}/{$validated['to']}",
                'period' => [
                    'start' => $validated['start_date'],
                    'end' => $validated['end_date'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get historical rates',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate a specific exchange rate
     * 
     * @OA\Post(
     *     path="/api/exchange-rates/validate",
     *     operationId="validateExchangeRate",
     *     tags={"Exchange Rates"},
     *     summary="Validate an exchange rate",
     *     description="Validates a given exchange rate against current market rates and configured thresholds",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from", "to", "rate"},
     *             @OA\Property(property="from", type="string", pattern="^[A-Z]{3}$", example="EUR", description="Source currency code"),
     *             @OA\Property(property="to", type="string", pattern="^[A-Z]{3}$", example="USD", description="Target currency code"),
     *             @OA\Property(property="rate", type="number", format="float", minimum=0, example=1.0825, description="Exchange rate to validate"),
     *             @OA\Property(property="provider", type="string", example="manual", description="Optional provider name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Validation result",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="is_valid", type="boolean"),
     *                 @OA\Property(property="deviation_percentage", type="number"),
     *                 @OA\Property(property="market_rate", type="number"),
     *                 @OA\Property(property="validation_messages", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters"
     *     )
     * )
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0',
            'provider' => 'nullable|string',
        ]);
        
        try {
            // Create a quote from the provided data
            $quote = new \App\Domain\Exchange\ValueObjects\ExchangeRateQuote(
                fromCurrency: $validated['from'],
                toCurrency: $validated['to'],
                rate: (float) $validated['rate'],
                bid: (float) $validated['rate'] * 0.999,
                ask: (float) $validated['rate'] * 1.001,
                provider: $validated['provider'] ?? 'manual',
                timestamp: now()
            );
            
            $validation = $this->service->validateQuote($quote);
            
            return response()->json([
                'data' => $validation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to validate rate',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}