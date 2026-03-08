<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Stablecoin Operations',
    description: 'Stablecoin minting, burning, and collateral management'
)]
class StablecoinOperationsController extends Controller
{
    public function __construct(
        private readonly StablecoinIssuanceService $issuanceService,
        private readonly CollateralService $collateralService,
        private readonly LiquidationService $liquidationService
    ) {
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/mint',
            operationId: 'mintStablecoin',
            tags: ['Stablecoin Operations'],
            summary: 'Mint stablecoins by locking collateral',
            description: 'Create a new collateral position and mint stablecoins against it',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['account_uuid', 'stablecoin_code', 'collateral_asset_code', 'collateral_amount', 'mint_amount'], properties: [
            new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', description: 'Account UUID to mint for'),
            new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD', description: 'Stablecoin to mint'),
            new OA\Property(property: 'collateral_asset_code', type: 'string', example: 'USD', description: 'Asset to use as collateral'),
            new OA\Property(property: 'collateral_amount', type: 'integer', example: 150000, description: 'Amount of collateral to lock (in smallest unit)'),
            new OA\Property(property: 'mint_amount', type: 'integer', example: 100000, description: 'Amount of stablecoin to mint (in smallest unit)'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Stablecoin minted successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Stablecoin minted successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'position_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'collateral_asset_code', type: 'string', example: 'USD'),
        new OA\Property(property: 'collateral_amount', type: 'integer', example: 150000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 100000),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '1.5000'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function mint(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_uuid'          => 'required|uuid|exists:accounts,uuid',
                'stablecoin_code'       => 'required|string|exists:stablecoins,code',
                'collateral_asset_code' => 'required|string|exists:assets,code',
                'collateral_amount'     => 'required|integer|min:1',
                'mint_amount'           => 'required|integer|min:1',
            ]
        );

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();

        try {
            $position = $this->issuanceService->mint(
                $account,
                $validated['stablecoin_code'],
                $validated['collateral_asset_code'],
                $validated['collateral_amount'],
                $validated['mint_amount']
            );

            return response()->json(
                [
                    'message' => 'Stablecoin minted successfully',
                    'data'    => $position->load(['stablecoin', 'collateralAsset']),
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                400
            );
        }
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/burn',
            operationId: 'burnStablecoin',
            tags: ['Stablecoin Operations'],
            summary: 'Burn stablecoins and release collateral',
            description: 'Burn stablecoins to reduce debt and optionally release collateral',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['account_uuid', 'stablecoin_code', 'burn_amount'], properties: [
            new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', description: 'Account UUID to burn from'),
            new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD', description: 'Stablecoin to burn'),
            new OA\Property(property: 'burn_amount', type: 'integer', example: 50000, description: 'Amount of stablecoin to burn (in smallest unit)'),
            new OA\Property(property: 'collateral_release_amount', type: 'integer', example: 75000, description: 'Specific amount of collateral to release. If not provided, proportional amount will be released'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Stablecoin burned successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Stablecoin burned successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'position_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'collateral_amount', type: 'integer', example: 75000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 50000),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '1.5000'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function burn(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_uuid'              => 'required|uuid|exists:accounts,uuid',
                'stablecoin_code'           => 'required|string|exists:stablecoins,code',
                'burn_amount'               => 'required|integer|min:1',
                'collateral_release_amount' => 'nullable|integer|min:0',
            ]
        );

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();

        try {
            $position = $this->issuanceService->burn(
                $account,
                $validated['stablecoin_code'],
                $validated['burn_amount'],
                $validated['collateral_release_amount'] ?? null
            );

            return response()->json(
                [
                    'message' => 'Stablecoin burned successfully',
                    'data'    => $position->load(['stablecoin', 'collateralAsset']),
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                400
            );
        }
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/add-collateral',
            operationId: 'addCollateral',
            tags: ['Stablecoin Operations'],
            summary: 'Add collateral to an existing position',
            description: 'Increase collateral to improve position health and collateral ratio',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['account_uuid', 'stablecoin_code', 'collateral_asset_code', 'collateral_amount'], properties: [
            new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', description: 'Account UUID'),
            new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD', description: 'Stablecoin code'),
            new OA\Property(property: 'collateral_asset_code', type: 'string', example: 'USD', description: 'Collateral asset code'),
            new OA\Property(property: 'collateral_amount', type: 'integer', example: 50000, description: 'Amount of collateral to add (in smallest unit)'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Collateral added successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Collateral added successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'position_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'collateral_amount', type: 'integer', example: 200000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 100000),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '2.0000'),
        new OA\Property(property: 'health_improved', type: 'boolean', example: true),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function addCollateral(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'account_uuid'          => 'required|uuid|exists:accounts,uuid',
                'stablecoin_code'       => 'required|string|exists:stablecoins,code',
                'collateral_asset_code' => 'required|string|exists:assets,code',
                'collateral_amount'     => 'required|integer|min:1',
            ]
        );

        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();

        try {
            $position = $this->issuanceService->addCollateral(
                $account,
                $validated['stablecoin_code'],
                $validated['collateral_asset_code'],
                $validated['collateral_amount']
            );

            return response()->json(
                [
                    'message' => 'Collateral added successfully',
                    'data'    => $position->load(['stablecoin', 'collateralAsset']),
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                400
            );
        }
    }

        #[OA\Get(
            path: '/api/v2/stablecoin-operations/accounts/{accountUuid}/positions',
            operationId: 'getAccountPositions',
            tags: ['Stablecoin Operations'],
            summary: 'Get account\'s stablecoin positions',
            description: 'Retrieve all collateral positions for a specific account',
            parameters: [
            new OA\Parameter(name: 'accountUuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'collateral_asset_code', type: 'string', example: 'USD'),
        new OA\Property(property: 'collateral_amount', type: 'integer', example: 150000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 100000),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '1.5000'),
        new OA\Property(property: 'liquidation_price', type: 'string', example: '0.80000000'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'health_score', type: 'number', example: 0.75),
        new OA\Property(property: 'recommendations', type: 'array', items: new OA\Items(type: 'object')),
        ])),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Account not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function getAccountPositions(string $accountUuid): JsonResponse
    {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        $positions = StablecoinCollateralPosition::where('account_uuid', $accountUuid)
            ->with(['stablecoin', 'collateralAsset'])
            ->get();

        $enhancedPositions = $positions->map(
            function ($position) {
                $this->collateralService->updatePositionCollateralRatio($position);
                $healthScore = $this->collateralService->calculatePositionHealthScore($position);
                $recommendations = $this->collateralService->getPositionRecommendations($position);

                return array_merge(
                    $position->toArray(),
                    [
                        'health_score'    => $healthScore,
                        'recommendations' => $recommendations,
                    ]
                );
            }
        );

        return response()->json(
            [
                'data' => $enhancedPositions,
            ]
        );
    }

        #[OA\Get(
            path: '/api/v2/stablecoin-operations/positions/{positionUuid}',
            operationId: 'getPositionDetails',
            tags: ['Stablecoin Operations'],
            summary: 'Get position details with recommendations',
            description: 'Retrieve detailed information about a specific collateral position including health metrics and recommendations',
            parameters: [
            new OA\Parameter(name: 'positionUuid', in: 'path', description: 'Position UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'collateral_asset_code', type: 'string', example: 'USD'),
        new OA\Property(property: 'collateral_amount', type: 'integer', example: 150000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 100000),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '1.5000'),
        new OA\Property(property: 'health_score', type: 'number', example: 0.75),
        new OA\Property(property: 'max_mint_amount', type: 'integer', example: 25000),
        new OA\Property(property: 'liquidation_price', type: 'string', example: '0.80000000'),
        new OA\Property(property: 'is_at_risk', type: 'boolean', example: false),
        new OA\Property(property: 'recommendations', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'action', type: 'string', example: 'mint_more'),
        new OA\Property(property: 'urgency', type: 'string', example: 'low'),
        new OA\Property(property: 'message', type: 'string', example: 'Position is over-collateralized, you can mint more stablecoins'),
        new OA\Property(property: 'max_mint_amount', type: 'integer', example: 25000),
        ])),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Position not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function getPositionDetails(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)
            ->with(['stablecoin', 'collateralAsset', 'account'])
            ->firstOrFail();

        $this->collateralService->updatePositionCollateralRatio($position);

        $healthScore = $this->collateralService->calculatePositionHealthScore($position);
        $recommendations = $this->collateralService->getPositionRecommendations($position);
        $maxMintAmount = $position->calculateMaxMintAmount();

        $data = $position->toArray();
        $data['health_score'] = $healthScore;
        $data['max_mint_amount'] = $maxMintAmount;
        $data['is_at_risk'] = $position->isAtRiskOfLiquidation();
        $data['recommendations'] = $recommendations;

        return response()->json(
            [
                'data' => $data,
            ]
        );
    }

        #[OA\Get(
            path: '/api/v2/stablecoin-operations/liquidation-opportunities',
            operationId: 'getLiquidationOpportunities',
            tags: ['Stablecoin Operations'],
            summary: 'Get liquidation opportunities',
            description: 'Retrieve positions eligible for liquidation with potential rewards',
            parameters: [
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of opportunities to return (default: 50)', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'stablecoin_code', in: 'query', description: 'Filter by stablecoin code', required: false, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'position_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'eligible', type: 'boolean', example: true),
        new OA\Property(property: 'reward', type: 'integer', example: 5000),
        new OA\Property(property: 'penalty', type: 'integer', example: 10000),
        new OA\Property(property: 'collateral_seized', type: 'integer', example: 100000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 90000),
        new OA\Property(property: 'collateral_asset', type: 'string', example: 'USD'),
        new OA\Property(property: 'current_ratio', type: 'string', example: '1.1000'),
        new OA\Property(property: 'min_ratio', type: 'string', example: '1.2000'),
        new OA\Property(property: 'priority_score', type: 'number', example: 0.85),
        new OA\Property(property: 'health_score', type: 'number', example: 0.1),
        ])),
        ])
    )]
    public function getLiquidationOpportunities(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);
        $stablecoinCode = $request->string('stablecoin_code');

        $opportunities = $this->liquidationService->getLiquidationOpportunities($limit);

        if ($stablecoinCode) {
            $opportunities = $opportunities->where('stablecoin_code', $stablecoinCode);
        }

        return response()->json(
            [
                'data' => $opportunities->values(),
            ]
        );
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/positions/{positionUuid}/liquidate',
            operationId: 'liquidatePosition',
            tags: ['Stablecoin Operations'],
            summary: 'Liquidate a specific position',
            description: 'Execute liquidation on an eligible collateral position',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'positionUuid', in: 'path', description: 'Position UUID to liquidate', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Position liquidated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Position liquidated successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'position_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'liquidated_debt', type: 'integer', example: 90000),
        new OA\Property(property: 'liquidated_collateral', type: 'integer', example: 100000),
        new OA\Property(property: 'penalty_amount', type: 'integer', example: 10000),
        new OA\Property(property: 'liquidator_reward', type: 'integer', example: 5000),
        new OA\Property(property: 'protocol_fee', type: 'integer', example: 5000),
        new OA\Property(property: 'returned_to_owner', type: 'integer', example: 90000),
        new OA\Property(property: 'liquidator_uuid', type: 'string', format: 'uuid', example: '456e7890-e89b-12d3-a456-426614174001'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - Position not eligible for liquidation',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    #[OA\Response(
        response: 404,
        description: 'Position not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function liquidatePosition(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)->firstOrFail();
        /** @var Account|null $liquidator */
        $liquidator = Auth::user()?->account;

        try {
            $result = $this->liquidationService->liquidatePosition($position, $liquidator);

            return response()->json(
                [
                    'message' => 'Position liquidated successfully',
                    'data'    => $result,
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                400
            );
        }
    }

        #[OA\Get(
            path: '/api/v2/stablecoin-operations/positions/{positionUuid}/liquidation-reward',
            operationId: 'calculateLiquidationReward',
            tags: ['Stablecoin Operations'],
            summary: 'Calculate potential liquidation reward for a position',
            description: 'Calculate the potential reward for liquidating a specific position',
            parameters: [
            new OA\Parameter(name: 'positionUuid', in: 'path', description: 'Position UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'eligible', type: 'boolean', example: true),
        new OA\Property(property: 'reward', type: 'integer', example: 5000),
        new OA\Property(property: 'penalty', type: 'integer', example: 10000),
        new OA\Property(property: 'collateral_seized', type: 'integer', example: 100000),
        new OA\Property(property: 'debt_amount', type: 'integer', example: 90000),
        new OA\Property(property: 'collateral_asset', type: 'string', example: 'USD'),
        new OA\Property(property: 'current_ratio', type: 'string', example: '1.1000'),
        new OA\Property(property: 'min_ratio', type: 'string', example: '1.2000'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Position not found',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function calculateLiquidationReward(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)
            ->with('stablecoin')
            ->firstOrFail();

        $this->collateralService->updatePositionCollateralRatio($position);
        $reward = $this->liquidationService->calculateLiquidationReward($position);

        return response()->json(
            [
                'data' => $reward,
            ]
        );
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/stablecoins/{stablecoinCode}/simulate-liquidation',
            operationId: 'simulateMassLiquidation',
            tags: ['Stablecoin Operations'],
            summary: 'Simulate mass liquidation scenario',
            description: 'Simulate the impact of a price drop on collateral positions',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'stablecoinCode', in: 'path', description: 'Stablecoin code', required: true, schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['price_drop_percentage'], properties: [
            new OA\Property(property: 'price_drop_percentage', type: 'number', minimum: 0, maximum: 1, example: 0.2, description: 'Price drop percentage (0-1)'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'price_drop_percentage', type: 'integer', example: 20),
        new OA\Property(property: 'total_positions', type: 'integer', example: 25),
        new OA\Property(property: 'positions_liquidated', type: 'integer', example: 8),
        new OA\Property(property: 'liquidation_impact_percentage', type: 'integer', example: 32),
        new OA\Property(property: 'total_collateral_seized', type: 'integer', example: 800000),
        new OA\Property(property: 'total_debt_liquidated', type: 'integer', example: 720000),
        new OA\Property(property: 'detailed_results', type: 'array', items: new OA\Items(type: 'object')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function simulateMassLiquidation(Request $request, string $stablecoinCode): JsonResponse
    {
        $validated = $request->validate(
            [
                'price_drop_percentage' => 'required|numeric|min:0|max:1',
            ]
        );

        $simulation = $this->liquidationService->simulateMassLiquidation(
            $stablecoinCode,
            $validated['price_drop_percentage']
        );

        return response()->json(
            [
                'data' => $simulation,
            ]
        );
    }

        #[OA\Post(
            path: '/api/v2/stablecoin-operations/auto-liquidate',
            operationId: 'executeAutoLiquidation',
            tags: ['Stablecoin Operations'],
            summary: 'Execute automatic liquidation for all eligible positions',
            description: 'Automatically liquidate all positions that meet liquidation criteria',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Automatic liquidation executed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Automatic liquidation executed'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'liquidated_count', type: 'integer', example: 3),
        new OA\Property(property: 'failed_count', type: 'integer', example: 0),
        new OA\Property(property: 'total_liquidator_reward', type: 'integer', example: 15000),
        new OA\Property(property: 'total_protocol_fees', type: 'integer', example: 15000),
        new OA\Property(property: 'results', type: 'array', items: new OA\Items(type: 'object')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    public function executeAutoLiquidation(): JsonResponse
    {
        /** @var Account|null $liquidator */
        $liquidator = Auth::user()?->account;

        try {
            $result = $this->liquidationService->liquidateEligiblePositions($liquidator);

            return response()->json(
                [
                    'message' => 'Automatic liquidation executed',
                    'data'    => $result,
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ],
                400
            );
        }
    }

        #[OA\Get(
            path: '/api/v2/stablecoin-operations/positions-at-risk',
            operationId: 'getPositionsAtRisk',
            tags: ['Stablecoin Operations'],
            summary: 'Get positions at risk of liquidation',
            description: 'Retrieve positions that are close to liquidation threshold',
            parameters: [
            new OA\Parameter(name: 'buffer_ratio', in: 'query', description: 'Risk buffer ratio (default: 0.05)', required: false, schema: new OA\Schema(type: 'number', default: 0.05)),
            new OA\Parameter(name: 'stablecoin_code', in: 'query', description: 'Filter by stablecoin code', required: false, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'account_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'stablecoin_code', type: 'string', example: 'FUSD'),
        new OA\Property(property: 'collateral_ratio', type: 'string', example: '1.2500'),
        new OA\Property(property: 'health_score', type: 'number', example: 0.25),
        new OA\Property(property: 'risk_level', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high'),
        new OA\Property(property: 'recommendations', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'action', type: 'string', example: 'add_collateral'),
        new OA\Property(property: 'urgency', type: 'string', example: 'high'),
        new OA\Property(property: 'suggested_amount', type: 'integer', example: 25000),
        ])),
        ])),
        ])
    )]
    public function getPositionsAtRisk(Request $request): JsonResponse
    {
        $bufferRatio = $request->float('buffer_ratio', 0.05);
        $stablecoinCode = $request->string('stablecoin_code');

        $atRiskPositions = $this->collateralService->getPositionsAtRisk($bufferRatio);

        if ($stablecoinCode) {
            $atRiskPositions = $atRiskPositions->where('stablecoin_code', $stablecoinCode);
        }

        $enhancedPositions = $atRiskPositions->map(
            function ($position) {
                $healthScore = $this->collateralService->calculatePositionHealthScore($position);
                $recommendations = $this->collateralService->getPositionRecommendations($position);

                $riskLevel = 'low';
                if ($healthScore < 0.2) {
                    $riskLevel = 'critical';
                } elseif ($healthScore < 0.4) {
                    $riskLevel = 'high';
                } elseif ($healthScore < 0.6) {
                    $riskLevel = 'medium';
                }

                return array_merge(
                    $position->toArray(),
                    [
                        'health_score'    => $healthScore,
                        'risk_level'      => $riskLevel,
                        'recommendations' => $recommendations,
                    ]
                );
            }
        );

        return response()->json(
            [
                'data' => $enhancedPositions->values(),
            ]
        );
    }
}
