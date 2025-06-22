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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
        
        // HSTS for production
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Remove sensitive headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        
        return $response;
    }
    
    /**
     * Get Content Security Policy directives
     */
    private function getContentSecurityPolicy(): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://api.finaegis.com wss://ws.finaegis.com",
            "media-src 'none'",
            "object-src 'none'",
            "frame-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
        ];
        
        return implode('; ', $policies);
    }
    
    /**
     * Get Permissions Policy directives
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