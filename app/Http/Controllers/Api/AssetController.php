<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Asset\Models\Asset;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Assets",
 *     description="Asset management endpoints"
 * )
 */
class AssetController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/assets",
     *     operationId="listAssets",
     *     tags={"Assets"},
     *     summary="List all supported assets",
     *     description="Get a list of all assets supported by the platform, including fiat currencies, cryptocurrencies, and commodities",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         required=false,
     *         description="Filter by active status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by asset type",
     *         @OA\Schema(type="string", enum={"fiat", "crypto", "commodity"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search by code or name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="code", type="string", example="USD"),
     *                     @OA\Property(property="name", type="string", example="US Dollar"),
     *                     @OA\Property(property="type", type="string", enum={"fiat", "crypto", "commodity"}),
     *                     @OA\Property(property="symbol", type="string", example="$"),
     *                     @OA\Property(property="precision", type="integer", example=2),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="metadata", type="object")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active", type="integer"),
     *                 @OA\Property(property="types", type="object",
     *                     @OA\Property(property="fiat", type="integer"),
     *                     @OA\Property(property="crypto", type="integer"),
     *                     @OA\Property(property="commodity", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query();
        
        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        
        // Filter by asset type
        if ($request->has('type')) {
            $query->where('type', $request->string('type')->toString());
        }
        
        // Search by code or name
        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        
        $assets = $query->orderBy('code')->get();
        
        // Calculate metadata
        $total = Asset::count();
        $active = Asset::where('is_active', true)->count();
        $types = Asset::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
        
        return response()->json([
            'data' => $assets->map(function (Asset $asset) {
                return [
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'type' => $asset->type,
                    'symbol' => $asset->symbol,
                    'precision' => $asset->precision,
                    'is_active' => $asset->is_active,
                    'metadata' => $asset->metadata,
                ];
            }),
            'meta' => [
                'total' => $total,
                'active' => $active,
                'types' => [
                    'fiat' => $types['fiat'] ?? 0,
                    'crypto' => $types['crypto'] ?? 0,
                    'commodity' => $types['commodity'] ?? 0,
                ],
            ],
        ]);
    }
    
    /**
     * Get asset details
     * 
     * Retrieve detailed information about a specific asset.
     * 
     * @urlParam code string required The asset code (e.g., USD, BTC, EUR). Example: USD
     * 
     * @response 200 {
     *   "data": {
     *     "code": "USD",
     *     "name": "US Dollar",
     *     "type": "fiat",
     *     "symbol": "$",
     *     "precision": 2,
     *     "is_active": true,
     *     "metadata": {
     *       "category": "currency",
     *       "regulated": true
     *     },
     *     "stats": {
     *       "total_accounts": 150,
     *       "total_balance": "1250000.00",
     *       "active_rates": 5
     *     }
     *   }
     * }
     * 
     * @response 404 {
     *   "message": "Asset not found",
     *   "error": "The specified asset code was not found"
     * }
     */
    public function show(string $code): JsonResponse
    {
        $asset = Asset::where('code', strtoupper($code))->first();
        
        if (!$asset) {
            return response()->json([
                'message' => 'Asset not found',
                'error' => 'The specified asset code was not found',
            ], 404);
        }
        
        // Calculate statistics
        $totalAccounts = $asset->accountBalances()->count();
        $totalBalance = $asset->accountBalances()->sum('balance');
        $activeRates = $asset->exchangeRatesFrom()->valid()->count();
        
        // Format balance according to asset precision
        $formattedBalance = number_format(
            $totalBalance / (10 ** $asset->precision),
            $asset->precision,
            '.',
            ''
        );
        
        return response()->json([
            'data' => [
                'code' => $asset->code,
                'name' => $asset->name,
                'type' => $asset->type,
                'symbol' => $asset->symbol,
                'precision' => $asset->precision,
                'is_active' => $asset->is_active,
                'metadata' => $asset->metadata,
                'stats' => [
                    'total_accounts' => $totalAccounts,
                    'total_balance' => $formattedBalance,
                    'active_rates' => $activeRates,
                ],
            ],
        ]);
    }
}