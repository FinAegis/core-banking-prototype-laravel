<?php

namespace Tests\Unit\Domain\Compliance\Services;

use App\Domain\Compliance\Services\AmlScreeningService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\ServiceTestCase;

class AmlScreeningServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    private AmlScreeningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmlScreeningService();
    }

    #[Test]
    public function test_perform_sanctions_screening_checks_multiple_lists(): void
    {
        Http::fake([
            'api.ofac.treasury.gov/*' => Http::response(['results' => []], 200),
            'webgate.ec.europa.eu/*'  => Http::response(['results' => []], 200),
            'api.un.org/*'            => Http::response(['results' => []], 200),
        ]);

        $results = $this->service->performSanctionsCheck(['name' => 'John Doe']);

        $this->assertArrayHasKey('matches', $results);
        $this->assertArrayHasKey('lists_checked', $results);
        $this->assertArrayHasKey('total_matches', $results);
        $this->assertEquals(0, $results['total_matches']);
        $this->assertContains('OFAC', $results['lists_checked']);
        $this->assertContains('EU', $results['lists_checked']);
        $this->assertContains('UN', $results['lists_checked']);
    }

    #[Test]
    public function test_perform_pep_screening(): void
    {
        Http::fake([
            '*' => Http::response(['results' => []], 200),
        ]);

        $results = $this->service->performPEPCheck(['name' => 'Test User']);

        $this->assertArrayHasKey('is_pep', $results);
        $this->assertArrayHasKey('total_matches', $results);
        $this->assertArrayHasKey('matches', $results);
        $this->assertIsArray($results['matches']);
        $this->assertFalse($results['is_pep']);
    }

    #[Test]
    public function test_perform_adverse_media_screening(): void
    {
        Http::fake([
            '*' => Http::response(['articles' => []], 200),
        ]);

        $results = $this->service->performAdverseMediaCheck(['name' => 'Test User']);

        $this->assertArrayHasKey('has_adverse_media', $results);
        $this->assertArrayHasKey('total_matches', $results);
        $this->assertArrayHasKey('articles', $results);
        $this->assertIsArray($results['articles']);
        $this->assertFalse($results['has_adverse_media']);
    }

    #[Test]
    public function test_calculate_overall_risk_with_no_matches(): void
    {
        $sanctionsResults = ['total_matches' => 0];
        $pepResults = ['total_matches' => 0];
        $adverseMediaResults = ['total_matches' => 0];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOverallRisk');
        $method->setAccessible(true);

        $risk = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals('low', $risk);
    }

    #[Test]
    public function test_calculate_overall_risk_with_sanctions_match(): void
    {
        $sanctionsResults = ['total_matches' => 1];
        $pepResults = ['total_matches' => 0];
        $adverseMediaResults = ['total_matches' => 0];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOverallRisk');
        $method->setAccessible(true);

        $risk = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals('critical', $risk);
    }

    #[Test]
    public function test_calculate_overall_risk_with_pep_match(): void
    {
        $sanctionsResults = ['total_matches' => 0];
        $pepResults = ['is_pep' => true];
        $adverseMediaResults = ['total_matches' => 0];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOverallRisk');
        $method->setAccessible(true);

        $risk = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals('high', $risk);
    }

    #[Test]
    public function test_calculate_overall_risk_with_adverse_media(): void
    {
        $sanctionsResults = ['total_matches' => 0];
        $pepResults = ['is_pep' => false];
        $adverseMediaResults = ['has_adverse_media' => true, 'serious_allegations' => 0];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOverallRisk');
        $method->setAccessible(true);

        $risk = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals('medium', $risk);
    }

    #[Test]
    public function test_calculate_overall_risk_with_serious_adverse_media(): void
    {
        $sanctionsResults = ['total_matches' => 0];
        $pepResults = ['is_pep' => false];
        $adverseMediaResults = ['serious_allegations' => 2];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOverallRisk');
        $method->setAccessible(true);

        $risk = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals('high', $risk);
    }

    #[Test]
    public function test_count_total_matches(): void
    {
        $sanctionsResults = ['total_matches' => 2];
        $pepResults = ['total_matches' => 1];
        $adverseMediaResults = ['total_matches' => 3];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('countTotalMatches');
        $method->setAccessible(true);

        $total = $method->invoke($this->service, $sanctionsResults, $pepResults, $adverseMediaResults);

        $this->assertEquals(6, $total);
    }

    #[Test]
    public function test_generate_unique_screening_number(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('generateUniqueScreeningNumber');
        $method->setAccessible(true);

        $screeningNumber = $method->invoke($this->service);

        $this->assertStringStartsWith('AML-' . date('Y') . '-', $screeningNumber);
        $this->assertMatchesRegularExpression('/^AML-\d{4}-\d{5}$/', $screeningNumber);

        // When no screenings exist, it should generate the first number
        $this->assertEquals('AML-' . date('Y') . '-00001', $screeningNumber);
    }

    #[Test]
    public function test_build_search_parameters_for_user(): void
    {
        $user = User::factory()->make([
            'name'    => 'Test User',
            'country' => 'UK',
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSearchParameters');
        $method->setAccessible(true);

        $params = $method->invoke($this->service, $user);

        $this->assertEquals('Test User', $params['name']);
        $this->assertEquals('UK', $params['country']);
    }

    #[Test]
    public function test_build_search_parameters_with_additional_params(): void
    {
        $user = User::factory()->make(['name' => 'Test User']);
        $additionalParams = [
            'include_aliases' => true,
            'fuzzy_matching'  => true,
        ];

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('buildSearchParameters');
        $method->setAccessible(true);

        $params = $method->invoke($this->service, $user, $additionalParams);

        $this->assertEquals('Test User', $params['name']);
        $this->assertTrue($params['include_aliases']);
        $this->assertTrue($params['fuzzy_matching']);
    }

    #[Test]
    public function test_check_ofac_list_with_no_matches(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('checkOFACList');
        $method->setAccessible(true);

        $matches = $method->invoke($this->service, ['name' => 'John Smith']);

        $this->assertIsArray($matches);
        $this->assertEmpty($matches);
    }

    #[Test]
    public function test_check_ofac_list_with_test_match(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('checkOFACList');
        $method->setAccessible(true);

        // The service has test logic that matches on 'test' or 'sanctioned'
        $matches = $method->invoke($this->service, ['name' => 'Test Person']);

        $this->assertIsArray($matches);
        $this->assertNotEmpty($matches);
        $this->assertEquals('Test Person', $matches[0]['name']);
        $this->assertArrayHasKey('match_score', $matches[0]);
    }

    #[Test]
    public function test_check_pep_database_negative(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('checkPEPDatabase');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'John Smith', 'US');

        $this->assertFalse($result);
    }

    #[Test]
    public function test_check_pep_database_positive(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('checkPEPDatabase');
        $method->setAccessible(true);

        // The service has test logic that matches on certain keywords
        $result = $method->invoke($this->service, 'Senator Smith', 'US');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_search_adverse_media_no_results(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('searchAdverseMedia');
        $method->setAccessible(true);

        $results = $method->invoke($this->service, 'John Smith');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function test_search_adverse_media_with_results(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('searchAdverseMedia');
        $method->setAccessible(true);

        // The service has test logic that matches on 'fraud' or 'scandal'
        $results = $method->invoke($this->service, 'Fraud Person');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('title', $results[0]);
        $this->assertArrayHasKey('severity', $results[0]);
        $this->assertEquals('high', $results[0]['severity']);
    }
}
