<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to handle demo mode functionality and restrictions.
 */
class DemoMode
{
    /**
     * Operations that should be blocked in demo mode.
     */
    private array $blockedOperations = [
        'api.webhooks.*', // Block external webhook processing
        'admin.settings.production.*', // Block production settings changes
        'api.v1.bank.production.*', // Block production bank operations
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isDemoMode()) {
            return $next($request);
        }

        // Block certain operations in demo mode
        if ($this->isBlockedOperation($request)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'     => 'This operation is not available in demo mode',
                    'demo_mode' => true,
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'This operation is not available in demo mode.');
        }

        // Add demo mode indicator to all views
        View::share('isDemoMode', true);
        View::share('demoModeConfig', config('demo'));

        // Add demo mode headers to response
        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Demo-Mode', 'true');

            $features = config('demo.features', []);
            $activeFeatures = array_keys(array_filter($features));
            if (! empty($activeFeatures)) {
                $response->headers->set('X-Demo-Features', implode(',', $activeFeatures));
            }
        }

        // Add demo mode watermark CSS if enabled
        if (config('demo.indicators.watermark')) {
            $this->addWatermarkStyles($response);
        }

        return $response;
    }

    /**
     * Check if demo mode is active.
     */
    private function isDemoMode(): bool
    {
        return config('demo.mode', false) || config('demo.sandbox.enabled', false);
    }

    /**
     * Check if the current route is blocked in demo mode.
     */
    private function isBlockedOperation(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return false;
        }

        foreach ($this->blockedOperations as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        // Block destructive operations
        if ($request->isMethod('DELETE') && str_contains($routeName, 'production')) {
            return true;
        }

        // Block certain admin operations
        if (str_starts_with($routeName, 'admin.') && $this->isDestructiveAdminOperation($request)) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is a destructive admin operation.
     */
    private function isDestructiveAdminOperation(Request $request): bool
    {
        $destructivePatterns = [
            'admin.users.delete',
            'admin.settings.reset',
            'admin.database.truncate',
            'admin.system.shutdown',
        ];

        $routeName = $request->route()?->getName() ?? '';

        foreach ($destructivePatterns as $pattern) {
            if (str_starts_with($routeName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add watermark styles to the response.
     */
    private function addWatermarkStyles(Response $response): void
    {
        if (! $response->getContent() || ! str_contains($response->headers->get('content-type', ''), 'text/html')) {
            return;
        }

        $watermarkCss = '
            <style>
                body::before {
                    content: "DEMO MODE";
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 120px;
                    font-weight: bold;
                    color: rgba(0, 0, 0, 0.05);
                    z-index: 9999;
                    pointer-events: none;
                    user-select: none;
                }
                
                @media (prefers-color-scheme: dark) {
                    body::before {
                        color: rgba(255, 255, 255, 0.05);
                    }
                }
                
                .demo-indicator {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background: #f59e0b;
                    color: white;
                    padding: 5px 15px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: bold;
                    z-index: 10000;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
            </style>
            <div class="demo-indicator">DEMO MODE</div>
        ';

        $content = $response->getContent();
        $content = str_replace('</head>', $watermarkCss . '</head>', $content);
        $response->setContent($content);
    }
}
