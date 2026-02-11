<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MobilePayment;

use App\Domain\MobilePayment\Enums\PaymentAsset;
use App\Domain\MobilePayment\Enums\PaymentNetwork;
use App\Domain\MobilePayment\Services\ReceiveAddressService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletReceiveController extends Controller
{
    public function __construct(
        private readonly ReceiveAddressService $receiveAddressService,
    ) {
    }

    /**
     * Get a receive address for the authenticated user.
     *
     * GET /v1/wallet/receive?asset=USDC&network=SOLANA
     *
     * @OA\Get(
     *     path="/api/v1/wallet/receive",
     *     operationId="mobilePaymentWalletReceive",
     *     summary="Get a receive address for the authenticated user",
     *     description="Returns a wallet receive address for the specified asset and network. The address can be used to receive payments from external wallets or exchanges.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="asset",
     *         in="query",
     *         required=true,
     *         description="Payment asset (currently only USDC supported)",
     *         @OA\Schema(type="string", enum={"USDC"}, example="USDC")
     *     ),
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=true,
     *         description="Payment network",
     *         @OA\Schema(type="string", enum={"SOLANA", "TRON"}, example="SOLANA")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Receive address",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="address", type="string", example="7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU"),
     *                 @OA\Property(property="network", type="string", example="SOLANA"),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="qr_uri", type="string", example="solana:7xKXtg...?spl-token=USDC")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (invalid asset or network)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="The given data was invalid.")
     *             )
     *         )
     *     )
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'asset'   => ['required', 'string', 'in:USDC'],
            'network' => ['required', 'string', 'in:SOLANA,TRON'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $network = PaymentNetwork::from($request->input('network'));
        $asset = PaymentAsset::from($request->input('asset'));

        $data = $this->receiveAddressService->getReceiveAddress(
            $user->id,
            $network,
            $asset,
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
