<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation for GET and HEAD requests
        if (in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }

        // Check if the content type is application/json
        $contentType = $request->header('Content-Type');
        
        if (!$contentType || !str_contains($contentType, 'application/json')) {
            return response()->json([
                'error' => 'Unsupported Media Type',
                'message' => 'This API endpoint requires Content-Type: application/json'
            ], 415);
        }

        return $next($request);
    }
}