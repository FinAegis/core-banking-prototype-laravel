<?php

namespace App\Http\Middleware;

use App\Services\SubProductService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubProductEnabled
{
    public function __construct(
        private SubProductService $subProductService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $subProduct, ?string $feature = null): Response
    {
        // Check if sub-product is enabled
        if (!$this->subProductService->isEnabled($subProduct)) {
            return response()->json([
                'error' => 'Sub-product not available',
                'message' => "The {$subProduct} sub-product is not enabled on this platform.",
                'code' => 'SUB_PRODUCT_DISABLED',
            ], 403);
        }

        // Check if specific feature is enabled (if provided)
        if ($feature && !$this->subProductService->isFeatureEnabled($subProduct, $feature)) {
            return response()->json([
                'error' => 'Feature not available',
                'message' => "The {$feature} feature of {$subProduct} is not enabled.",
                'code' => 'FEATURE_DISABLED',
            ], 403);
        }

        // Add sub-product info to request for downstream use
        $request->attributes->set('sub_product', $subProduct);
        if ($feature) {
            $request->attributes->set('sub_product_feature', $feature);
        }

        return $next($request);
    }
}