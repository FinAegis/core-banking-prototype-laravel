<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MachinePay;

use App\Domain\MachinePay\Models\MppMonetizedResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Machine Payments')]
class MppResourceController extends Controller
{
    public function __construct()
    {
        $this->middleware('is_admin')->except(['index', 'show']);
    }

    #[OA\Get(
        path: '/api/v1/mpp/resources',
        operationId: 'mppResources',
        summary: 'List monetized resources',
        description: 'Returns paginated list of API endpoints that require MPP payment. Filter active-only by default.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'active_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Filter to active resources only (default: true)'),
        ],
    )]
    #[OA\Response(response: 200, description: 'Monetized resources')]
    public function index(Request $request): JsonResponse
    {
        $resources = MppMonetizedResource::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $resources,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/mpp/resources',
        operationId: 'mppCreateResource',
        summary: 'Create a monetized resource',
        description: 'Register an API endpoint for MPP payment gating. Admin only. Blocked for auth/admin/monitoring/webhook paths.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['method', 'path', 'amount_cents', 'currency', 'available_rails'],
            properties: [
                new OA\Property(property: 'method', type: 'string', enum: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
                new OA\Property(property: 'path', type: 'string', example: 'v1/sms/send'),
                new OA\Property(property: 'amount_cents', type: 'integer', example: 5000),
                new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                new OA\Property(property: 'available_rails', type: 'array', items: new OA\Items(type: 'string', enum: ['stripe', 'tempo', 'lightning', 'card', 'x402'])),
                new OA\Property(property: 'description', type: 'string', nullable: true),
            ],
        ),
    )]
    #[OA\Response(response: 201, description: 'Resource created')]
    #[OA\Response(response: 409, description: 'Duplicate method+path')]
    #[OA\Response(response: 422, description: 'Validation error or blocked path')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method'            => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'path'              => ['required', 'string', 'max:500', 'regex:/^[a-zA-Z0-9\/_\-\.{}]+$/'],
            'amount_cents'      => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency'          => ['required', 'string', 'max:10'],
            'available_rails'   => ['required', 'array', 'min:1'],
            'available_rails.*' => ['string', 'in:stripe,tempo,lightning,card,x402'],
            'description'       => ['nullable', 'string', 'max:500'],
            'mime_type'         => ['nullable', 'string', 'max:128'],
        ]);

        // Prevent duplicate method+path
        $exists = MppMonetizedResource::where('method', $validated['method'])
            ->where('path', $validated['path'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error'   => 'A monetized resource with this method and path already exists.',
            ], 409);
        }

        // Block monetization of sensitive paths
        $blockedPrefixes = ['auth/', 'admin/', 'monitoring/', 'webhooks/'];
        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($validated['path'], $prefix)) {
                return response()->json([
                    'success' => false,
                    'error'   => "Cannot monetize paths starting with '{$prefix}'.",
                ], 422);
            }
        }

        $resource = MppMonetizedResource::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $resource,
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/mpp/resources/{id}',
        operationId: 'mppShowResource',
        summary: 'Get a monetized resource',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Resource details')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function show(int $id): JsonResponse
    {
        $resource = MppMonetizedResource::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $resource,
        ]);
    }

    #[OA\Put(
        path: '/api/v1/mpp/resources/{id}',
        operationId: 'mppUpdateResource',
        summary: 'Update a monetized resource',
        description: 'Update pricing, rails, or active status. Admin only.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'amount_cents', type: 'integer'),
                new OA\Property(property: 'currency', type: 'string'),
                new OA\Property(property: 'available_rails', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Resource updated')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function update(Request $request, int $id): JsonResponse
    {
        $resource = MppMonetizedResource::findOrFail($id);

        $validated = $request->validate([
            'amount_cents'      => ['sometimes', 'integer', 'min:1', 'max:100000000'],
            'currency'          => ['sometimes', 'string', 'max:10'],
            'available_rails'   => ['sometimes', 'array', 'min:1'],
            'available_rails.*' => ['string', 'in:stripe,tempo,lightning,card,x402'],
            'description'       => ['nullable', 'string', 'max:500'],
            'is_active'         => ['sometimes', 'boolean'],
        ]);

        $resource->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $resource->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/mpp/resources/{id}',
        operationId: 'mppDeleteResource',
        summary: 'Delete a monetized resource',
        description: 'Remove an API endpoint from MPP payment gating. Admin only.',
        security: [['sanctum' => []]],
        tags: ['Machine Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
    )]
    #[OA\Response(response: 200, description: 'Resource deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function destroy(int $id): JsonResponse
    {
        $resource = MppMonetizedResource::findOrFail($id);
        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Monetized resource deleted.',
        ]);
    }
}
