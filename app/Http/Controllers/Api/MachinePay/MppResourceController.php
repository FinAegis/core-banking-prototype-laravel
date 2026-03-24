<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MachinePay;

use App\Domain\MachinePay\Models\MppMonetizedResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MppResourceController extends Controller
{
    public function __construct()
    {
        $this->middleware('is_admin')->except(['index', 'show']);
    }

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

    public function show(int $id): JsonResponse
    {
        $resource = MppMonetizedResource::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $resource,
        ]);
    }

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
