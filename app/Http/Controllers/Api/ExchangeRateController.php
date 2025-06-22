<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Exchange Rates",
 *     description="APIs for managing exchange rates between different assets"
 * )
 */
class ExchangeRateController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {}
    
    /**
     * @OA\Get(
     *     path="/api/exchange-rates/{from}/{to}",
     *     operationId="getExchangeRate",
     *     tags={"Exchange Rates"},
     *     summary="Get current exchange rate",
     *     description="Retrieve the current exchange rate between two assets",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="path",
     *         required=true,
     *         description="The source asset code",
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="path",
     *         required=true,
     *         description="The target asset code",
     *         @OA\Schema(type="string", example="EUR")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="from_asset", type="string"),
     *                 @OA\Property(property="to_asset", type="string"),
     *                 @OA\Property(property="rate", type="string"),
     *                 @OA\Property(property="inverse_rate", type="string"),
     *                 @OA\Property(property="source", type="string"),
     *                 @OA\Property(property="valid_at", type="string", format="date-time"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="age_minutes", type="integer"),
     *                 @OA\Property(property="metadata", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exchange rate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show(string $from, string $to): JsonResponse
    {
        $fromAsset = strtoupper($from);
        $toAsset = strtoupper($to);
        
        $rate = $this->exchangeRateService->getRate($fromAsset, $toAsset);
        
        if (!$rate) {
            return response()->json([
                'message' => 'Exchange rate not found',
                'error' => 'No active exchange rate found for the specified asset pair',
            ], 404);
        }
        
        return response()->json([
            'data' => [
                'from_asset' => $rate->from_asset_code,
                'to_asset' => $rate->to_asset_code,
                'rate' => $rate->rate,
                'inverse_rate' => number_format($rate->getInverseRate(), 10, '.', ''),
                'source' => $rate->source,
                'valid_at' => $rate->valid_at->toISOString(),
                'expires_at' => $rate->expires_at?->toISOString(),
                'is_active' => $rate->is_active,
                'age_minutes' => $rate->getAgeInMinutes(),
                'metadata' => $rate->metadata,
            ],
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/exchange-rates/{from}/{to}/convert",
     *     operationId="convertCurrency",
     *     tags={"Exchange Rates"},
     *     summary="Convert amount between assets",
     *     description="Convert an amount from one asset to another using current exchange rates",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="path",
     *         required=true,
     *         description="The source asset code",
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="path",
     *         required=true,
     *         description="The target asset code",
     *         @OA\Schema(type="string", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="amount",
     *         in="query",
     *         required=true,
     *         description="The amount to convert (in smallest unit)",
     *         @OA\Schema(type="integer", example=10000)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="from_asset", type="string"),
     *                 @OA\Property(property="to_asset", type="string"),
     *                 @OA\Property(property="from_amount", type="integer"),
     *                 @OA\Property(property="to_amount", type="integer"),
     *                 @OA\Property(property="from_formatted", type="string"),
     *                 @OA\Property(property="to_formatted", type="string"),
     *                 @OA\Property(property="rate", type="string"),
     *                 @OA\Property(property="rate_age_minutes", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Conversion not available"
     *     )
     * )
     */
    public function convert(Request $request, string $from, string $to): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);
        
        $fromAsset = strtoupper($from);
        $toAsset = strtoupper($to);
        $amount = (int) $request->input('amount');
        
        $convertedAmount = $this->exchangeRateService->convert($amount, $fromAsset, $toAsset);
        
        if ($convertedAmount === null) {
            return response()->json([
                'message' => 'Conversion not available',
                'error' => 'No active exchange rate found for the specified asset pair',
            ], 404);
        }
        
        $rate = $this->exchangeRateService->getRate($fromAsset, $toAsset);
        
        // Get asset details for formatting
        $fromAssetModel = \App\Domain\Asset\Models\Asset::where('code', $fromAsset)->first();
        $toAssetModel = \App\Domain\Asset\Models\Asset::where('code', $toAsset)->first();
        
        $fromFormatted = $this->formatAmount($amount, $fromAssetModel);
        $toFormatted = $this->formatAmount($convertedAmount, $toAssetModel);
        
        return response()->json([
            'data' => [
                'from_asset' => $fromAsset,
                'to_asset' => $toAsset,
                'from_amount' => $amount,
                'to_amount' => $convertedAmount,
                'from_formatted' => $fromFormatted,
                'to_formatted' => $toFormatted,
                'rate' => $rate->rate,
                'rate_age_minutes' => $rate->getAgeInMinutes(),
            ],
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/exchange-rates",
     *     operationId="listExchangeRates",
     *     tags={"Exchange Rates"},
     *     summary="List exchange rates",
     *     description="Get a list of available exchange rates with filtering options",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=false,
     *         description="Filter by source asset code",
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=false,
     *         description="Filter by target asset code",
     *         @OA\Schema(type="string", example="EUR")
     *     ),
     *     @OA\Parameter(
     *         name="source",
     *         in="query",
     *         required=false,
     *         description="Filter by rate source",
     *         @OA\Schema(type="string", enum={"manual", "api", "oracle", "market"})
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         required=false,
     *         description="Filter by active status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="valid",
     *         in="query",
     *         required=false,
     *         description="Filter by validity (not expired)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of results per page (max 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="from_asset", type="string"),
     *                     @OA\Property(property="to_asset", type="string"),
     *                     @OA\Property(property="rate", type="string"),
     *                     @OA\Property(property="inverse_rate", type="string"),
     *                     @OA\Property(property="source", type="string"),
     *                     @OA\Property(property="valid_at", type="string", format="date-time"),
     *                     @OA\Property(property="expires_at", type="string", format="date-time"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="age_minutes", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="valid", type="integer"),
     *                 @OA\Property(property="stale", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|string|size:3',
            'to' => 'sometimes|string|size:3',
            'source' => ['sometimes', Rule::in(['manual', 'api', 'oracle', 'market'])],
            'active' => 'sometimes|boolean',
            'valid' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);
        
        $query = ExchangeRate::query();
        
        // Apply filters
        if ($request->has('from')) {
            $query->where('from_asset_code', strtoupper($request->string('from')->toString()));
        }
        
        if ($request->has('to')) {
            $query->where('to_asset_code', strtoupper($request->string('to')->toString()));
        }
        
        if ($request->has('source')) {
            $query->where('source', $request->string('source')->toString());
        }
        
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        
        if ($request->has('valid') && $request->boolean('valid')) {
            $query->valid();
        }
        
        $limit = $request->integer('limit', 50);
        $rates = $query->orderBy('valid_at', 'desc')->limit($limit)->get();
        
        // Calculate metadata
        $total = ExchangeRate::count();
        $valid = ExchangeRate::valid()->count();
        $stale = ExchangeRate::where('valid_at', '<=', now()->subDay())->count();
        
        return response()->json([
            'data' => $rates->map(function (ExchangeRate $rate) {
                return [
                    'id' => $rate->id,
                    'from_asset' => $rate->from_asset_code,
                    'to_asset' => $rate->to_asset_code,
                    'rate' => $rate->rate,
                    'inverse_rate' => number_format($rate->getInverseRate(), 10, '.', ''),
                    'source' => $rate->source,
                    'valid_at' => $rate->valid_at->toISOString(),
                    'expires_at' => $rate->expires_at?->toISOString(),
                    'is_active' => $rate->is_active,
                    'age_minutes' => $rate->getAgeInMinutes(),
                ];
            }),
            'meta' => [
                'total' => $total,
                'valid' => $valid,
                'stale' => $stale,
            ],
        ]);
    }
    
    private function formatAmount(int $amount, ?\App\Domain\Asset\Models\Asset $asset): string
    {
        if (!$asset) {
            return (string) $amount;
        }
        
        $formatted = number_format(
            $amount / (10 ** $asset->precision),
            $asset->precision,
            '.',
            ''
        );
        
        return "{$formatted} {$asset->code}";
    }
}