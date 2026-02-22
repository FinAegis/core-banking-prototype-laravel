<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * X402 Monetized Endpoint Management Controller.
 *
 * Manages which API endpoints require x402 payment and their pricing.
 *
 * @OA\Tag(
 *     name="X402 Endpoints",
 *     description="Manage monetized API endpoints"
 * )
 */
class X402EndpointController extends Controller
{
    /**
     * List all monetized endpoints.
     *
     * @OA\Get(
     *     path="/api/v1/x402/endpoints",
     *     summary="List monetized endpoints",
     *     tags={"X402 Endpoints"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="network", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=50, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="List of monetized endpoints"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = X402MonetizedEndpoint::query()
            ->where('team_id', $request->user()?->currentTeam?->id);

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('network')) {
            $query->where('network', $request->input('network'));
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $endpoints = $query->orderBy('path')->paginate($perPage);

        return response()->json([
            'data' => collect($endpoints->items())->map(fn ($e) => $e->toApiResponse()),
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'last_page'    => $endpoints->lastPage(),
                'per_page'     => $endpoints->perPage(),
                'total'        => $endpoints->total(),
            ],
        ]);
    }

    /**
     * Create a monetized endpoint.
     *
     * @OA\Post(
     *     path="/api/v1/x402/endpoints",
     *     summary="Register an endpoint for x402 payment",
     *     tags={"X402 Endpoints"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"method", "path", "price"},
     *             @OA\Property(property="method", type="string", enum={"GET", "POST", "PUT", "PATCH", "DELETE"}),
     *             @OA\Property(property="path", type="string", example="api/v1/ai/query"),
     *             @OA\Property(property="price", type="string", example="0.01"),
     *             @OA\Property(property="network", type="string", example="eip155:8453"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Endpoint monetized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method'      => ['required', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'path'        => ['required', 'string', 'max:500'],
            'price'       => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'network'     => ['nullable', 'string', 'regex:/^eip155:\d+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $endpoint = X402MonetizedEndpoint::create([
            'method'      => $request->input('method'),
            'path'        => $request->input('path'),
            'price'       => $request->input('price'),
            'network'     => $request->input('network', config('x402.server.default_network')),
            'description' => $request->input('description'),
            'is_active'   => $request->boolean('is_active', true),
            'team_id'     => $request->user()?->currentTeam?->id,
        ]);

        return response()->json([
            'data'    => $endpoint->toApiResponse(),
            'message' => 'Endpoint monetized successfully.',
        ], 201);
    }

    /**
     * Get a monetized endpoint.
     *
     * @OA\Get(
     *     path="/api/v1/x402/endpoints/{id}",
     *     summary="Get monetized endpoint details",
     *     tags={"X402 Endpoints"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Endpoint details"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $endpoint = X402MonetizedEndpoint::where('team_id', $request->user()?->currentTeam?->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $endpoint->toApiResponse(),
        ]);
    }

    /**
     * Update a monetized endpoint.
     *
     * @OA\Put(
     *     path="/api/v1/x402/endpoints/{id}",
     *     summary="Update monetized endpoint pricing or status",
     *     tags={"X402 Endpoints"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="price", type="string", example="0.02"),
     *             @OA\Property(property="network", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Endpoint updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $endpoint = X402MonetizedEndpoint::where('team_id', $request->user()?->currentTeam?->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'price'       => ['sometimes', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'network'     => ['sometimes', 'string', 'regex:/^eip155:\d+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $endpoint->update($validator->validated());

        return response()->json([
            'data'    => $endpoint->fresh()->toApiResponse(),
            'message' => 'Endpoint updated successfully.',
        ]);
    }

    /**
     * Delete a monetized endpoint.
     *
     * @OA\Delete(
     *     path="/api/v1/x402/endpoints/{id}",
     *     summary="Remove monetization from an endpoint",
     *     tags={"X402 Endpoints"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Endpoint removed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $endpoint = X402MonetizedEndpoint::where('team_id', $request->user()?->currentTeam?->id)
            ->findOrFail($id);
        $endpoint->delete();

        return response()->json([
            'data'    => ['id' => $id],
            'message' => 'Monetized endpoint removed.',
        ]);
    }
}
