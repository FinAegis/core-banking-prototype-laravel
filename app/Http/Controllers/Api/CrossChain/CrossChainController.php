<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CrossChain;

use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\CrossChain\Services\BridgeOrchestratorService;
use App\Domain\CrossChain\Services\BridgeTransactionTracker;
use App\Domain\CrossChain\Services\CrossChainSwapService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CrossChainController extends Controller
{
    public function __construct(
        private readonly BridgeOrchestratorService $bridgeOrchestrator,
        private readonly BridgeTransactionTracker $bridgeTracker,
        private readonly CrossChainSwapService $swapService,
    ) {
    }

    /**
     * List supported chains with bridge availability.
     *
     * @OA\Get(
     *     path="/api/v1/crosschain/chains",
     *     operationId="crosschainChains",
     *     summary="List supported chains with bridge availability",
     *     description="Returns all supported blockchain networks and their bridge provider availability.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of supported chains",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="network", type="string", example="ethereum"),
     *                 @OA\Property(property="chain_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="is_evm", type="boolean", example=true),
     *                 @OA\Property(property="native_currency", type="string", example="ETH"),
     *                 @OA\Property(property="bridge_providers", type="array", @OA\Items(type="string"), example={"wormhole", "layerzero", "axelar"})
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function chains(): JsonResponse
    {
        $chains = $this->bridgeOrchestrator->getSupportedChains();

        return response()->json([
            'success' => true,
            'data'    => $chains,
        ]);
    }

    /**
     * Get bridge quotes from all providers.
     *
     * @OA\Post(
     *     path="/api/v1/crosschain/bridge/quote",
     *     operationId="crosschainBridgeQuote",
     *     summary="Get bridge quotes from all available providers",
     *     description="Fetches bridge transfer quotes from all supported bridge providers (Wormhole, LayerZero, Axelar) for the given source/destination chain, token, and amount.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_chain", "to_chain", "token", "amount"},
     *             @OA\Property(property="from_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="ethereum"),
     *             @OA\Property(property="to_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="polygon"),
     *             @OA\Property(property="token", type="string", maxLength=20, example="USDC"),
     *             @OA\Property(property="amount", type="string", example="1000.50", description="Numeric amount as string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bridge quotes retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="provider", type="string", example="wormhole"),
     *                 @OA\Property(property="from_chain", type="string", example="ethereum"),
     *                 @OA\Property(property="to_chain", type="string", example="polygon"),
     *                 @OA\Property(property="token", type="string", example="USDC"),
     *                 @OA\Property(property="amount", type="string", example="1000.50"),
     *                 @OA\Property(property="fee", type="string", example="2.50"),
     *                 @OA\Property(property="estimated_time_seconds", type="integer", example=900),
     *                 @OA\Property(property="output_amount", type="string", example="998.00")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Quote retrieval failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_CROSSCHAIN_001"),
     *                 @OA\Property(property="message", type="string", example="Bridge quote failed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bridgeQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_chain' => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'to_chain'   => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'token'      => 'required|string|max:20',
            'amount'     => 'required|string|regex:/^\d+(\.\d+)?$/',
        ]);

        try {
            $sourceChain = CrossChainNetwork::from($validated['from_chain']);
            $destChain = CrossChainNetwork::from($validated['to_chain']);

            $quotes = $this->bridgeOrchestrator->getQuotes(
                $sourceChain,
                $destChain,
                $validated['token'],
                $validated['amount'],
            );

            return response()->json([
                'success' => true,
                'data'    => array_map(fn ($q) => $q->toArray(), $quotes),
            ]);
        } catch (Throwable $e) {
            Log::error('Bridge quote failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CROSSCHAIN_001',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Initiate a bridge transfer.
     *
     * Selects the best available quote and initiates the cross-chain bridge
     * transfer from sender to recipient address.
     *
     * @OA\Post(
     *     path="/api/v1/crosschain/bridge/initiate",
     *     operationId="crosschainBridgeInitiate",
     *     summary="Initiate a cross-chain bridge transfer",
     *     description="Selects the best available bridge quote and initiates the cross-chain transfer from sender to recipient address.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_chain", "to_chain", "token", "amount", "sender_address", "recipient_address"},
     *             @OA\Property(property="from_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="ethereum"),
     *             @OA\Property(property="to_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="arbitrum"),
     *             @OA\Property(property="token", type="string", maxLength=20, example="USDC"),
     *             @OA\Property(property="amount", type="string", example="500.00", description="Numeric amount as string"),
     *             @OA\Property(property="sender_address", type="string", maxLength=100, example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e"),
     *             @OA\Property(property="recipient_address", type="string", maxLength=100, example="0x8ba1f109551bD432803012645Ac136ddd64DBA72")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bridge transfer initiated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transaction_id", type="string", example="bridge-tx-abc123"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="quote", type="object",
     *                     @OA\Property(property="provider", type="string", example="wormhole"),
     *                     @OA\Property(property="from_chain", type="string", example="ethereum"),
     *                     @OA\Property(property="to_chain", type="string", example="arbitrum"),
     *                     @OA\Property(property="token", type="string", example="USDC"),
     *                     @OA\Property(property="amount", type="string", example="500.00"),
     *                     @OA\Property(property="fee", type="string", example="1.25"),
     *                     @OA\Property(property="estimated_time_seconds", type="integer", example=600)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bridge initiation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_CROSSCHAIN_002"),
     *                 @OA\Property(property="message", type="string", example="Bridge initiation failed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bridgeInitiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_chain'        => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'to_chain'          => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'token'             => 'required|string|max:20',
            'amount'            => 'required|string|regex:/^\d+(\.\d+)?$/',
            'sender_address'    => 'required|string|max:100',
            'recipient_address' => 'required|string|max:100',
        ]);

        try {
            $sourceChain = CrossChainNetwork::from($validated['from_chain']);
            $destChain = CrossChainNetwork::from($validated['to_chain']);

            $quote = $this->bridgeOrchestrator->getBestQuote(
                $sourceChain,
                $destChain,
                $validated['token'],
                $validated['amount'],
            );

            $result = $this->bridgeOrchestrator->initiateBridge(
                $quote,
                $validated['sender_address'],
                $validated['recipient_address'],
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id' => $result['transaction_id'],
                    'status'         => $result['status']->value,
                    'quote'          => $quote->toArray(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Bridge initiation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CROSSCHAIN_002',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get bridge transaction status.
     *
     * @OA\Get(
     *     path="/api/v1/crosschain/bridge/{id}/status",
     *     operationId="crosschainBridgeStatus",
     *     summary="Get bridge transaction status",
     *     description="Retrieves the current status and details of a previously initiated bridge transaction.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bridge transaction ID",
     *         @OA\Schema(type="string", example="bridge-tx-abc123")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bridge transaction status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transaction_id", type="string", example="bridge-tx-abc123"),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="from_chain", type="string", example="ethereum"),
     *                 @OA\Property(property="to_chain", type="string", example="polygon"),
     *                 @OA\Property(property="token", type="string", example="USDC"),
     *                 @OA\Property(property="amount", type="string", example="1000.50"),
     *                 @OA\Property(property="provider", type="string", example="wormhole"),
     *                 @OA\Property(property="source_tx_hash", type="string", nullable=true),
     *                 @OA\Property(property="destination_tx_hash", type="string", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bridge transaction not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_CROSSCHAIN_003"),
     *                 @OA\Property(property="message", type="string", example="Bridge transaction not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function bridgeStatus(string $id): JsonResponse
    {
        $transaction = $this->bridgeTracker->getTransaction($id);

        if ($transaction === null) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CROSSCHAIN_003',
                    'message' => 'Bridge transaction not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $transaction,
        ]);
    }

    /**
     * Get a cross-chain swap quote (bridge + swap).
     *
     * @OA\Post(
     *     path="/api/v1/crosschain/swap/quote",
     *     operationId="crosschainSwapQuote",
     *     summary="Get a cross-chain swap quote",
     *     description="Gets a combined bridge + swap quote for swapping tokens across different chains. The quote includes bridge fees, swap fees, estimated output, and execution time.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_chain", "to_chain", "from_token", "to_token", "amount"},
     *             @OA\Property(property="from_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="ethereum"),
     *             @OA\Property(property="to_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="polygon"),
     *             @OA\Property(property="from_token", type="string", maxLength=20, example="ETH"),
     *             @OA\Property(property="to_token", type="string", maxLength=20, example="USDC"),
     *             @OA\Property(property="amount", type="string", example="1.5", description="Numeric amount as string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cross-chain swap quote",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="from_chain", type="string", example="ethereum"),
     *                 @OA\Property(property="to_chain", type="string", example="polygon"),
     *                 @OA\Property(property="from_token", type="string", example="ETH"),
     *                 @OA\Property(property="to_token", type="string", example="USDC"),
     *                 @OA\Property(property="input_amount", type="string", example="1.5"),
     *                 @OA\Property(property="output_amount", type="string", example="2950.00"),
     *                 @OA\Property(property="bridge_fee", type="string", example="3.50"),
     *                 @OA\Property(property="swap_fee", type="string", example="1.25"),
     *                 @OA\Property(property="estimated_time_seconds", type="integer", example=1200),
     *                 @OA\Property(property="route", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Swap quote failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_CROSSCHAIN_004"),
     *                 @OA\Property(property="message", type="string", example="Cross-chain swap quote failed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function swapQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_chain' => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'to_chain'   => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'from_token' => 'required|string|max:20',
            'to_token'   => 'required|string|max:20',
            'amount'     => 'required|string|regex:/^\d+(\.\d+)?$/',
        ]);

        try {
            $sourceChain = CrossChainNetwork::from($validated['from_chain']);
            $destChain = CrossChainNetwork::from($validated['to_chain']);

            $quote = $this->swapService->getQuote(
                $sourceChain,
                $destChain,
                $validated['from_token'],
                $validated['to_token'],
                $validated['amount'],
            );

            return response()->json([
                'success' => true,
                'data'    => $quote->toArray(),
            ]);
        } catch (Throwable $e) {
            Log::error('Cross-chain swap quote failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CROSSCHAIN_004',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Execute a cross-chain swap.
     *
     * @OA\Post(
     *     path="/api/v1/crosschain/swap/execute",
     *     operationId="crosschainSwapExecute",
     *     summary="Execute a cross-chain swap",
     *     description="Executes a cross-chain token swap by first obtaining the best quote and then executing the bridge + swap operations for the given wallet address.",
     *     tags={"CrossChain"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_chain", "to_chain", "from_token", "to_token", "amount", "wallet_address"},
     *             @OA\Property(property="from_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="ethereum"),
     *             @OA\Property(property="to_chain", type="string", enum={"ethereum", "polygon", "bsc", "bitcoin", "solana", "tron", "arbitrum", "optimism", "base"}, example="polygon"),
     *             @OA\Property(property="from_token", type="string", maxLength=20, example="ETH"),
     *             @OA\Property(property="to_token", type="string", maxLength=20, example="USDC"),
     *             @OA\Property(property="amount", type="string", example="1.5", description="Numeric amount as string"),
     *             @OA\Property(property="wallet_address", type="string", maxLength=100, example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cross-chain swap executed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="swap_id", type="string", example="swap-xyz789"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="bridge_tx_id", type="string", example="bridge-tx-abc123"),
     *                 @OA\Property(property="from_chain", type="string", example="ethereum"),
     *                 @OA\Property(property="to_chain", type="string", example="polygon"),
     *                 @OA\Property(property="from_token", type="string", example="ETH"),
     *                 @OA\Property(property="to_token", type="string", example="USDC"),
     *                 @OA\Property(property="input_amount", type="string", example="1.5"),
     *                 @OA\Property(property="expected_output", type="string", example="2950.00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Swap execution failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_CROSSCHAIN_005"),
     *                 @OA\Property(property="message", type="string", example="Cross-chain swap execution failed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function swapExecute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_chain'     => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'to_chain'       => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'from_token'     => 'required|string|max:20',
            'to_token'       => 'required|string|max:20',
            'amount'         => 'required|string|regex:/^\d+(\.\d+)?$/',
            'wallet_address' => 'required|string|max:100',
        ]);

        try {
            $sourceChain = CrossChainNetwork::from($validated['from_chain']);
            $destChain = CrossChainNetwork::from($validated['to_chain']);

            $quote = $this->swapService->getQuote(
                $sourceChain,
                $destChain,
                $validated['from_token'],
                $validated['to_token'],
                $validated['amount'],
            );

            $result = $this->swapService->executeSwap($quote, $validated['wallet_address']);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Cross-chain swap execution failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CROSSCHAIN_005',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
