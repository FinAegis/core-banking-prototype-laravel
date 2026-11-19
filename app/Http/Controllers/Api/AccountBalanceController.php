<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Http\Controllers\Controller;
use App\Support\StaffRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Account Balances',
    description: 'APIs for managing multi-asset account balances'
)]
class AccountBalanceController extends Controller
{
        #[OA\Get(
            path: '/api/accounts/{uuid}/balances',
            operationId: 'getAccountBalances',
            tags: ['Account Balances'],
            summary: 'Get account balances',
            description: 'Retrieve all asset balances for a specific account',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'The account UUID', schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
            new OA\Parameter(name: 'asset', in: 'query', required: false, description: 'Filter by specific asset code', schema: new OA\Schema(type: 'string', example: 'USD')),
            new OA\Parameter(name: 'positive', in: 'query', required: false, description: 'Only show positive balances', schema: new OA\Schema(type: 'boolean', example: true)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'balances', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'asset_code', type: 'string'),
        new OA\Property(property: 'balance', type: 'integer'),
        new OA\Property(property: 'formatted', type: 'string'),
        new OA\Property(property: 'asset', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'symbol', type: 'string'),
        new OA\Property(property: 'precision', type: 'integer'),
        ]),
        ])),
        new OA\Property(property: 'summary', type: 'object', properties: [
        new OA\Property(property: 'total_assets', type: 'integer'),
        new OA\Property(property: 'total_usd_equivalent', type: 'string'),
        ]),
        ]),
        ])
    )]
    #[OA\Response(
        response: 403,
        description: 'The account belongs to another user',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this account'),
        new OA\Property(property: 'error', type: 'string', example: 'FORBIDDEN'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Account not found'),
        new OA\Property(property: 'error', type: 'string', example: 'The specified account UUID was not found'),
        ])
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        // The lookup ignores the BelongsToTeam scope; the owner check below
        // is what limits access (an owner's account may be in no team or
        // another team).
        $account = Account::query()->withoutGlobalScope('team')->where('uuid', $uuid)->first();

        if (! $account) {
            return response()->json(
                [
                    'message' => 'Account not found',
                    'error'   => 'The specified account UUID was not found',
                ],
                404
            );
        }

        // Only the account owner may read its balances (same response as
        // the REST account endpoints).
        if (! $account->isOwnedBy($request->user())) {
            return response()->json(
                [
                    'message' => 'Access denied to this account',
                    'error'   => 'FORBIDDEN',
                ],
                403
            );
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

        return response()->json(
            [
                'data' => [
                    'account_uuid' => $account->uuid,
                    'balances'     => $balances->map(
                        function ($balance) {
                            $asset = $balance->asset;
                            $formatted = $this->formatAmount($balance->balance, $asset);

                            return [
                                'asset_code' => $balance->asset_code,
                                'balance'    => $balance->balance,
                                'formatted'  => $formatted,
                                'asset'      => [
                                    'code'      => $asset->code,
                                    'name'      => $asset->name,
                                    'type'      => $asset->type,
                                    'symbol'    => $asset->symbol,
                                    'precision' => $asset->precision,
                                ],
                            ];
                        }
                    ),
                    'summary' => [
                        'total_assets'         => $balances->where('balance', '>', 0)->count(),
                        'total_usd_equivalent' => number_format($totalUsdEquivalent, 2),
                    ],
                ],
            ]
        );
    }

        #[OA\Get(
            path: '/api/balances',
            operationId: 'listAllBalances',
            tags: ['Account Balances'],
            summary: 'List account balances',
            description: 'Balances of the caller\'s own accounts with filtering and aggregation options; the totals in meta cover the same accounts. Operators (admin, super_admin, bank_admin) see every account, may filter by user_uuid and get platform-wide totals.',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'asset', in: 'query', required: false, description: 'Filter by specific asset code', schema: new OA\Schema(type: 'string', example: 'USD')),
            new OA\Parameter(name: 'min_balance', in: 'query', required: false, description: 'Minimum balance filter (in smallest unit)', schema: new OA\Schema(type: 'integer', example: 1000)),
            new OA\Parameter(name: 'user_uuid', in: 'query', required: false, description: 'Filter by account owner (operators; for other callers it only narrows their own accounts)', schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Number of results per page (max 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'asset_code', type: 'string'),
        new OA\Property(property: 'balance', type: 'integer'),
        new OA\Property(property: 'formatted', type: 'string'),
        new OA\Property(property: 'account', type: 'object', properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'user_uuid', type: 'string', format: 'uuid'),
        ]),
        ])),
        new OA\Property(property: 'meta', type: 'object', properties: [
        new OA\Property(property: 'total_accounts', type: 'integer'),
        new OA\Property(property: 'total_balances', type: 'integer'),
        new OA\Property(property: 'asset_totals', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(
            [
                'asset'       => 'sometimes|string|size:3',
                'min_balance' => 'sometimes|integer|min:0',
                'user_uuid'   => 'sometimes|uuid',
                'limit'       => 'sometimes|integer|min:1|max:100',
            ]
        );

        // Regular users see their own accounts only; operators keep the
        // platform-wide view, the user_uuid filter and platform totals.
        // Account lookups ignore the BelongsToTeam scope: user_uuid is the
        // ownership check, and an owner's account may have no team or
        // another team.
        $user = $request->user();
        $ownAccountUuids = null;

        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            $ownAccountUuids = array_values(
                Account::query()
                    ->withoutGlobalScope('team')
                    ->where('user_uuid', (string) ($user->uuid ?? ''))
                    ->pluck('uuid')
                    ->map(static fn ($uuid): string => (string) $uuid)
                    ->all()
            );
        }

        $query = AccountBalance::with([
            'account' => static fn ($q) => $q->withoutGlobalScope('team'),
            'asset',
        ]);

        if ($ownAccountUuids !== null) {
            $query->whereIn('account_uuid', $ownAccountUuids);
        }

        // Apply filters
        if ($request->has('asset')) {
            $query->where('asset_code', strtoupper($request->string('asset')->toString()));
        }

        if ($request->has('min_balance')) {
            $query->where('balance', '>=', $request->integer('min_balance'));
        }

        if ($request->has('user_uuid')) {
            $query->whereHas(
                'account',
                function ($q) use ($request) {
                    /** @phpstan-ignore-next-line */
                    $q->withoutGlobalScope('team')->where('user_uuid', $request->string('user_uuid')->toString());
                }
            );
        }

        $limit = $request->integer('limit', 50);
        $balances = $query->orderBy('balance', 'desc')->limit($limit)->get();

        // Calculate aggregations
        if ($ownAccountUuids === null) {
            $totalAccounts = Account::count();
            $totalBalances = AccountBalance::count();
        } else {
            $totalAccounts = count($ownAccountUuids);
            $totalBalances = AccountBalance::query()->whereIn('account_uuid', $ownAccountUuids)->count();
        }
        $assetTotals = $this->calculateAssetTotals($ownAccountUuids);

        return response()->json(
            [
                'data' => $balances->map(
                    function ($balance) {
                        return [
                            'account_uuid' => $balance->account_uuid,
                            'asset_code'   => $balance->asset_code,
                            'balance'      => $balance->balance,
                            'formatted'    => $this->formatAmount($balance->balance, $balance->asset),
                            'account'      => [
                                'uuid'      => $balance->account->uuid ?? $balance->account_uuid,
                                'user_uuid' => $balance->account?->user_uuid,
                            ],
                        ];
                    }
                ),
                'meta' => [
                    'total_accounts' => $totalAccounts,
                    'total_balances' => $totalBalances,
                    'asset_totals'   => $assetTotals,
                ],
            ]
        );
    }

    private function formatAmount(int $amount, Asset $asset): string
    {
        $formatted = $this->formatMinorUnits($amount, (int) $asset->precision);

        return "{$formatted} {$asset->code}";
    }

    /**
     * Minor units as a decimal string with the given number of decimals,
     * computed with bcmath so large amounts keep every digit.
     */
    private function formatMinorUnits(int|string $amount, int $precision): string
    {
        return bcdiv((string) $amount, bcpow('10', (string) $precision), $precision);
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

    /**
     * Summed balance per asset: platform-wide (cached) when $accountUuids is
     * null, otherwise over those accounts only.
     *
     * @param  list<string>|null  $accountUuids
     * @return array<string, string>
     */
    private function calculateAssetTotals(?array $accountUuids = null): array
    {
        if ($accountUuids !== null) {
            return $this->sumAssetTotals($accountUuids);
        }

        return Cache::remember(
            'asset_totals',
            300,
            fn () => $this->sumAssetTotals(null)
        );
    }

    /**
     * @param  list<string>|null  $accountUuids
     * @return array<string, string>
     */
    private function sumAssetTotals(?array $accountUuids): array
    {
        $query = AccountBalance::selectRaw('asset_code, SUM(balance) as total');

        if ($accountUuids !== null) {
            $query->whereIn('account_uuid', $accountUuids);
        }

        $totals = $query
            ->groupBy('asset_code')
            ->with('asset')
            ->get()
            ->mapWithKeys(
                function ($item) {
                    $asset = Asset::where('code', $item->asset_code)->first();
                    if ($asset) {
                        return [$item->asset_code => $this->formatMinorUnits((string) $item->total, (int) $asset->precision)];
                    }

                    return [$item->asset_code => (string) $item->total];
                }
            );

        return $totals->toArray();
    }
}
