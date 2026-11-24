<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a dismissible demo notice at the top of every HTML page.
 *
 * bootstrap/app.php adds it to the web group only when APP_ENV=demo; the
 * environment check below is a second guard. DEMO_SHOW_BANNER=false
 * (`demo.ui.show_banner`) turns it off.
 */
class InjectDemoBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (
            app()->environment('demo')
            && config('demo.ui.show_banner', true)
            // Symfony sends `text/html; charset=utf-8` (lowercase charset),
            // so match the media type, not one exact header string.
            && str_starts_with(strtolower((string) $response->headers->get('Content-Type')), 'text/html')
        ) {
            $content = (string) $response->getContent();

            $demoBanner = <<<'HTML'
            <div id="demo-banner" style="background-color: #fbbf24; color: #451a03; text-align: center; padding: 12px; font-size: 14px; font-family: system-ui, -apple-system, sans-serif; position: relative; z-index: 9999;">
                <strong>This is a demo.</strong> Its data may be reset from time to time.
                <button type="button" aria-label="Close" onclick="document.getElementById('demo-banner').style.display='none'" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 18px;">&times;</button>
            </div>
            HTML;

            // Inject right after the opening body tag.
            $response->setContent((string) preg_replace('/(<body[^>]*>)/i', '$1' . $demoBanner, $content, 1));
        }

        return $response;
    }
}
