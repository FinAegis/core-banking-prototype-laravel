<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy
        $csp = $this->getContentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $csp);

        // Permissions Policy (formerly Feature Policy)
        $permissions = $this->getPermissionsPolicy();
        $response->headers->set('Permissions-Policy', $permissions);

        // HSTS - different values for different environments
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        } elseif (app()->environment('testing', 'local', 'development')) {
            // Set a shorter HSTS duration for testing
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Remove sensitive headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Ensure JSON responses have proper content type
        if ($request->is('api/*') && $response->headers->get('Content-Type') === null) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }

    /**
     * Get Content Security Policy directives.
     */
    private function getContentSecurityPolicy(): string
    {
        // Get configured sources
        $fontSources = explode(',', config('security.csp.font_sources', ''));
        $styleSources = explode(',', config('security.csp.style_sources', ''));
        $scriptSources = explode(',', config('security.csp.script_sources', ''));
        $connectSources = explode(',', config('security.csp.connect_sources', ''));
        $apiEndpoint = config('security.csp.api_endpoint', '');
        $wsEndpoint = config('security.csp.ws_endpoint', '');

        // Build policies
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . implode(' ', $scriptSources),
            "style-src 'self' 'unsafe-inline' " . implode(' ', $styleSources),
            "img-src 'self' data: https:",
            "font-src 'self' " . implode(' ', $fontSources),
            "connect-src 'self' {$apiEndpoint} {$wsEndpoint} " . implode(' ', $connectSources),
            "media-src 'none'",
            "object-src 'none'",
            "frame-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        // Only add upgrade-insecure-requests if configured
        if (config('security.force_https', false)) {
            $policies[] = 'upgrade-insecure-requests';
        }

        // In local/development, allow more flexibility
        if (app()->environment('local', 'development')) {
            // Get local hostnames
            $localHosts = explode(',', config('app.local_hostnames', 'localhost,127.0.0.1'));
            $localConnections = [];

            foreach ($localHosts as $host) {
                $localConnections[] = "http://{$host}:*";
                $localConnections[] = "https://{$host}:*";
                $localConnections[] = "ws://{$host}:*";
                $localConnections[] = "wss://{$host}:*";
            }

            $policies = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . implode(' ', $scriptSources) . ' ' . str_replace('https://', 'http://', implode(' ', $scriptSources)),
                "style-src 'self' 'unsafe-inline' " . implode(' ', $styleSources) . ' ' . str_replace('https://', 'http://', implode(' ', $styleSources)),
                "img-src 'self' data: https: http:",
                "font-src 'self' " . implode(' ', $fontSources) . ' ' . str_replace('https://', 'http://', implode(' ', $fontSources)),
                "connect-src 'self' " . implode(' ', $localConnections) . " {$apiEndpoint} {$wsEndpoint} " . implode(' ', $connectSources),
                "media-src 'none'",
                "object-src 'none'",
                "frame-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ];
        }

        return implode('; ', $policies);
    }

    /**
     * Get Permissions Policy directives.
     */
    private function getPermissionsPolicy(): string
    {
        $policies = [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=(self)',
            'usb=()',
        ];

        return implode(', ', $policies);
    }
}
