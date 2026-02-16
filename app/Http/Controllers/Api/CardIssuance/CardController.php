<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @OA\Tag(
 *     name="Card Issuance",
 *     description="Virtual card provisioning for Apple Pay / Google Pay"
 * )
 */
class CardController extends Controller
{
    public function __construct(
        private readonly CardProvisioningService $provisioningService,
    ) {
    }

    /**
     * Provision a new virtual card for Apple Pay / Google Pay.
     *
     * @OA\Post(
     *     path="/api/v1/cards/provision",
     *     summary="Provision virtual card for mobile wallet",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "wallet_type"},
     *             @OA\Property(property="device_id", type="string", example="device_abc123"),
     *             @OA\Property(property="wallet_type", type="string", enum={"apple_pay", "google_pay"}),
     *             @OA\Property(property="cardholder_name", type="string", example="John Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Provisioning data for mobile wallet",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="card_id", type="string"),
     *                 @OA\Property(property="encrypted_pass_data", type="string"),
     *                 @OA\Property(property="activation_data", type="string"),
     *                 @OA\Property(property="ephemeral_public_key", type="string"),
     *                 @OA\Property(property="certificate_chain", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function provision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'       => 'required|string|max:255',
            'wallet_type'     => 'required|string|in:apple_pay,google_pay',
            'cardholder_name' => 'nullable|string|max:255',
        ]);

        try {
            $user = $request->user();
            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'ERR_AUTH_001',
                        'message' => 'Authentication required',
                    ],
                ], 401);
            }

            $walletType = WalletType::from($validated['wallet_type']);
            $cardholderName = $validated['cardholder_name'] ?? $user->name ?? 'FinAegis User';

            // Create card if user doesn't have one
            $card = $this->provisioningService->createCard(
                userId: (string) $user->id,
                cardholderName: $cardholderName,
            );

            // Get provisioning data for the wallet
            $provisioningData = $this->provisioningService->getProvisioningData(
                userId: (string) $user->id,
                cardToken: $card->cardToken,
                walletType: $walletType,
                deviceId: $validated['device_id'],
            );

            return response()->json([
                'success' => true,
                'data'    => $provisioningData->toArray(),
            ]);
        } catch (Throwable $e) {
            Log::error('Card provisioning failed', [
                'error'   => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_001',
                    'message' => 'Failed to provision card: ' . $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get user's virtual cards.
     *
     * @OA\Get(
     *     path="/api/v1/cards",
     *     summary="List user's virtual cards",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of cards",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="card_id", type="string"),
     *                 @OA\Property(property="last4", type="string", example="4242"),
     *                 @OA\Property(property="network", type="string", example="visa"),
     *                 @OA\Property(property="status", type="string", example="active")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Demo implementation - return empty list
        // In production, query from database
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }

    /**
     * Create a new virtual card.
     *
     * @OA\Post(
     *     path="/api/v1/cards",
     *     summary="Create a new virtual card",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="cardholder_name", type="string", example="John Doe"),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="card_id", type="string"),
     *                 @OA\Property(property="last4", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Card creation failed"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cardholder_name' => 'nullable|string|max:255',
            'currency'        => 'nullable|string|max:10',
        ]);

        try {
            $user = $request->user();
            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'ERR_AUTH_001',
                        'message' => 'Authentication required',
                    ],
                ], 401);
            }

            $cardholderName = $validated['cardholder_name'] ?? $user->name ?? 'FinAegis User';

            $card = $this->provisioningService->createCard(
                userId: (string) $user->id,
                cardholderName: $cardholderName,
            );

            $data = $card->toArray();
            $data['currency'] = $validated['currency'] ?? 'USD';

            return response()->json([
                'success' => true,
                'data'    => $data,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Card creation failed', [
                'error'   => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_005',
                    'message' => 'Failed to create card: ' . $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a specific card.
     *
     * @OA\Get(
     *     path="/api/v1/cards/{cardId}",
     *     summary="Get a specific virtual card",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Card details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function show(Request $request, string $cardId): JsonResponse
    {
        try {
            $card = $this->provisioningService->getCard($cardId);

            if ($card === null) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'ERR_CARD_NOT_FOUND',
                        'message' => 'Card not found',
                    ],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $card->toArray(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_006',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get transactions for a card.
     *
     * @OA\Get(
     *     path="/api/v1/cards/{cardId}/transactions",
     *     summary="Get transactions for a virtual card",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Card transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function transactions(Request $request, string $cardId): JsonResponse
    {
        // Demo implementation — return empty list
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }

    /**
     * Freeze a virtual card.
     *
     * @OA\Post(
     *     path="/api/v1/cards/{cardId}/freeze",
     *     summary="Freeze a virtual card",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Card frozen"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function freeze(Request $request, string $cardId): JsonResponse
    {
        try {
            $result = $this->provisioningService->freezeCard($cardId);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Card frozen successfully' : 'Failed to freeze card',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_002',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Unfreeze a virtual card.
     *
     * @OA\Delete(
     *     path="/api/v1/cards/{cardId}/freeze",
     *     summary="Unfreeze a virtual card",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Card unfrozen"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function unfreeze(Request $request, string $cardId): JsonResponse
    {
        try {
            $result = $this->provisioningService->unfreezeCard($cardId);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Card unfrozen successfully' : 'Failed to unfreeze card',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_003',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Cancel a virtual card.
     *
     * @OA\Delete(
     *     path="/api/v1/cards/{cardId}",
     *     summary="Cancel a virtual card permanently",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="User requested cancellation")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Card cancelled"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function cancel(Request $request, string $cardId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->provisioningService->cancelCard(
                $cardId,
                $validated['reason'] ?? 'User requested cancellation'
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Card cancelled successfully' : 'Failed to cancel card',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_CARD_004',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
