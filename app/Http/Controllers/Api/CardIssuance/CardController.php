<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Services\CardProvisioningService;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
        private readonly ?BiometricJWTService $biometricJWTService = null,
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

        $cards = $this->provisioningService->listUserCards((string) $user->id);

        return response()->json([
            'success' => true,
            'data'    => array_map(fn ($card) => [
                'card_token' => $card->cardToken,
                'last4'      => $card->last4,
                'network'    => $card->network->value,
                'status'     => $card->status->value,
                'label'      => $card->label,
                'expires_at' => $card->expiresAt->format('Y-m-d'),
            ], $cards),
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
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="network", type="string", enum={"visa", "mastercard"}, example="visa"),
     *             @OA\Property(property="label", type="string", example="My Travel Card", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="card_token", type="string"),
     *                 @OA\Property(property="last4", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="label", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Card creation failed"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cardholder_name' => 'nullable|string|max:255',
            'currency'        => 'nullable|string|max:10',
            'network'         => 'nullable|string|in:visa,mastercard',
            'label'           => 'nullable|string|max:50',
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
            $network = isset($validated['network']) ? CardNetwork::from($validated['network']) : null;

            $card = $this->provisioningService->createCard(
                userId: (string) $user->id,
                cardholderName: $cardholderName,
                network: $network,
                label: $validated['label'] ?? null,
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
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=20, maximum=100)),
     *     @OA\Parameter(name="cursor", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Card transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="merchant", type="string"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="next_cursor", type="string", nullable=true),
     *                 @OA\Property(property="has_more", type="boolean"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function transactions(Request $request, string $cardId): JsonResponse
    {
        $limit = min((int) $request->query('limit', '20'), 100);
        $cursor = $request->query('cursor');

        $result = $this->provisioningService->getTransactions(
            $cardId,
            $limit,
            is_string($cursor) ? $cursor : null,
        );

        $transactions = array_map(
            fn ($tx) => $tx->toArray(),
            $result['transactions'],
        );

        return response()->json([
            'success'    => true,
            'data'       => $transactions,
            'pagination' => [
                'next_cursor' => $result['next_cursor'],
                'has_more'    => $result['next_cursor'] !== null,
                'total'       => count($transactions),
            ],
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
     * Cancel a virtual card (requires biometric authentication).
     *
     * @OA\Delete(
     *     path="/api/v1/cards/{cardId}",
     *     summary="Cancel a virtual card permanently (requires biometric auth)",
     *     tags={"Card Issuance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="cardId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"biometric_token"},
     *             @OA\Property(property="biometric_token", type="string", minLength=32, maxLength=2048, description="Biometric JWT or demo HMAC token"),
     *             @OA\Property(property="reason", type="string", example="User requested cancellation")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Card cancelled"),
     *     @OA\Response(response=403, description="Biometric verification failed"),
     *     @OA\Response(response=404, description="Card not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=503, description="Biometric service unavailable")
     * )
     */
    public function cancel(Request $request, string $cardId): JsonResponse
    {
        $validated = $request->validate([
            'biometric_token' => 'required|string|min:32|max:2048',
            'reason'          => 'nullable|string|max:500',
        ]);

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

        // Verify biometric token
        if (! $this->verifyBiometricToken($user, $validated['biometric_token'])) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_BIOMETRIC_001',
                    'message' => 'Biometric verification failed',
                ],
            ], 403);
        }

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

    /**
     * Verify biometric token using BiometricJWTService or demo fallback.
     *
     * @param \App\Models\User $user
     */
    private function verifyBiometricToken($user, string $biometricToken): bool
    {
        if (empty($biometricToken)) {
            return false;
        }

        // Production mode: Use JWT verification
        if ($this->biometricJWTService !== null) {
            return $this->biometricJWTService->verifyToken($user, $biometricToken);
        }

        // Reject in production environment without JWT service
        if (app()->environment('production')) {
            Log::critical('BiometricJWTService not configured in production for card cancellation', [
                'user_id' => $user->id,
            ]);

            throw new RuntimeException('Biometric verification unavailable.');
        }

        // Demo mode: Verify using HMAC signature with app key
        Log::warning('Using demo biometric verification for card cancellation', [
            'user_id'     => $user->id,
            'environment' => app()->environment(),
        ]);

        $expectedToken = hash_hmac('sha256', 'demo_biometric:' . $user->id, (string) config('app.key'));

        return hash_equals($expectedToken, $biometricToken);
    }
}
