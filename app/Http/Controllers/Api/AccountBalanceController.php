<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Domain\Asset\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Account Balances",
 *     description="APIs for managing multi-asset account balances"
 * )
 */
class AccountBalanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/accounts/{uuid}/balances",
     *     operationId="getAccountBalances",
     *     tags={"Account Balances"},
     *     summary="Get account balances",
     *     description="Retrieve all asset balances for a specific account",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="The account UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="asset",
     *         in="query",
     *         required=false,
     *         description="Filter by specific asset code",
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="positive",
     *         in="query",
     *         required=false,
     *         description="Only show positive balances",
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_uuid", type="string", format="uuid"),
     *                 @OA\Property(property="balances", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="asset_code", type="string"),
     *                         @OA\Property(property="balance", type="integer"),
     *                         @OA\Property(property="formatted", type="string"),
     *                         @OA\Property(property="asset", type="object",
     *                             @OA\Property(property="code", type="string"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="type", type="string"),
     *                             @OA\Property(property="symbol", type="string"),
     *                             @OA\Property(property="precision", type="integer")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_assets", type="integer"),
     *                     @OA\Property(property="total_usd_equivalent", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Account not found"),
     *             @OA\Property(property="error", type="string", example="The specified account UUID was not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $account = Account::where('uuid', $uuid)->first();
        
        if (!$account) {
            return response()->json([
                'message' => 'Account not found',
                'error' => 'The specified account UUID was not found',
            ], 404);
        }
        
        $query = $account->balances()->with('asset');
        
        // Filter by specific asset
        if ($request->has('asset')) {
            $query->where('asset_code', strtoupper($request->string('asset')->toString()));
        }
        
        // Filter positive balances only
        if ($request->boolean('positive')) {
            $query->where('balance', '>', 0);
        }
        
        $balances = $query->get();
        
        // Calculate USD equivalent for summary
        $totalUsdEquivalent = $this->calculateUsdEquivalent($balances);
        
        return response()->json([
            'data' => [
                'account_uuid' => $account->uuid,
                'balances' => $balances->map(function ($balance) {
                    $asset = $balance->asset;
                    $formatted = $this->formatAmount($balance->balance, $asset);
                    
                    return [
                        'asset_code' => $balance->asset_code,
                        'balance' => $balance->balance,
                        'formatted' => $formatted,
                        'asset' => [
                            'code' => $asset->code,
                            'name' => $asset->name,
                            'type' => $asset->type,
                            'symbol' => $asset->symbol,
                            'precision' => $asset->precision,
                        ],
                    ];
                }),
                'summary' => [
                    'total_assets' => $balances->where('balance', '>', 0)->count(),
                    'total_usd_equivalent' => number_format($totalUsdEquivalent, 2),
                ],
            ],
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/balances",
     *     operationId="listAllBalances",
     *     tags={"Account Balances"},
     *     summary="List all account balances",
     *     description="Get balances across all accounts with filtering and aggregation options",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="asset",
     *         in="query",
     *         required=false,
     *         description="Filter by specific asset code",
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="min_balance",
     *         in="query",
     *         required=false,
     *         description="Minimum balance filter (in smallest unit)",
     *         @OA\Schema(type="integer", example=1000)
     *     ),
     *     @OA\Parameter(
     *         name="user_uuid",
     *         in="query",
     *         required=false,
     *         description="Filter by account owner",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of results per page (max 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="account_uuid", type="string", format="uuid"),
     *                     @OA\Property(property="asset_code", type="string"),
     *                     @OA\Property(property="balance", type="integer"),
     *                     @OA\Property(property="formatted", type="string"),
     *                     @OA\Property(property="account", type="object",
     *                         @OA\Property(property="uuid", type="string", format="uuid"),
     *                         @OA\Property(property="user_uuid", type="string", format="uuid")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_accounts", type="integer"),
     *                 @OA\Property(property="total_balances", type="integer"),
     *                 @OA\Property(property="asset_totals", type="object",
     *                     additionalProperties=@OA\Property(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'asset' => 'sometimes|string|size:3',
            'min_balance' => 'sometimes|integer|min:0',
            'user_uuid' => 'sometimes|uuid',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);
        
        $query = \App\Models\AccountBalance::with(['account', 'asset']);
        
        // Apply filters
        if ($request->has('asset')) {
            $query->where('asset_code', strtoupper($request->string('asset')->toString()));
        }
        
        if ($request->has('min_balance')) {
            $query->where('balance', '>=', $request->integer('min_balance'));
        }
        
        if ($request->has('user_uuid')) {
            $query->whereHas('account', function ($q) use ($request) {
                $q->where('user_uuid', $request->string('user_uuid')->toString());
            });
        }
        
        $limit = $request->integer('limit', 50);
        $balances = $query->orderBy('balance', 'desc')->limit($limit)->get();
        
        // Calculate aggregations
        $totalAccounts = Account::count();
        $totalBalances = \App\Models\AccountBalance::count();
        $assetTotals = $this->calculateAssetTotals();
        
        return response()->json([
            'data' => $balances->map(function ($balance) {
                return [
                    'account_uuid' => $balance->account_uuid,
                    'asset_code' => $balance->asset_code,
                    'balance' => $balance->balance,
                    'formatted' => $this->formatAmount($balance->balance, $balance->asset),
                    'account' => [
                        'uuid' => $balance->account->uuid,
                        'user_uuid' => $balance->account->user_uuid,
                    ],
                ];
            }),
            'meta' => [
                'total_accounts' => $totalAccounts,
                'total_balances' => $totalBalances,
                'asset_totals' => $assetTotals,
            ],
        ]);
    }
    
    private function formatAmount(int $amount, Asset $asset): string
    {
        $formatted = number_format(
            $amount / (10 ** $asset->precision),
            $asset->precision,
            '.',
            ''
        );
        
        return "{$formatted} {$asset->code}";
    }
    
    private function calculateUsdEquivalent($balances): float
    {
        // Calculate without caching to avoid type issues
        $total = 0.0;
        
        foreach ($balances as $balance) {
            if ($balance->asset_code === 'USD') {
                $total += $balance->balance / 100; // USD is stored in cents
            } else {
                // For now, return 0 for non-USD. In production, you'd convert using exchange rates
                // $rate = app(ExchangeRateService::class)->getRate($balance->asset_code, 'USD');
                // if ($rate) {
                //     $usdAmount = $rate->convert($balance->balance);
                //     $total += $usdAmount / 100;
                // }
            }
        }
        
        return $total;
    }
    
    private function calculateAssetTotals(): array
    {
        return Cache::remember('asset_totals', 300, function () {
            $totals = \App\Models\AccountBalance::selectRaw('asset_code, SUM(balance) as total')
                ->groupBy('asset_code')
                ->with('asset')
                ->get()
                ->mapWithKeys(function ($item) {
                    $asset = Asset::where('code', $item->asset_code)->first();
                    if ($asset) {
                        $formatted = number_format(
                            $item->total / (10 ** $asset->precision),
                            $asset->precision,
                            '.',
                            ''
                        );
                        return [$item->asset_code => $formatted];
                    }
                    return [$item->asset_code => (string) $item->total];
                });
            
            return $totals->toArray();
        });
    }
}