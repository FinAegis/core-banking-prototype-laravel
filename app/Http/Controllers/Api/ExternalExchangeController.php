<?php

namespace App\Http\Controllers\Api;

use App\Domain\Exchange\Services\ExternalExchangeConnectorRegistry;
use App\Domain\Exchange\Services\ExternalLiquidityService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="External Exchange",
 *     description="External exchange integration endpoints"
 * )
 */
class ExternalExchangeController extends Controller
{
    public function __construct(
        private readonly ExternalExchangeConnectorRegistry $connectorRegistry,
        private readonly ExternalLiquidityService $liquidityService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchange/connectors",
     *     tags={"External Exchange"},
     *     summary="Get available external exchange connectors",
     *
     * @OA\Response(
     *         response=200,
     *         description="List of available connectors",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="connectors",   type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="name",         type="string", example="binance"),
     * @OA\Property(property="display_name", type="string", example="Binance"),
     * @OA\Property(property="available",    type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function connectors(): JsonResponse
    {
        $connectors = $this->connectorRegistry->all()->map(
            function ($connector, $name) {
                return [
                    'name'         => $name,
                    'display_name' => $connector->getName(),
                    'available'    => $connector->isAvailable(),
                ];
            }
        );

        return response()->json(
            [
                'connectors' => $connectors->values(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchange/ticker/{base}/{quote}",
     *     tags={"External Exchange"},
     *     summary="Get aggregated ticker data from external exchanges",
     *
     * @OA\Parameter(
     *         name="base",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string", example="BTC")
     *     ),
     *
     * @OA\Parameter(
     *         name="quote",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string", example="EUR")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Aggregated ticker data"
     *     )
     * )
     */
    public function ticker(string $base, string $quote): JsonResponse
    {
        $tickers = [];

        foreach ($this->connectorRegistry->available() as $name => $connector) {
            try {
                $ticker = $connector->getTicker($base, $quote);
                $tickers[$name] = $ticker->toArray();
            } catch (Exception $e) {
                // Log but continue with other exchanges
                Log::warning("Failed to get ticker from {$name}", ['error' => $e->getMessage()]);
            }
        }

        $bestBid = $this->connectorRegistry->getBestBid($base, $quote);
        $bestAsk = $this->connectorRegistry->getBestAsk($base, $quote);

        return response()->json(
            [
                'pair'      => "{$base}/{$quote}",
                'tickers'   => $tickers,
                'best_bid'  => $bestBid,
                'best_ask'  => $bestAsk,
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchange/orderbook/{base}/{quote}",
     *     tags={"External Exchange"},
     *     summary="Get aggregated order book from external exchanges",
     *
     * @OA\Parameter(
     *         name="base",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",  example="BTC")
     *     ),
     *
     * @OA\Parameter(
     *         name="quote",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string",  example="EUR")
     *     ),
     *
     * @OA\Parameter(
     *         name="depth",
     *         in="query",
     *         required=false,
     *
     * @OA\Schema(type="integer", default=20)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Aggregated order book"
     *     )
     * )
     */
    public function orderBook(Request $request, string $base, string $quote): JsonResponse
    {
        $depth = (int) $request->get('depth', 20);
        $aggregatedBook = $this->connectorRegistry->getAggregatedOrderBook($base, $quote, $depth);

        return response()->json(
            [
                'pair'      => "{$base}/{$quote}",
                'orderbook' => $aggregatedBook,
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/external-exchange/arbitrage/{base}/{quote}",
     *     tags={"External Exchange"},
     *     summary="Check arbitrage opportunities",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="base",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string", example="BTC")
     *     ),
     *
     * @OA\Parameter(
     *         name="quote",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string", example="EUR")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Arbitrage opportunities"
     *     )
     * )
     */
    public function arbitrage(string $base, string $quote): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $opportunities = $this->liquidityService->findArbitrageOpportunities($base, $quote);

        return response()->json(
            [
                'pair'          => "{$base}/{$quote}",
                'opportunities' => $opportunities,
                'timestamp'     => now()->toIso8601String(),
            ]
        );
    }
}
