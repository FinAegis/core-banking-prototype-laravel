<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoMetaTagsTest extends TestCase
{
/**
 * Test that all public pages have proper SEO meta tags.
 */ #[Test]
    public function test_public_pages_have_seo_meta_tags(): void
    {
        $pages = [
            '/' => [
                'title'          => 'FinAegis - The Enterprise Financial Platform',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
            '/about' => [
                'title'          => 'About FinAegis - Our Mission & Community',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
            '/platform' => [
                'title'          => 'FinAegis Platform - Open Banking for Developers',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
            '/gcu' => [
                'title'          => 'Global Currency Unit (GCU) - FinAegis',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
            '/pricing' => [
                'title'          => 'Pricing - Flexible Plans for Every Scale | FinAegis',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
            '/security' => [
                'title'          => 'Security - Bank-Grade Protection | FinAegis',
                'hasDescription' => true,
                'hasKeywords'    => true,
                'hasOgTags'      => true,
            ],
        ];

        foreach ($pages as $url => $expectations) {
            $response = $this->get($url);

            $response->assertStatus(200);

            // Check title - handle HTML entities in the title
            $expectedTitle = htmlspecialchars($expectations['title'], ENT_QUOTES, 'UTF-8');
            $response->assertSee('<title>' . $expectedTitle . '</title>', false);

            // Check meta description
            if ($expectations['hasDescription']) {
                $response->assertSee('<meta name="description"', false);
            }

            // Check meta keywords
            if ($expectations['hasKeywords']) {
                $response->assertSee('<meta name="keywords"', false);
            }

            // Check Open Graph tags
            if ($expectations['hasOgTags']) {
                $response->assertSee('<meta property="og:title"', false);
                $response->assertSee('<meta property="og:description"', false);
                $response->assertSee('<meta property="og:url"', false);
                $response->assertSee('<meta property="og:type"', false);
            }
        }
    }

/**
 * Test that pages have Twitter Card meta tags.
 */ #[Test]
    public function test_pages_have_twitter_card_tags(): void
    {
        $pages = ['/', '/about', '/platform', '/gcu', '/pricing', '/security'];

        foreach ($pages as $url) {
            $response = $this->get($url);

            $response->assertStatus(200);

            // Check Twitter Card tags
            $response->assertSee('<meta name="twitter:card"', false);
            $response->assertSee('<meta name="twitter:title"', false);
            $response->assertSee('<meta name="twitter:description"', false);
            $response->assertSee('<meta name="twitter:image"', false);
        }
    }

/**
 * Test that pages have canonical URLs.
 */ #[Test]
    public function test_pages_have_canonical_urls(): void
    {
        $pages = ['/', '/about', '/platform', '/gcu', '/pricing', '/security'];

        foreach ($pages as $url) {
            $response = $this->get($url);

            $response->assertStatus(200);

            // Check canonical link
            $response->assertSee('<link rel="canonical"', false);
            // For home page, the canonical URL should not have trailing slash
            $canonicalUrl = $url === '/' ? config('app.url') : config('app.url') . $url;
            $response->assertSee('href="' . $canonicalUrl . '"', false);
        }
    }

/**
 * Test that pages have proper robots meta tag.
 */ #[Test]
    public function test_pages_have_robots_meta_tag(): void
    {
        $pages = ['/', '/about', '/platform', '/gcu', '/pricing', '/security'];

        foreach ($pages as $url) {
            $response = $this->get($url);

            $response->assertStatus(200);

            // Check robots meta tag
            $response->assertSee('<meta name="robots" content="index, follow">', false);
        }
    }

/**
 * Test that SEO partial handles missing parameters gracefully.
 */ #[Test]
    public function test_seo_partial_handles_missing_parameters(): void
    {
        // Test sub-products page which should use defaults for some values
        $response = $this->get('/sub-products');

        $response->assertStatus(200);

        // Should still have meta tags with default values
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<meta name="keywords"', false);
        $response->assertSee('<meta property="og:title"', false);
    }

/**
 * Test that meta descriptions have appropriate length.
 */ #[Test]
    public function test_meta_descriptions_have_appropriate_length(): void
    {
        $pages = [
            '/'         => 'FinAegis - The Enterprise Financial Platform Powering the Future of Banking. Experience the Global Currency Unit (GCU) with democratic governance and real bank integration.',
            '/about'    => 'Learn about FinAegis - revolutionizing banking with democratic governance and the Global Currency Unit. Our mission, team, and journey.',
            '/platform' => 'FinAegis Platform - Open-source banking infrastructure for developers. Build, deploy, and scale financial services with our MIT-licensed platform.',
            '/pricing'  => 'FinAegis Pricing - Start with our free open-source community edition. Scale with enterprise support, custom features, and dedicated infrastructure when ready.',
        ];

        foreach ($pages as $url => $expectedDescription) {
            $response = $this->get($url);
            $response->assertStatus(200);

            // Check that description length is between 120-160 characters (optimal for SEO)
            $length = strlen($expectedDescription);
            $this->assertGreaterThanOrEqual(120, $length, "Description for $url is too short");
            $this->assertLessThanOrEqual(200, $length, "Description for $url might be too long");
        }
    }
}
