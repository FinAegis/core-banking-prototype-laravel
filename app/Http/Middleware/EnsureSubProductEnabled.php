<?php

namespace App\Http\Middleware;

use App\Domain\Product\Services\SubProductService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubProductEnabled
{
    public function __construct(
        private SubProductService $subProductService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        // Validate parameter
        if (empty($parameter)) {
            return $this->errorResponse('Configuration error', 'Sub-product parameter is required', 500);
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

                if (! $anyEnabled) {
                    return $this->errorResponse(
                        'Feature not available',
                        'None of the required features [' . implode(', ', $featureList) . "] are enabled for sub-product {$subProduct}",
                        403,
                        $subProduct
                    );
                }
            } else {
                // Single feature check
                if (! $this->subProductService->isFeatureEnabled($subProduct, $features)) {
                    return $this->errorResponse(
                        'Feature not available',
                        "The feature {$features} is not enabled for sub-product {$subProduct}",
                        403,
                        $subProduct
                    );
                }
            }
        } else {
            // Just sub-product check
            $subProduct = $parameter;

            if (! $this->subProductService->isEnabled($subProduct)) {
                return $this->errorResponse(
                    'Feature not available',
                    "The {$subProduct} sub-product is not enabled",
                    403,
                    $subProduct
                );
            }
        }

        // Add sub-product info to request for downstream use
        $request->attributes->set('sub_product', $subProduct);

        $response = $next($request);

        // Add sub-product header to response
        if ($response instanceof Response) {
            $response->headers->set('X-SubProduct-Required', $subProduct);
        }

        return $response;
    }

    /**
     * Create error response.
     */
    private function errorResponse(string $error, string $message, int $statusCode, ?string $subProduct = null): Response
    {
        $response = response()->json(
            [
                'error'   => $error,
                'message' => $message,
            ],
            $statusCode
        );

        // Add sub-product header if available
        if ($subProduct) {
            $response->headers->set('X-SubProduct-Required', $subProduct);
        }

        return $response;
    }
}
