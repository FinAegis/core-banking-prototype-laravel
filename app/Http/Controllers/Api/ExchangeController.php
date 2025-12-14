<?php

namespace App\Http\Controllers\Api;

use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use App\Domain\Exchange\Services\ExchangeService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExchangeController extends Controller
{
    private ExchangeService $exchangeService;

    public function __construct(ExchangeService $exchangeService)
    {
        $this->exchangeService = $exchangeService;
    }

    /**
     * @OA\Post(
     *     path="/api/exchange/orders",
     *     tags={"Exchange"},
     *     summary="Place a new order",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"type", "order_type", "base_currency", "quote_currency", "amount"},
     *
     * @OA\Property(property="type",           type="string", enum={"buy", "sell"}),
     * @OA\Property(property="order_type",     type="string", enum={"market", "limit"}),
     * @OA\Property(property="base_currency",  type="string", example="BTC"),
     * @OA\Property(property="quote_currency", type="string", example="EUR"),
     * @OA\Property(property="amount",         type="string", example="0.01"),
     * @OA\Property(property="price",          type="string", example="50000", description="Required for limit orders"),
     * @OA\Property(property="stop_price",     type="string", example="49000", description="For stop orders")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Order placed successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="success",        type="boolean", example=true),
     * @OA\Property(property="order_id",       type="string"),
     * @OA\Property(property="message",        type="string")
     *         )
     *     )
     * )
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'type'           => ['required', Rule::in(['buy', 'sell'])],
                'order_type'     => ['required', Rule::in(['market', 'limit'])],
                'base_currency'  => ['required', 'string', 'size:3'],
                'quote_currency' => ['required', 'string', 'size:3'],
                'amount'         => ['required', 'numeric', 'gt:0'],
                'price'          => ['required_if:order_type,limit', 'nullable', 'numeric', 'gt:0'],
                'stop_price'     => ['nullable', 'numeric', 'gt:0'],
            ]
        );

        $account = Auth::user()->account;

        if (! $account) {
            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Account not found. Please complete your account setup.',
                ],
                400
            );
        }

        try {
            $result = $this->exchangeService->placeOrder(
                accountId: $account->id,
                type: $validated['type'],
                orderType: $validated['order_type'],
                baseCurrency: $validated['base_currency'],
                quoteCurrency: $validated['quote_currency'],
                amount: $validated['amount'],
                price: $validated['price'] ?? null,
                stopPrice: $validated['stop_price'] ?? null,
                metadata: [
                    'api_version' => 'v1',
                    'user_id'     => Auth::id(),
                ]
            );

            return response()->json($result);
        } catch (Exception $e) {
            logger()->error('Order placement failed', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Order placement failed. Please try again.',
                ],
                400
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/exchange/orders/{orderId}",
     *     tags={"Exchange"},
     *     summary="Cancel an order",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully"
     *     )
     * )
     */
    public function cancelOrder(string $orderId): JsonResponse
    {
        $account = Auth::user()->account;

        if (! $account) {
            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Account not found. Please complete your account setup.',
                ],
                400
            );
        }

        // Verify order belongs to user
        $order = Order::where('order_id', $orderId)
            ->where('account_id', $account->id)
            ->first();

        if (! $order) {
            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Order not found',
                ],
                404
            );
        }

        try {
            $result = $this->exchangeService->cancelOrder($orderId);

            return response()->json($result);
        } catch (Exception $e) {
            logger()->error('Order cancellation failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Order cancellation failed. Please try again.',
                ],
                400
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/exchange/orders",
     *     tags={"Exchange"},
     *     summary="Get user's orders",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="status",
     *         in="query",
     *
     * @OA\Schema(type="string", enum={"open", "filled", "cancelled", "all"})
     *     ),
     *
     * @OA\Parameter(
     *         name="base_currency",
     *         in="query",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="quote_currency",
     *         in="query",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="List of orders"
     *     )
     * )
     */
    public function getOrders(Request $request): JsonResponse
    {
        $account = Auth::user()->account;

        if (! $account) {
            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Account not found. Please complete your account setup.',
                ],
                400
            );
        }

        $query = Order::forAccount($account->id);

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'open') {
                $query->open();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->base_currency && $request->quote_currency) {
            $query->forPair($request->base_currency, $request->quote_currency);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * @OA\Get(
     *     path="/api/exchange/trades",
     *     tags={"Exchange"},
     *     summary="Get user's trade history",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="base_currency",
     *         in="query",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="quote_currency",
     *         in="query",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="List of trades"
     *     )
     * )
     */
    public function getTrades(Request $request): JsonResponse
    {
        $account = Auth::user()->account;

        if (! $account) {
            return response()->json(
                [
                    'success' => false,
                    'error'   => 'Account not found. Please complete your account setup.',
                ],
                400
            );
        }

        $query = Trade::forAccount($account->id);

        if ($request->base_currency && $request->quote_currency) {
            $query->forPair($request->base_currency, $request->quote_currency);
        }

        $trades = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($trades);
    }

    /**
     * @OA\Get(
     *     path="/api/exchange/orderbook/{baseCurrency}/{quoteCurrency}",
     *     tags={"Exchange"},
     *     summary="Get order book for a trading pair",
     *
     * @OA\Parameter(
     *         name="baseCurrency",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="quoteCurrency",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="depth",
     *         in="query",
     *
     * @OA\Schema(type="integer", default=20)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Order book data"
     *     )
     * )
     */
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, Request $request): JsonResponse
    {
        $depth = min($request->input('depth', 20), 100); // Max depth of 100

        $orderBook = $this->exchangeService->getOrderBook($baseCurrency, $quoteCurrency, $depth);

        return response()->json($orderBook);
    }

    /**
     * @OA\Get(
     *     path="/api/exchange/markets",
     *     tags={"Exchange"},
     *     summary="Get market data for all trading pairs",
     *
     * @OA\Response(
     *         response=200,
     *         description="Market data for all pairs"
     *     )
     * )
     */
    public function getMarkets(): JsonResponse
    {
        // Load active trading pairs from liquidity pools
        $activePools = LiquidityPool::where('is_active', true)
            ->select(['base_currency', 'quote_currency'])
            ->distinct()
            ->get();

        // Fallback to default pairs if no active pools exist
        if ($activePools->isEmpty()) {
            $tradingPairs = [
                ['base' => 'BTC', 'quote' => 'EUR'],
                ['base' => 'ETH', 'quote' => 'EUR'],
            ];
        } else {
            $tradingPairs = $activePools->map(fn ($pool) => [
                'base'  => $pool->base_currency,
                'quote' => $pool->quote_currency,
            ])->toArray();
        }

        $markets = [];
        foreach ($tradingPairs as $pair) {
            $marketData = $this->exchangeService->getMarketData($pair['base'], $pair['quote']);
            // Add base and quote currency to the data
            $marketData['base_currency'] = $pair['base'];
            $marketData['quote_currency'] = $pair['quote'];
            $markets[] = $marketData;
        }

        return response()->json(
            [
                'success' => true,
                'data'    => $markets,
            ]
        );
    }
}
