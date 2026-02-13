<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="External Exchanges",
 *     description="External exchange connectors, tickers, and arbitrage endpoints (stub)"
 * )
 */
class ExternalExchangeStubController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external-exchanges/connectors",
     *     operationId="externalExchangesConnectors",
     *     tags={"External Exchanges"},
     *     summary="List exchange connectors",
     *     description="Returns available external exchange connectors",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function connectors(): JsonResponse
    {
        return response()->json(
            [
                'status' => 'success',
                'data'   => [
                    [
                        'name'            => 'binance',
                        'enabled'         => true,
                        'supported_pairs' => ['BTC/EUR', 'ETH/EUR', 'BTC/USD'],
                        'features'        => ['spot', 'orderbook', 'ticker'],
                    ],
                    [
                        'name'            => 'kraken',
                        'enabled'         => true,
                        'supported_pairs' => ['BTC/EUR', 'ETH/EUR'],
                        'features'        => ['spot', 'orderbook', 'ticker'],
                    ],
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchanges/ticker/{base}/{quote}",
     *     operationId="externalExchangesTicker",
     *     tags={"External Exchanges"},
     *     summary="Get ticker data",
     *     description="Returns current ticker data for a trading pair",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="base", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="quote", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function ticker(Request $request, $base, $quote): JsonResponse
    {
        $request->merge(['base' => $base, 'quote' => $quote]);
        $request->validate(
            [
                'base'  => 'required|in:BTC,ETH,EUR,USD',
                'quote' => 'required|in:BTC,ETH,EUR,USD',
            ]
        );

        return response()->json(
            [
                'status' => 'success',
                'data'   => [
                    'base'       => $base,
                    'quote'      => $quote,
                    'bid'        => 45000.00,
                    'ask'        => 45100.00,
                    'last'       => 45050.00,
                    'volume_24h' => 1234.56,
                    'change_24h' => 2.5,
                    'timestamp'  => now()->timestamp,
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchanges/order-book/{base}/{quote}",
     *     operationId="externalExchangesOrderBook",
     *     tags={"External Exchanges"},
     *     summary="Get order book",
     *     description="Returns order book for a trading pair",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="base", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="quote", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function orderBook(Request $request, $base, $quote): JsonResponse
    {
        $request->merge(['base' => $base, 'quote' => $quote]);
        $request->validate(
            [
                'base'  => 'required|in:BTC,ETH,EUR,USD',
                'quote' => 'required|in:BTC,ETH,EUR,USD',
            ]
        );

        return response()->json(
            [
                'status' => 'success',
                'data'   => [
                    'base'  => $base,
                    'quote' => $quote,
                    'bids'  => [
                        ['price' => 45000.00, 'amount' => 0.5],
                        ['price' => 44950.00, 'amount' => 1.0],
                    ],
                    'asks' => [
                        ['price' => 45100.00, 'amount' => 0.3],
                        ['price' => 45150.00, 'amount' => 0.8],
                    ],
                    'timestamp' => now()->timestamp,
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchanges/arbitrage/{base}/{quote}",
     *     operationId="externalExchangesArbitrage",
     *     tags={"External Exchanges"},
     *     summary="Get arbitrage opportunities",
     *     description="Returns arbitrage opportunities for a trading pair",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="base", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="quote", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function arbitrage(Request $request, $base, $quote): JsonResponse
    {
        $request->merge(['base' => $base, 'quote' => $quote]);
        $request->validate(
            [
                'base'  => 'required|in:BTC,ETH,EUR,USD',
                'quote' => 'required|in:BTC,ETH,EUR,USD',
            ]
        );

        return response()->json(
            [
                'status' => 'success',
                'data'   => [
                    'base'          => $base,
                    'quote'         => $quote,
                    'opportunities' => [
                        [
                            'buy_exchange'      => 'kraken',
                            'sell_exchange'     => 'binance',
                            'buy_price'         => 45000.00,
                            'sell_price'        => 45100.00,
                            'spread'            => 100.00,
                            'spread_percentage' => 0.22,
                            'potential_profit'  => 98.00,
                        ],
                    ],
                    'best_opportunity' => [
                        'buy_exchange'      => 'kraken',
                        'sell_exchange'     => 'binance',
                        'spread_percentage' => 0.22,
                    ],
                    'timestamp' => now()->timestamp,
                ],
            ]
        );
    }
}
