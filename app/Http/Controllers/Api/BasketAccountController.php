<?php

namespace App\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Basket\Services\BasketService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Basket Operations',
    description: 'Basket operations on accounts'
)]
class BasketAccountController extends Controller
{
    public function __construct(
        private readonly BasketService $basketService
    ) {
    }

        #[OA\Post(
            path: '/api/v2/accounts/{uuid}/baskets/decompose',
            operationId: 'decomposeBasket',
            tags: ['Basket Operations'],
            summary: 'Decompose basket holdings into component assets',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['basket_code', 'amount'], properties: [
            new OA\Property(property: 'basket_code', type: 'string', example: 'STABLE_BASKET'),
            new OA\Property(property: 'amount', type: 'integer', example: 10000, description: 'Amount in smallest unit'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Basket decomposed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'basket_code', type: 'string'),
        new OA\Property(property: 'basket_amount', type: 'integer'),
        new OA\Property(property: 'components', type: 'object'),
        new OA\Property(property: 'decomposed_at', type: 'string', format: 'date-time'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error or insufficient balance'
    )]
    public function decompose(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(
            [
                'basket_code' => 'required|string|exists:basket_assets,code',
                'amount'      => 'required|integer|min:1',
            ]
        );

        $account = Account::where('uuid', $uuid)->firstOrFail();

        // Verify account ownership
        if ($request->user() && $account->user_uuid !== $request->user()->uuid) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->basketService->decomposeBasket(
                $account->uuid,
                $validated['basket_code'],
                $validated['amount']
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }

        #[OA\Post(
            path: '/api/v2/accounts/{uuid}/baskets/compose',
            operationId: 'composeBasket',
            tags: ['Basket Operations'],
            summary: 'Compose component assets into a basket',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['basket_code', 'amount'], properties: [
            new OA\Property(property: 'basket_code', type: 'string', example: 'STABLE_BASKET'),
            new OA\Property(property: 'amount', type: 'integer', example: 10000, description: 'Amount of basket to create'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Basket composed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'basket_code', type: 'string'),
        new OA\Property(property: 'basket_amount', type: 'integer'),
        new OA\Property(property: 'components_used', type: 'object'),
        new OA\Property(property: 'composed_at', type: 'string', format: 'date-time'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error or insufficient component balances'
    )]
    public function compose(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate(
            [
                'basket_code' => 'required|string|exists:basket_assets,code',
                'amount'      => 'required|integer|min:1',
            ]
        );

        $account = Account::where('uuid', $uuid)->firstOrFail();

        // Verify account ownership
        if ($request->user() && $account->user_uuid !== $request->user()->uuid) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->basketService->composeBasket(
                $account->uuid,
                $validated['basket_code'],
                $validated['amount']
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }

        #[OA\Get(
            path: '/api/v2/accounts/{uuid}/baskets',
            operationId: 'getAccountBaskets',
            tags: ['Basket Operations'],
            summary: 'Get basket holdings for an account',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'Account UUID', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Account basket holdings',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'account_uuid', type: 'string'),
        new OA\Property(property: 'basket_holdings', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'basket_code', type: 'string'),
        new OA\Property(property: 'basket_name', type: 'string'),
        new OA\Property(property: 'balance', type: 'integer'),
        new OA\Property(property: 'unit_value', type: 'number'),
        new OA\Property(property: 'total_value', type: 'number'),
        ])),
        new OA\Property(property: 'total_value', type: 'number'),
        new OA\Property(property: 'currency', type: 'string'),
        ])
    )]
    public function getBasketHoldings(Request $request, string $uuid): JsonResponse
    {
        $account = Account::where('uuid', $uuid)->firstOrFail();

        // Verify account ownership
        if ($request->user() && $account->user_uuid !== $request->user()->uuid) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $holdings = $this->basketService->getBasketHoldings($account->uuid);

        return response()->json($holdings);
    }
}
