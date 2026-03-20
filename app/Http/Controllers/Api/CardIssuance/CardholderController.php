<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * REST API for cardholder management (KYC-linked card ownership).
 */
#[OA\Tag(
    name: 'Card Issuance',
    description: 'Virtual card provisioning and cardholder management'
)]
class CardholderController extends Controller
{
    /**
     * List cardholders for the authenticated user.
     */
    #[OA\Get(
        path: '/api/v1/cardholders',
        summary: 'List cardholders',
        tags: ['Card Issuance'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'List of cardholders',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean'),
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function index(Request $request): JsonResponse
    {
        $cardholders = Cardholder::where('user_id', $request->user()?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Cardholder $ch) => [
                'id'          => $ch->id,
                'first_name'  => $ch->first_name,
                'last_name'   => $ch->last_name,
                'full_name'   => $ch->getFullName(),
                'email'       => $ch->email,
                'kyc_status'  => $ch->kyc_status,
                'is_verified' => $ch->isVerified(),
                'created_at'  => $ch->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $cardholders]);
    }

    /**
     * Create a new cardholder.
     */
    #[OA\Post(
        path: '/api/v1/cardholders',
        summary: 'Create cardholder',
        tags: ['Card Issuance'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['first_name', 'last_name'], properties: [
            new OA\Property(property: 'first_name', type: 'string', example: 'John'),
            new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
            new OA\Property(property: 'phone', type: 'string'),
            new OA\Property(property: 'shipping_address_line1', type: 'string'),
            new OA\Property(property: 'shipping_city', type: 'string'),
            new OA\Property(property: 'shipping_state', type: 'string'),
            new OA\Property(property: 'shipping_postal_code', type: 'string'),
            new OA\Property(property: 'shipping_country', type: 'string', example: 'US'),
        ]))
    )]
    #[OA\Response(response: 201, description: 'Cardholder created')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'             => 'required|string|max:100',
            'last_name'              => 'required|string|max:100',
            'email'                  => 'nullable|email|max:255',
            'phone'                  => 'nullable|string|max:20',
            'shipping_address_line1' => 'nullable|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city'          => 'nullable|string|max:100',
            'shipping_state'         => 'nullable|string|max:100',
            'shipping_postal_code'   => 'nullable|string|max:20',
            'shipping_country'       => 'nullable|string|size:2',
        ]);

        $cardholder = Cardholder::create(array_merge($validated, [
            'user_id'    => $request->user()?->id,
            'kyc_status' => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $cardholder->id,
                'full_name'  => $cardholder->getFullName(),
                'kyc_status' => $cardholder->kyc_status,
                'created_at' => $cardholder->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get a cardholder by ID.
     */
    #[OA\Get(
        path: '/api/v1/cardholders/{id}',
        summary: 'Get cardholder details',
        tags: ['Card Issuance'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    )]
    #[OA\Response(response: 200, description: 'Cardholder details')]
    public function show(Request $request, string $id): JsonResponse
    {
        $cardholder = Cardholder::where('id', $id)
            ->where('user_id', $request->user()?->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $cardholder->id,
                'first_name'       => $cardholder->first_name,
                'last_name'        => $cardholder->last_name,
                'full_name'        => $cardholder->getFullName(),
                'email'            => $cardholder->email,
                'phone'            => $cardholder->phone,
                'kyc_status'       => $cardholder->kyc_status,
                'is_verified'      => $cardholder->isVerified(),
                'shipping_address' => $cardholder->getShippingAddress(),
                'card_count'       => $cardholder->cards()->count(),
                'verified_at'      => $cardholder->verified_at?->toIso8601String(),
                'created_at'       => $cardholder->created_at?->toIso8601String(),
            ],
        ]);
    }
}
