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
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        // Validate parameter
        if (empty($parameter)) {
            return $this->errorResponse('Sub-product parameter is required', 500);
        }

        // Parse parameter for sub-product and features
        if (str_contains($parameter, ':')) {
            [$subProduct, $features] = explode(':', $parameter, 2);
            
            // Handle multiple features with OR logic
            if (str_contains($features, '|')) {
                $featureList = explode('|', $features);
                $anyEnabled = false;
                
                foreach ($featureList as $feature) {
                    if ($this->subProductService->isFeatureEnabled($subProduct, $feature)) {
                        $anyEnabled = true;
                        break;
                    }
                }
                
                if (!$anyEnabled) {
                    return $this->errorResponse(
                        "None of the required features [" . implode(', ', $featureList) . "] are enabled for sub-product {$subProduct}",
                        403
                    );
                }
            } else {
                // Single feature check
                if (!$this->subProductService->isFeatureEnabled($subProduct, $features)) {
                    return $this->errorResponse(
                        "Feature {$features} is not enabled for sub-product {$subProduct}",
                        403
                    );
                }
            }
        } else {
            // Just sub-product check
            $subProduct = $parameter;
            
            if (!$this->subProductService->isEnabled($subProduct)) {
                return $this->errorResponse("Sub-product {$subProduct} is not enabled", 403);
            }
        }

        // Add sub-product info to request for downstream use
        $request->attributes->set('sub_product', $subProduct ?? $parameter);

        return $next($request);
    }

    /**
     * Create error response
     */
    private function errorResponse(string $message, int $statusCode): Response
    {
        return response()->json([
            'error' => $message,
        ], $statusCode);
    }
}