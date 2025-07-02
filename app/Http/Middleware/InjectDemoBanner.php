<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InjectDemoBanner
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only inject banner in demo environment and for HTML responses
        if (app()->environment('demo') && 
            config('demo.features.show_demo_banner', true) &&
            $response->headers->get('Content-Type') === 'text/html; charset=UTF-8') {
            
            $content = $response->getContent();
            
            // Demo banner HTML
            $demoBanner = <<<'HTML'
            <div id="demo-banner" style="background-color: #fbbf24; color: #451a03; text-align: center; padding: 12px; font-size: 14px; font-family: system-ui, -apple-system, sans-serif; position: relative; z-index: 9999;">
                <strong>Demo Environment</strong> - This is a demonstration instance. Data may be reset periodically.
                <button onclick="document.getElementById('demo-banner').style.display='none'" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 18px;">&times;</button>
            </div>
            HTML;
            
            // Inject after opening body tag
            $content = preg_replace('/(<body[^>]*>)/i', '$1' . $demoBanner, $content, 1);
            $response->setContent($content);
        }
        
        return $response;
    }
}