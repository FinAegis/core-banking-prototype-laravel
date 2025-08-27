<?php

namespace Tests\Unit\Helpers;

use App\Helpers\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class SchemaHelperTest extends TestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(SchemaHelper::class));
    }

    #[Test]
    public function test_has_static_methods(): void
    {
        $expectedMethods = [
            'organization',
            'website',
            'softwareApplication',
            'gcuProduct',
            'faq',
            'breadcrumb',
            'service',
            'article',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists(SchemaHelper::class, $method));
        }
    }

    #[Test]
    public function test_organization_returns_valid_json_ld(): void
    {
        $result = SchemaHelper::organization();

        $this->assertIsString($result);
        $this->assertStringContainsString('<script type="application/ld+json">', $result);
        $this->assertStringContainsString('</script>', $result);

        // Extract JSON from script tag
        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Organization', $schema['@type']);
        $this->assertEquals('FinAegis', $schema['name']);
        $this->assertEquals('FinAegis Core Banking Platform', $schema['alternateName']);
        $this->assertArrayHasKey('logo', $schema);
        $this->assertArrayHasKey('sameAs', $schema);
        $this->assertIsArray($schema['sameAs']);
        $this->assertArrayHasKey('contactPoint', $schema);
        $this->assertEquals('ContactPoint', $schema['contactPoint']['@type']);
        $this->assertArrayHasKey('knowsAbout', $schema);
        $this->assertContains('Core Banking', $schema['knowsAbout']);
    }

    #[Test]
    public function test_website_returns_valid_json_ld(): void
    {
        $result = SchemaHelper::website();

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('WebSite', $schema['@type']);
        $this->assertEquals('FinAegis', $schema['name']);
        $this->assertArrayHasKey('potentialAction', $schema);
        $this->assertEquals('SearchAction', $schema['potentialAction']['@type']);
        $this->assertArrayHasKey('target', $schema['potentialAction']);
        $this->assertStringContainsString('{search_term_string}', $schema['potentialAction']['target']['urlTemplate']);
    }

    #[Test]
    public function test_software_application_returns_valid_json_ld(): void
    {
        $result = SchemaHelper::softwareApplication();

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('SoftwareApplication', $schema['@type']);
        $this->assertEquals('FinAegis Core Banking Platform', $schema['name']);
        $this->assertEquals('Linux, macOS, Windows', $schema['operatingSystem']);
        $this->assertEquals('FinanceApplication', $schema['applicationCategory']);
        $this->assertArrayHasKey('offers', $schema);
        $this->assertIsArray($schema['offers']);
        $this->assertCount(2, $schema['offers']);
        $this->assertEquals('Community Edition', $schema['offers'][0]['name']);
        $this->assertEquals('0', $schema['offers'][0]['price']);
    }

    #[Test]
    public function test_gcu_product_returns_valid_json_ld(): void
    {
        $result = SchemaHelper::gcuProduct();

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Product', $schema['@type']);
        $this->assertEquals('Global Currency Unit (GCU)', $schema['name']);
        $this->assertArrayHasKey('brand', $schema);
        $this->assertEquals('FinAegis', $schema['brand']['name']);
        $this->assertEquals('Digital Currency', $schema['category']);
        $this->assertArrayHasKey('offers', $schema);
        $this->assertEquals('1.00', $schema['offers']['price']);
        $this->assertArrayHasKey('aggregateRating', $schema);
        $this->assertEquals('4.8', $schema['aggregateRating']['ratingValue']);
    }

    #[Test]
    public function test_faq_returns_valid_json_ld(): void
    {
        $faqs = [
            ['question' => 'What is FinAegis?', 'answer' => 'FinAegis is a core banking platform.'],
            ['question' => 'How does GCU work?', 'answer' => 'GCU is a democratically governed basket currency.'],
        ];

        $result = SchemaHelper::faq($faqs);

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('FAQPage', $schema['@type']);
        $this->assertArrayHasKey('mainEntity', $schema);
        $this->assertCount(2, $schema['mainEntity']);
        $this->assertEquals('Question', $schema['mainEntity'][0]['@type']);
        $this->assertEquals($faqs[0]['question'], $schema['mainEntity'][0]['name']);
        $this->assertEquals($faqs[0]['answer'], $schema['mainEntity'][0]['acceptedAnswer']['text']);
    }

    #[Test]
    public function test_breadcrumb_returns_valid_json_ld(): void
    {
        $items = [
            ['name' => 'Home', 'url' => 'https://finaegis.com'],
            ['name' => 'Products', 'url' => 'https://finaegis.com/products'],
            ['name' => 'GCU', 'url' => 'https://finaegis.com/products/gcu'],
        ];

        $result = SchemaHelper::breadcrumb($items);

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('BreadcrumbList', $schema['@type']);
        $this->assertArrayHasKey('itemListElement', $schema);
        $this->assertCount(3, $schema['itemListElement']);

        foreach ($schema['itemListElement'] as $index => $item) {
            $this->assertEquals('ListItem', $item['@type']);
            $this->assertEquals($index + 1, $item['position']);
            $this->assertEquals($items[$index]['name'], $item['name']);
            $this->assertEquals($items[$index]['url'], $item['item']);
        }
    }

    #[Test]
    public function test_service_returns_valid_json_ld(): void
    {
        $name = 'Core Banking API';
        $description = 'Enterprise-grade banking API solution';
        $category = 'Financial Services';

        $result = SchemaHelper::service($name, $description, $category);

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Service', $schema['@type']);
        $this->assertEquals($name, $schema['name']);
        $this->assertEquals($description, $schema['description']);
        $this->assertEquals($category, $schema['serviceType']);
        $this->assertEquals('Global', $schema['areaServed']);
        $this->assertArrayHasKey('provider', $schema);
        $this->assertEquals('FinAegis', $schema['provider']['name']);
    }

    #[Test]
    public function test_article_returns_valid_json_ld(): void
    {
        $data = [
            'title'        => 'Understanding Core Banking',
            'description'  => 'A comprehensive guide to modern core banking systems',
            'url'          => 'https://finaegis.com/blog/understanding-core-banking',
            'published_at' => '2024-01-01T00:00:00Z',
            'updated_at'   => '2024-01-02T00:00:00Z',
            'image'        => 'https://finaegis.com/images/article.jpg',
        ];

        $result = SchemaHelper::article($data);

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Article', $schema['@type']);
        $this->assertEquals($data['title'], $schema['headline']);
        $this->assertEquals($data['description'], $schema['description']);
        $this->assertEquals($data['published_at'], $schema['datePublished']);
        $this->assertEquals($data['updated_at'], $schema['dateModified']);
        $this->assertEquals($data['image'], $schema['image']);
        $this->assertArrayHasKey('author', $schema);
        $this->assertArrayHasKey('publisher', $schema);
        $this->assertEquals('FinAegis', $schema['author']['name']);
    }

    #[Test]
    public function test_article_without_optional_fields(): void
    {
        $data = [
            'title'       => 'Test Article',
            'description' => 'Test description',
            'url'         => 'https://finaegis.com/blog/test',
        ];

        $result = SchemaHelper::article($data);

        $json = $this->extractJsonFromScript($result);
        $schema = json_decode($json, true);

        $this->assertArrayHasKey('datePublished', $schema);
        $this->assertArrayHasKey('dateModified', $schema);
        $this->assertArrayNotHasKey('image', $schema);
    }

    #[Test]
    public function test_json_encoding_preserves_unicode(): void
    {
        $faqs = [
            ['question' => 'What is €uro support?', 'answer' => 'We support €uro and other currencies'],
        ];

        $result = SchemaHelper::faq($faqs);

        // Check that Unicode characters are not escaped
        $this->assertStringContainsString('€uro', $result);
        $this->assertStringNotContainsString('\u20ac', $result);
    }

    #[Test]
    public function test_json_encoding_preserves_slashes(): void
    {
        $result = SchemaHelper::organization();

        // Check that slashes are not escaped
        $this->assertStringContainsString('https://schema.org', $result);
        $this->assertStringNotContainsString('https:\\/\\/schema.org', $result);
    }

    #[Test]
    public function test_generate_script_method_exists(): void
    {
        $reflection = new ReflectionClass(SchemaHelper::class);
        $this->assertTrue($reflection->hasMethod('generateScript'));

        $method = $reflection->getMethod('generateScript');
        $this->assertTrue($method->isPrivate());
        $this->assertTrue($method->isStatic());
    }

    private function extractJsonFromScript(string $script): string
    {
        $pattern = '/<script type="application\/ld\+json">\n(.*)\n<\/script>/s';
        preg_match($pattern, $script, $matches);

        return $matches[1] ?? '';
    }
}
