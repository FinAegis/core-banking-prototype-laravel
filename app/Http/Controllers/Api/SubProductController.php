<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubProductService;
use Illuminate\Http\JsonResponse;

class SubProductController extends Controller
{
    public function __construct(
        private SubProductService $subProductService
    ) {}

    /**
     * Get all sub-product statuses
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->subProductService->getApiStatus(),
        ]);
    }

    /**
     * Get specific sub-product status
     */
    public function show(string $subProduct): JsonResponse
    {
        $allProducts = $this->subProductService->getApiStatus();
        
        if (!isset($allProducts[$subProduct])) {
            return response()->json([
                'error' => 'Sub-product not found',
                'message' => "The sub-product '{$subProduct}' does not exist.",
            ], 404);
        }
        
        return response()->json([
            'data' => $allProducts[$subProduct],
        ]);
    }

    /**
     * Get enabled sub-products for the current user (authenticated)
     */
    public function enabled(): JsonResponse
    {
        $enabledProducts = $this->subProductService->getEnabledSubProducts();
        
        return response()->json([
            'data' => array_map(function ($product) {
                return [
                    'key' => $product['key'],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'icon' => $product['icon'],
                    'color' => $product['color'],
                    'enabled_features' => $product['enabled_features'],
                ];
            }, $enabledProducts),
        ]);
    }
}