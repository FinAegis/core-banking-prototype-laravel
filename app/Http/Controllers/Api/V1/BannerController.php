<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BannerController extends Controller
{
    #[OA\Get(
        path: '/api/v1/banners',
        operationId: 'v1ListBanners',
        tags: ['Banners'],
        summary: 'List active banners for current user',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Active banners')]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $banners = Banner::currentlyVisible()
            ->notDismissedBy($userId)
            ->orderBy('position')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => BannerResource::collection($banners),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/banners/{id}/dismiss',
        operationId: 'v1DismissBanner',
        tags: ['Banners'],
        summary: 'Dismiss a banner for the current user',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Banner dismissed')]
    #[OA\Response(response: 404, description: 'Banner not found')]
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Banner not found',
                ],
            ], 404);
        }

        $banner->dismiss($request->user()->id);

        return response()->json([
            'message' => 'Banner dismissed',
        ]);
    }
}
