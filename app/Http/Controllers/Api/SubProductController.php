<?php

namespace App\Http\Controllers\Api;

use App\Domain\Product\Services\SubProductService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Sub-Products",
 *     description="Sub-product status and configuration endpoints"
 * )
 */
class SubProductController extends Controller
{
    public function __construct(
        private SubProductService $subProductService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/sub-products",
     *     operationId="subProductsIndex",
     *     tags={"Sub-Products"},
     *     summary="Get all sub-product statuses",
     *     description="Returns status of all sub-products",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json(
            [
                'data' => $this->subProductService->getApiStatus(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/sub-products/{subProduct}",
     *     operationId="subProductsShow",
     *     tags={"Sub-Products"},
     *     summary="Get specific sub-product status",
     *     description="Returns the status of a specific sub-product",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="subProduct", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $subProduct): JsonResponse
    {
        $allProducts = $this->subProductService->getApiStatus();

        if (! isset($allProducts[$subProduct])) {
            return response()->json(
                [
                    'error'   => 'Sub-product not found',
                    'message' => "The sub-product '{$subProduct}' does not exist.",
                ],
                404
            );
        }

        return response()->json(
            [
                'data' => $allProducts[$subProduct],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/sub-products/enabled",
     *     operationId="subProductsEnabled",
     *     tags={"Sub-Products"},
     *     summary="Get enabled sub-products",
     *     description="Returns sub-products enabled for the current user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function enabled(): JsonResponse
    {
        $enabledProducts = $this->subProductService->getEnabledSubProducts();

        return response()->json(
            [
                'data' => array_map(
                    function ($product) {
                        return [
                            'key'              => $product['key'],
                            'name'             => $product['name'],
                            'description'      => $product['description'],
                            'icon'             => $product['icon'],
                            'color'            => $product['color'],
                            'enabled_features' => $product['enabled_features'],
                        ];
                    },
                    $enabledProducts
                ),
            ]
        );
    }
}
