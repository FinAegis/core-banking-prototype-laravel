<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wallet;

use App\Domain\Wallet\Services\WalletTransferService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\ResolveNameRequest;
use App\Http\Requests\Wallet\ValidateAddressRequest;
use App\Http\Requests\Wallet\WalletQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WalletTransferController extends Controller
{
    public function __construct(
        private readonly WalletTransferService $transferService,
    ) {
    }

    /**
     * Validate a blockchain address.
     *
     * @OA\Get(
     *     path="/api/v1/wallet/validate-address",
     *     operationId="walletValidateAddress",
     *     summary="Validate a blockchain address",
     *     description="Validates a blockchain address format for the specified network.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="address",
     *         in="query",
     *         required=true,
     *         description="Blockchain address to validate (20-128 characters)",
     *         @OA\Schema(type="string", minLength=20, maxLength=128, example="0x1234567890abcdef1234567890abcdef12345678")
     *     ),
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=true,
     *         description="Target network for validation",
     *         @OA\Schema(type="string", example="POLYGON")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Validation result with address details")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function validateAddress(ValidateAddressRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->transferService->validateAddress(
            $validated['address'],
            $validated['network'],
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * Resolve an ENS/SNS name to a blockchain address.
     *
     * @OA\Post(
     *     path="/api/v1/wallet/resolve-name",
     *     operationId="walletResolveName",
     *     summary="Resolve an ENS/SNS name to an address",
     *     description="Resolves a domain name (e.g. vitalik.eth, alice.sol) to a blockchain address on the specified network.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "network"},
     *             @OA\Property(property="name", type="string", example="vitalik.eth", description="Domain name to resolve (e.g. alice.sol, vitalik.eth)"),
     *             @OA\Property(property="network", type="string", example="ETHEREUM", description="Target network")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Name resolved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Resolution result with resolved address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Name could not be resolved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="data", type="object", description="Resolution result indicating failure")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function resolveName(ResolveNameRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->transferService->resolveName(
            $validated['name'],
            $validated['network'],
        );

        $statusCode = $result['resolved'] ? 200 : 422;

        return response()->json([
            'success' => $result['resolved'],
            'data'    => $result,
        ], $statusCode);
    }

    /**
     * Get a fee quote for a wallet-to-wallet transfer.
     *
     * @OA\Post(
     *     path="/api/v1/wallet/quote",
     *     operationId="walletQuote",
     *     summary="Get a fee quote for a transfer",
     *     description="Returns a fee quote for a wallet-to-wallet transfer on the specified network and asset.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"network", "asset", "amount"},
     *             @OA\Property(property="network", type="string", example="POLYGON", description="Target network"),
     *             @OA\Property(property="asset", type="string", example="USDC", description="Asset/token symbol"),
     *             @OA\Property(property="amount", type="number", example=100.50, description="Transfer amount (must be > 0, max 1000000)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fee quote",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Transfer quote with fee breakdown")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Quote failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="QUOTE_FAILED"),
     *                 @OA\Property(property="message", type="string", example="Unable to generate quote.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function quote(WalletQuoteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->transferService->getTransferQuote(
                $validated['network'],
                $validated['asset'],
                $validated['amount'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'QUOTE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a transaction quote including recipient address validation.
     *
     * @OA\Post(
     *     path="/api/v1/wallet/transactions/quote",
     *     operationId="walletTransactionQuote",
     *     summary="Get a transaction quote with recipient validation",
     *     description="Returns a fee quote for a transfer to a specific recipient address, validating the recipient on the specified network.",
     *     tags={"Mobile Wallet"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to", "network", "asset", "amount"},
     *             @OA\Property(property="to", type="string", example="0x1234...", description="Recipient address"),
     *             @OA\Property(property="network", type="string", example="POLYGON", description="Target network"),
     *             @OA\Property(property="asset", type="string", example="USDC", description="Asset/token symbol"),
     *             @OA\Property(property="amount", type="number", example=100.50, description="Transfer amount")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction quote",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quote_id", type="string"),
     *                 @OA\Property(property="to", type="string"),
     *                 @OA\Property(property="amount", type="string"),
     *                 @OA\Property(property="asset", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="fee", type="string"),
     *                 @OA\Property(property="total", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid recipient or quote failed"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function transactionQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to'      => 'required|string|max:128',
            'network' => 'required|string',
            'asset'   => 'required|string',
            'amount'  => 'required|numeric|gt:0|max:1000000',
        ]);

        try {
            $quote = $this->transferService->getTransactionQuote(
                $validated['to'],
                $validated['network'],
                $validated['asset'],
                (string) $validated['amount'],
            );

            return response()->json([
                'success' => true,
                'data'    => $quote,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'QUOTE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
