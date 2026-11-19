<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Http\Controllers\Controller;
use App\Support\MachinePaymentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * X402 Monetized Endpoint Management Controller.
 *
 * Manages which API endpoints require x402 payment and their pricing.
 * Prices apply platform-wide, so administrators (StaffRoles::ADMINS) read
 * and manage every row; other users read their current team's rows only.
 */
#[OA\Tag(
    name: 'X402 Endpoints',
    description: 'Manage monetized API endpoints'
)]
class X402EndpointController extends Controller
{
    /**
     * List all monetized endpoints.
     */
    #[OA\Get(
        path: '/api/v1/x402/endpoints',
        summary: 'List monetized endpoints',
        description: 'Every monetized endpoint for platform administrators (admin, super_admin); the rows of the caller\'s current team otherwise (none without a team).',
        tags: ['X402 Endpoints'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        new OA\Parameter(name: 'network', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'List of monetized endpoints'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function index(Request $request): JsonResponse
    {
        $query = MachinePaymentScope::teamRecords(X402MonetizedEndpoint::query(), $request->user());

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('network')) {
            $query->where('network', $request->input('network'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

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
     */
    #[OA\Post(
        path: '/api/v1/x402/endpoints',
        summary: 'Register an endpoint for x402 payment',
        tags: ['X402 Endpoints'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['method', 'path', 'price'], properties: [
        new OA\Property(property: 'method', type: 'string', enum: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
        new OA\Property(property: 'path', type: 'string', example: 'api/v1/ai/query'),
        new OA\Property(property: 'price', type: 'string', example: '0.01'),
        new OA\Property(property: 'network', type: 'string', example: 'eip155:8453'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'Endpoint monetized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    #[OA\Response(
        response: 403,
        description: 'Administrators only (admin, super_admin)'
    )]
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

        // One active price per method and path: the gate looks prices up by
        // route, whatever the team.
        $isActive = $request->boolean('is_active', true);
        if ($isActive && X402MonetizedEndpoint::activeRouteExists((string) $request->input('method'), (string) $request->input('path'))) {
            return response()->json(['errors' => ['path' => [X402MonetizedEndpoint::DUPLICATE_ROUTE_MESSAGE]]], 422);
        }

        $endpoint = new X402MonetizedEndpoint([
            'method'      => $request->input('method'),
            'path'        => $request->input('path'),
            'price'       => $request->input('price'),
            'network'     => $request->input('network', config('x402.server.default_network')),
            'description' => $request->input('description'),
            'is_active'   => $isActive,
            'team_id'     => $request->user()?->currentTeam?->id,
        ]);
        $endpoint->created_by_user_id = $request->user()?->id;
        $endpoint->save();

        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        return response()->json([
            'data'    => $endpoint->toApiResponse(),
            'message' => 'Endpoint monetized successfully.',
        ], 201);
    }

    /**
     * Get a monetized endpoint.
     */
    #[OA\Get(
        path: '/api/v1/x402/endpoints/{id}',
        summary: 'Get monetized endpoint details',
        description: 'Any monetized endpoint for platform administrators (admin, super_admin); a row of the caller\'s current team otherwise; 404 for anything else.',
        tags: ['X402 Endpoints'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Endpoint details'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $endpoint = MachinePaymentScope::teamRecords(X402MonetizedEndpoint::query(), $request->user())
            ->findOrFail($id);

        return response()->json([
            'data' => $endpoint->toApiResponse(),
        ]);
    }

    /**
     * Update a monetized endpoint.
     */
    #[OA\Put(
        path: '/api/v1/x402/endpoints/{id}',
        summary: 'Update monetized endpoint pricing or status',
        description: 'Administrators only (admin, super_admin). Prices apply platform-wide, so any monetized endpoint can be updated, whichever team created it.',
        tags: ['X402 Endpoints'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'price', type: 'string', example: '0.02'),
        new OA\Property(property: 'network', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'asset', type: 'string', enum: ['USDC']),
        new OA\Property(property: 'scheme', type: 'string', enum: ['exact', 'upto']),
        new OA\Property(property: 'mime_type', type: 'string', example: 'application/json'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Endpoint updated'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    #[OA\Response(
        response: 403,
        description: 'Administrators only (admin, super_admin)'
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        // Administrators only (route middleware): any row, whatever the team.
        $endpoint = MachinePaymentScope::teamRecords(X402MonetizedEndpoint::query(), $request->user())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'price'       => ['sometimes', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'network'     => ['sometimes', 'string', 'regex:/^eip155:\d+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
            'asset'       => ['sometimes', 'string', Rule::in(['USDC'])],
            'scheme'      => ['sometimes', 'string', Rule::in(['exact', 'upto'])],
            'mime_type'   => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        // Activating a price is refused while another row prices the route.
        $activates = array_key_exists('is_active', $validated) && (bool) $validated['is_active'] && ! $endpoint->is_active;
        if ($activates && X402MonetizedEndpoint::activeRouteExists($endpoint->method, $endpoint->path, $endpoint->id)) {
            return response()->json(['errors' => ['is_active' => [X402MonetizedEndpoint::DUPLICATE_ROUTE_MESSAGE]]], 422);
        }

        $oldCacheKey = "x402:route:{$endpoint->method}:{$endpoint->path}";

        $endpoint->fill($validated);
        // An administrator's update vouches for a row written before prices
        // became admin-only.
        $endpoint->created_by_user_id ??= $request->user()?->id;
        $endpoint->save();
        $endpoint->refresh();

        Cache::forget($oldCacheKey);
        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        return response()->json([
            'data'    => $endpoint->toApiResponse(),
            'message' => 'Endpoint updated successfully.',
        ]);
    }

    /**
     * Delete a monetized endpoint.
     */
    #[OA\Delete(
        path: '/api/v1/x402/endpoints/{id}',
        summary: 'Remove monetization from an endpoint',
        description: 'Administrators only (admin, super_admin). Prices apply platform-wide, so any monetized endpoint can be removed, whichever team created it.',
        tags: ['X402 Endpoints'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 204,
        description: 'Endpoint removed'
    )]
    #[OA\Response(
        response: 404,
        description: 'Not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    #[OA\Response(
        response: 403,
        description: 'Administrators only (admin, super_admin)'
    )]
    public function destroy(Request $request, string $id): Response
    {
        // Administrators only (route middleware): any row, whatever the team.
        $endpoint = MachinePaymentScope::teamRecords(X402MonetizedEndpoint::query(), $request->user())
            ->findOrFail($id);

        Cache::forget("x402:route:{$endpoint->method}:{$endpoint->path}");

        $endpoint->delete();

        return response()->noContent();
    }
}
