<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Domain\Privacy\Models\RailgunWallet;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * Non-custodial RAILGUN wallet registration (Phase 1).
 *
 * In the non-custodial model the device creates the 0zk wallet locally (seed +
 * spending key + viewing key never leave the device) and registers only its
 * PUBLIC 0zk address here — for activity-feed mirroring, push notifications and
 * scan hints. The backend stores no seed: encrypted_mnemonic stays null.
 */
class RailgunWalletRegistrationController extends Controller
{
    #[OA\Post(
        path: '/api/v1/privacy/wallet/register',
        operationId: 'registerRailgunWallet',
        summary: 'Register a non-custodial RAILGUN 0zk address',
        description: 'The device creates the RAILGUN wallet locally and registers only its public 0zk address. No seed material is sent or stored.',
        security: [['sanctum' => []]],
        tags: ['Privacy'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['railgun_address', 'network'],
                properties: [
                    new OA\Property(property: 'railgun_address', type: 'string', example: '0zk1q...'),
                    new OA\Property(property: 'network', type: 'string', enum: ['ethereum', 'polygon', 'arbitrum', 'bsc']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Registered'),
            new OA\Response(response: 409, description: 'Address already registered to another user'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentication required.']], 401);
        }

        $validated = $request->validate([
            'railgun_address' => ['required', 'string', 'min:20', 'max:128', 'regex:/^0zk1[a-z0-9]+$/'],
            'network'         => ['required', 'string', Rule::in(config('privacy.railgun.networks', ['ethereum', 'polygon', 'arbitrum', 'bsc']))],
        ]);

        $address = $validated['railgun_address'];

        $existing = RailgunWallet::where('railgun_address', $address)->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ADDRESS_ALREADY_REGISTERED',
                    'message' => 'This RAILGUN address is already registered to another account.',
                ],
            ], 409);
        }

        // encrypted_mnemonic is intentionally NOT set — non-custodial wallets
        // hold the seed on-device only.
        $wallet = RailgunWallet::updateOrCreate(
            ['user_id' => $user->id, 'railgun_address' => $address],
            ['network' => $validated['network'], 'status' => RailgunWallet::STATUS_ACTIVE],
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'railgun_address' => $wallet->railgun_address,
                'network'         => $wallet->network,
                'status'          => $wallet->status,
                'custodial'       => false,
                'registered_at'   => $wallet->updated_at->toIso8601String(),
            ],
        ]);
    }
}
