<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RegTech\Services;

use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\RegTech\Services\JurisdictionConfigurationService;
use Tests\TestCase;

class JurisdictionConfigurationServiceTest extends TestCase
{
    private JurisdictionConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JurisdictionConfigurationService();
    }

    public function test_get_jurisdiction_config_returns_config(): void
    {
        $config = $this->service->getJurisdictionConfig(Jurisdiction::US);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('currency', $config);
        $this->assertArrayHasKey('regulators', $config);
    }

    public function test_get_jurisdiction_config_with_string(): void
    {
        $config = $this->service->getJurisdictionConfig('US');

        $this->assertIsArray($config);
        $this->assertEquals('United States', $config['name']);
    }

    public function test_get_all_jurisdictions(): void
    {
        $jurisdictions = $this->service->getAllJurisdictions();

        $this->assertIsArray($jurisdictions);
        $this->assertArrayHasKey('US', $jurisdictions);
        $this->assertArrayHasKey('EU', $jurisdictions);
        $this->assertArrayHasKey('UK', $jurisdictions);
        $this->assertArrayHasKey('SG', $jurisdictions);
    }

    public function test_get_regulators(): void
    {
        $regulators = $this->service->getRegulators(Jurisdiction::US);

        $this->assertIsArray($regulators);
        // Config uses lowercase keys
        $this->assertArrayHasKey('fincen', $regulators);
    }

    public function test_get_regulator_config(): void
    {
        // Config uses lowercase keys
        $config = $this->service->getRegulatorConfig(Jurisdiction::US, 'fincen');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('reports', $config);
    }

    public function test_get_regulator_config_returns_null_for_invalid(): void
    {
        $config = $this->service->getRegulatorConfig(Jurisdiction::US, 'InvalidRegulator');

        $this->assertNull($config);
    }

    public function test_get_supported_report_types(): void
    {
        // Config uses lowercase keys
        $reports = $this->service->getSupportedReportTypes(Jurisdiction::US, 'fincen');

        $this->assertIsArray($reports);
        $this->assertContains('CTR', $reports);
        $this->assertContains('SAR', $reports);
    }

    public function test_get_currency_from_enum(): void
    {
        $this->assertEquals('USD', $this->service->getCurrency(Jurisdiction::US));
        $this->assertEquals('EUR', $this->service->getCurrency(Jurisdiction::EU));
        $this->assertEquals('GBP', $this->service->getCurrency(Jurisdiction::UK));
        $this->assertEquals('SGD', $this->service->getCurrency(Jurisdiction::SG));
    }

    public function test_get_currency_from_string(): void
    {
        $this->assertEquals('USD', $this->service->getCurrency('US'));
    }

    public function test_get_timezone_from_enum(): void
    {
        $this->assertEquals('America/New_York', $this->service->getTimezone(Jurisdiction::US));
        $this->assertEquals('Europe/Paris', $this->service->getTimezone(Jurisdiction::EU));
        $this->assertEquals('Europe/London', $this->service->getTimezone(Jurisdiction::UK));
        $this->assertEquals('Asia/Singapore', $this->service->getTimezone(Jurisdiction::SG));
    }

    public function test_get_ctr_threshold(): void
    {
        $threshold = $this->service->getCtrThreshold(Jurisdiction::US);

        $this->assertIsFloat($threshold);
        $this->assertEquals(10000.0, $threshold);
    }

    public function test_is_mifid_applicable(): void
    {
        $this->assertTrue($this->service->isMifidApplicable(Jurisdiction::EU));
        $this->assertTrue($this->service->isMifidApplicable(Jurisdiction::UK));
        $this->assertFalse($this->service->isMifidApplicable(Jurisdiction::US));
        $this->assertFalse($this->service->isMifidApplicable(Jurisdiction::SG));
    }

    public function test_is_mica_applicable(): void
    {
        $this->assertTrue($this->service->isMicaApplicable(Jurisdiction::EU));
        $this->assertFalse($this->service->isMicaApplicable(Jurisdiction::UK));
        $this->assertFalse($this->service->isMicaApplicable(Jurisdiction::US));
        $this->assertFalse($this->service->isMicaApplicable(Jurisdiction::SG));
    }

    public function test_get_api_base_url(): void
    {
        // Config uses lowercase keys
        $url = $this->service->getApiBaseUrl(Jurisdiction::US, 'fincen');

        $this->assertNotNull($url);
        $this->assertStringContainsString('fincen', strtolower($url));
    }

    public function test_get_jurisdiction_by_currency(): void
    {
        $this->assertEquals(Jurisdiction::US, $this->service->getJurisdictionByCurrency('USD'));
        $this->assertEquals(Jurisdiction::EU, $this->service->getJurisdictionByCurrency('EUR'));
        $this->assertEquals(Jurisdiction::UK, $this->service->getJurisdictionByCurrency('GBP'));
        $this->assertEquals(Jurisdiction::SG, $this->service->getJurisdictionByCurrency('SGD'));
    }

    public function test_get_jurisdiction_by_currency_returns_null_for_unknown(): void
    {
        $this->assertNull($this->service->getJurisdictionByCurrency('XYZ'));
    }

    public function test_determine_applicable_jurisdictions(): void
    {
        $jurisdictions = $this->service->determineApplicableJurisdictions(
            'USD',
            'EUR',
            'US',
            'DE'
        );

        $this->assertIsArray($jurisdictions);
        $this->assertCount(2, $jurisdictions);
        $this->assertContainsOnlyInstancesOf(Jurisdiction::class, $jurisdictions);
    }

    public function test_determine_applicable_jurisdictions_by_currency_only(): void
    {
        $jurisdictions = $this->service->determineApplicableJurisdictions('USD', 'GBP');

        $this->assertCount(2, $jurisdictions);
    }

    public function test_determine_applicable_jurisdictions_with_country_mapping(): void
    {
        $jurisdictions = $this->service->determineApplicableJurisdictions(
            'EUR',
            'EUR',
            'DE',
            'FR'
        );

        // Both DE and FR map to EU, so should only have 1 unique jurisdiction
        $this->assertCount(1, $jurisdictions);
        $this->assertEquals(Jurisdiction::EU, $jurisdictions[0]);
    }

    public function test_get_mifid_config(): void
    {
        $config = $this->service->getMifidConfig();

        $this->assertIsArray($config);
    }

    public function test_get_mica_config(): void
    {
        $config = $this->service->getMicaConfig();

        $this->assertIsArray($config);
    }

    public function test_get_ml_monitoring_config(): void
    {
        $config = $this->service->getMlMonitoringConfig();

        $this->assertIsArray($config);
    }
}
