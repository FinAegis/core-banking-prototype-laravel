<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SitemapTest extends TestCase
{
/**
 * Test that sitemap.xml is accessible and has correct content type.
 */ #[Test]
    public function test_sitemap_is_accessible(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/xml');
    }

/**
 * Test that sitemap contains essential URLs.
 */ #[Test]
    public function test_sitemap_contains_essential_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        // Check for essential URLs. /login and /register are deliberately
        // excluded by SitemapController — robots.txt Disallows them and auth
        // pages don't belong in sitemaps.
        $essentialUrls = [
            config('app.url'),
            config('app.url') . '/about',
            config('app.url') . '/platform',
            config('app.url') . '/gcu',
            config('app.url') . '/pricing',
            config('app.url') . '/features',
            config('app.url') . '/security',
            config('app.url') . '/support',
            config('app.url') . '/developers',
            config('app.url') . '/blog',
        ];

        foreach ($essentialUrls as $url) {
            $response->assertSee('<loc>' . htmlspecialchars($url) . '</loc>', false);
        }
    }

/**
 * Test that sitemap has valid XML structure.
 */ #[Test]
    public function test_sitemap_has_valid_xml_structure(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        // Check XML declaration
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);

        // Check urlset namespace
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);

        // Check that URLs have required elements
        $response->assertSee('<url>', false);
        $response->assertSee('<loc>', false);
        $response->assertSee('<lastmod>', false);
        $response->assertSee('<changefreq>', false);
        $response->assertSee('<priority>', false);
        $response->assertSee('</urlset>', false);
    }

/**
 * Test that robots.txt is accessible.
 */ #[Test]
    public function test_robots_txt_is_accessible(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

/**
 * Test that robots.txt contains correct directives.
 */ #[Test]
    public function test_robots_txt_contains_correct_directives(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);

        // Check for essential directives
        $response->assertSee('User-agent: *');
        $response->assertSee('Allow: /');
        $response->assertSee('Disallow: /admin/');
        $response->assertSee('Disallow: /api/');
        $response->assertSee('Disallow: /dashboard/');
        $response->assertSee('Disallow: /wallet/');

        // Check for sitemap reference
        $response->assertSee('Sitemap: ' . config('app.url') . '/sitemap.xml');
    }

/**
 * Test that private routes are excluded from sitemap.
 */ #[Test]
    public function test_private_routes_are_excluded_from_sitemap(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        // These URLs should NOT be in the sitemap
        $privateUrls = [
            '/dashboard',
            '/wallet',
            '/exchange',
            '/lending',
            '/liquidity',
            '/api-keys',
            '/profile',
            '/teams',
            '/admin',
        ];

        foreach ($privateUrls as $url) {
            $response->assertDontSee('<loc>' . config('app.url') . $url . '</loc>', false);
        }
    }
}
