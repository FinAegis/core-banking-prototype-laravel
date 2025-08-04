<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchemaMarkupTest extends TestCase
{
    #[Test]
    public function test_homepage_has_organization_and_website_schema()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "Organization"', false);
        $response->assertSee('"@type": "WebSite"', false);
        $response->assertSee('"name": "FinAegis"', false);
    }

    #[Test]
    public function test_gcu_page_has_product_and_breadcrumb_schema()
    {
        $response = $this->get('/gcu');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "Product"', false);
        $response->assertSee('"name": "Global Currency Unit (GCU)"', false);
        $response->assertSee('"@type": "BreadcrumbList"', false);
    }

    #[Test]
    public function test_platform_page_has_software_schema()
    {
        $response = $this->get('/platform');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "SoftwareApplication"', false);
        $response->assertSee('"name": "FinAegis Core Banking Platform"', false);
    }

    #[Test]
    public function test_about_page_has_organization_schema()
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "Organization"', false);
    }

    #[Test]
    public function test_pricing_page_has_software_schema()
    {
        $response = $this->get('/pricing');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "SoftwareApplication"', false);
    }

    #[Test]
    public function test_security_page_has_service_schema()
    {
        $response = $this->get('/security');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "Service"', false);
        $response->assertSee('"name": "FinAegis Security"', false);
    }

    #[Test]
    public function test_faq_page_has_faqpage_schema()
    {
        $response = $this->get('/support/faq');

        $response->assertStatus(200);
        $response->assertSee('<script type="application/ld+json">', false);
        $response->assertSee('"@type": "FAQPage"', false);
        $response->assertSee('"@type": "Question"', false);
        $response->assertSee('"@type": "Answer"', false);
    }

    #[Test]
    public function test_breadcrumb_schema_has_correct_structure()
    {
        $response = $this->get('/gcu');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check for breadcrumb structure
        $this->assertStringContainsString('"@type": "BreadcrumbList"', $content);
        $this->assertStringContainsString('"@type": "ListItem"', $content);
        $this->assertStringContainsString('"position": 1', $content);
        $this->assertStringContainsString('"position": 2', $content);
    }

    #[Test]
    public function test_schema_json_is_valid()
    {
        $response = $this->get('/');
        $content = $response->getContent();

        // Extract JSON-LD scripts
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        $this->assertNotEmpty($matches[1], 'No JSON-LD scripts found');

        foreach ($matches[1] as $jsonString) {
            $json = json_decode($jsonString);
            $this->assertNotNull($json, 'Invalid JSON in schema markup');
            $this->assertObjectHasProperty('@context', $json);
            $this->assertObjectHasProperty('@type', $json);
        }
    }
}
