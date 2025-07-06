<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;

class SitemapController extends Controller
{
    /**
     * Generate the sitemap
     */
    public function index(): Response
    {
        $sitemap = $this->generateSitemap();
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
    
    /**
     * Generate the sitemap XML
     */
    private function generateSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        $routes = $this->getPublicRoutes();
        
        foreach ($routes as $route) {
            $xml .= $this->generateUrlEntry($route);
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Generate a single URL entry
     */
    private function generateUrlEntry(array $route): string
    {
        $url = '<url>';
        $url .= '<loc>' . htmlspecialchars($route['url']) . '</loc>';
        $url .= '<lastmod>' . $route['lastmod'] . '</lastmod>';
        $url .= '<changefreq>' . $route['changefreq'] . '</changefreq>';
        $url .= '<priority>' . $route['priority'] . '</priority>';
        $url .= '</url>';
        
        return $url;
    }
    
    /**
     * Get all public routes for the sitemap
     */
    private function getPublicRoutes(): array
    {
        $baseUrl = config('app.url');
        $now = Carbon::now()->toW3cString();
        
        $routes = [
            // Homepage - highest priority
            [
                'url' => $baseUrl,
                'lastmod' => $now,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            
            // Main pages - high priority
            [
                'url' => $baseUrl . '/about',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'url' => $baseUrl . '/platform',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'url' => $baseUrl . '/gcu',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'url' => $baseUrl . '/pricing',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            
            // Feature pages
            [
                'url' => $baseUrl . '/features',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'url' => $baseUrl . '/features/gcu',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'url' => $baseUrl . '/security',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'url' => $baseUrl . '/compliance',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            
            // Sub-products
            [
                'url' => $baseUrl . '/sub-products',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'url' => $baseUrl . '/subproducts/exchange',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'url' => $baseUrl . '/subproducts/lending',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'url' => $baseUrl . '/subproducts/stablecoins',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'url' => $baseUrl . '/subproducts/treasury',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            
            // Developer resources
            [
                'url' => $baseUrl . '/developers',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            
            // Support pages
            [
                'url' => $baseUrl . '/support',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'url' => $baseUrl . '/support/contact',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
            [
                'url' => $baseUrl . '/support/faq',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'url' => $baseUrl . '/support/guides',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            
            // Other pages
            [
                'url' => $baseUrl . '/cgo',
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'url' => $baseUrl . '/partners',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
            [
                'url' => $baseUrl . '/blog',
                'lastmod' => $now,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ],
            
            // Legal pages - lower priority
            [
                'url' => $baseUrl . '/legal/terms',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            [
                'url' => $baseUrl . '/legal/privacy',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            [
                'url' => $baseUrl . '/legal/cookies',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            [
                'url' => $baseUrl . '/cgo/terms',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            
            // Status page
            [
                'url' => $baseUrl . '/status',
                'lastmod' => $now,
                'changefreq' => 'hourly',
                'priority' => '0.6',
            ],
            
            // Financial institutions
            [
                'url' => $baseUrl . '/financial-institutions/apply',
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
        ];
        
        // Add authentication pages
        $routes[] = [
            'url' => $baseUrl . '/login',
            'lastmod' => $now,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
        
        $routes[] = [
            'url' => $baseUrl . '/register',
            'lastmod' => $now,
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
        
        return $routes;
    }
    
    /**
     * Generate robots.txt file
     */
    public function robots(): Response
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /dashboard/\n";
        $content .= "Disallow: /user/\n";
        $content .= "Disallow: /wallet/\n";
        $content .= "Disallow: /exchange/\n";
        $content .= "Disallow: /lending/\n";
        $content .= "Disallow: /liquidity/\n";
        $content .= "Disallow: /compliance/\n";
        $content .= "Disallow: /audit/\n";
        $content .= "Disallow: /risk/\n";
        $content .= "Disallow: /monitoring/\n";
        $content .= "Disallow: /api-keys/\n";
        $content .= "Disallow: /profile/\n";
        $content .= "Disallow: /teams/\n";
        $content .= "Disallow: /subscriber/unsubscribe/\n";
        $content .= "\n";
        $content .= "Sitemap: " . config('app.url') . "/sitemap.xml\n";
        
        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}