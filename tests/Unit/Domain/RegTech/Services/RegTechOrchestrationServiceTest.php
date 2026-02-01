<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RegTech\Services;

use App\Domain\RegTech\Contracts\RegulatoryFilingAdapterInterface;
use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\RegTech\Models\FilingSchedule;
use App\Domain\RegTech\Models\RegulatoryEndpoint;
use App\Domain\RegTech\Services\JurisdictionConfigurationService;
use App\Domain\RegTech\Services\RegTechOrchestrationService;
use App\Domain\RegTech\Services\RegulatoryCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RegTechOrchestrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RegTechOrchestrationService $service;

    private JurisdictionConfigurationService $jurisdictionService;

    private RegulatoryCalendarService $calendarService;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-02-01 10:00:00'));

        $this->jurisdictionService = new JurisdictionConfigurationService();
        $this->calendarService = new RegulatoryCalendarService($this->jurisdictionService);
        $this->service = new RegTechOrchestrationService(
            $this->jurisdictionService,
            $this->calendarService
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_adapter(): void
    {
        $adapter = Mockery::mock(RegulatoryFilingAdapterInterface::class);

        $this->service->registerAdapter('us_sar', $adapter);

        $this->assertSame($adapter, $this->service->getAdapter('us_sar'));
    }

    public function test_get_adapter_returns_null_for_unregistered(): void
    {
        $this->assertNull($this->service->getAdapter('nonexistent'));
    }

    public function test_get_all_adapters(): void
    {
        $adapter1 = Mockery::mock(RegulatoryFilingAdapterInterface::class);
        $adapter2 = Mockery::mock(RegulatoryFilingAdapterInterface::class);

        $this->service->registerAdapter('us_sar', $adapter1);
        $this->service->registerAdapter('eu_mifid', $adapter2);

        $adapters = $this->service->getAdapters();

        $this->assertCount(2, $adapters);
        $this->assertArrayHasKey('us_sar', $adapters);
        $this->assertArrayHasKey('eu_mifid', $adapters);
    }

    public function test_is_enabled(): void
    {
        config(['regtech.enabled' => true]);
        $this->assertTrue($this->service->isEnabled());

        config(['regtech.enabled' => false]);
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_is_demo_mode(): void
    {
        config(['regtech.demo_mode' => true]);
        $this->assertTrue($this->service->isDemoMode());

        config(['regtech.demo_mode' => false]);
        $this->assertFalse($this->service->isDemoMode());
    }

    public function test_submit_report_returns_error_when_disabled(): void
    {
        config(['regtech.enabled' => false]);

        $result = $this->service->submitReport('SAR', Jurisdiction::US, ['data' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertContains('RegTech automation is disabled', $result['errors']);
    }

    public function test_submit_report_demo_mode(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => true]);

        $result = $this->service->submitReport(
            'SAR',
            Jurisdiction::US,
            ['amount' => 15000, 'account' => 'ACC123']
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['reference']);
        $this->assertStringStartsWith('DEMO-', $result['reference']);
        $this->assertTrue($result['details']['demo_mode']);
    }

    public function test_submit_report_demo_mode_with_string_jurisdiction(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => true]);

        $result = $this->service->submitReport('CTR', 'US', ['data' => 'test']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('US', $result['reference']);
    }

    public function test_submit_report_no_adapter_returns_error(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => false]);

        $result = $this->service->submitReport('SAR', Jurisdiction::US, ['data' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No adapter configured', $result['errors'][0]);
    }

    public function test_submit_report_with_adapter(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => false]);

        $adapter = Mockery::mock(RegulatoryFilingAdapterInterface::class);
        $adapter->shouldReceive('validateReport')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);
        $adapter->shouldReceive('submitReport')
            ->once()
            ->andReturn([
                'success'   => true,
                'reference' => 'REF-123',
                'errors'    => [],
                'response'  => ['status' => 'accepted'],
            ]);

        $this->service->registerAdapter('us_sar', $adapter);

        $result = $this->service->submitReport('SAR', Jurisdiction::US, ['data' => 'test']);

        $this->assertTrue($result['success']);
        $this->assertEquals('REF-123', $result['reference']);
    }

    public function test_submit_report_validation_failure(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => false]);

        $adapter = Mockery::mock(RegulatoryFilingAdapterInterface::class);
        $adapter->shouldReceive('validateReport')
            ->once()
            ->andReturn([
                'valid'  => false,
                'errors' => ['Missing required field: amount'],
            ]);

        $this->service->registerAdapter('us_sar', $adapter);

        $result = $this->service->submitReport('SAR', Jurisdiction::US, []);

        $this->assertFalse($result['success']);
        $this->assertContains('Missing required field: amount', $result['errors']);
        $this->assertTrue($result['details']['validation_failed']);
    }

    public function test_submit_report_exception_handling(): void
    {
        config(['regtech.enabled' => true]);
        config(['regtech.demo_mode' => false]);

        $adapter = Mockery::mock(RegulatoryFilingAdapterInterface::class);
        $adapter->shouldReceive('validateReport')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);
        $adapter->shouldReceive('submitReport')
            ->once()
            ->andThrow(new RuntimeException('Connection timeout'));

        $this->service->registerAdapter('us_sar', $adapter);

        $result = $this->service->submitReport('SAR', Jurisdiction::US, ['data' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertContains('Connection timeout', $result['errors']);
        $this->assertTrue($result['details']['exception']);
    }

    public function test_check_report_status_demo_mode(): void
    {
        config(['regtech.demo_mode' => true]);

        $result = $this->service->checkReportStatus('DEMO-US-SAR-123', Jurisdiction::US);

        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['pending', 'processing', 'accepted']);
        $this->assertTrue($result['details']['demo_mode']);
    }

    public function test_check_report_status_no_adapter(): void
    {
        config(['regtech.demo_mode' => false]);

        $result = $this->service->checkReportStatus('REF-123', Jurisdiction::US);

        $this->assertEquals('unknown', $result['status']);
        $this->assertStringContainsString('No adapter configured', $result['message']);
    }

    public function test_check_report_status_with_adapter(): void
    {
        config(['regtech.demo_mode' => false]);

        $adapter = Mockery::mock(RegulatoryFilingAdapterInterface::class);
        $adapter->shouldReceive('checkStatus')
            ->once()
            ->with('REF-123')
            ->andReturn([
                'status'  => 'accepted',
                'message' => 'Report accepted',
                'details' => ['timestamp' => '2026-02-01'],
            ]);

        $this->service->registerAdapter('us', $adapter);

        $result = $this->service->checkReportStatus('REF-123', Jurisdiction::US);

        $this->assertEquals('accepted', $result['status']);
    }

    public function test_get_compliance_summary(): void
    {
        FilingSchedule::create([
            'name'          => 'Test Schedule',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->addDays(10),
            'is_active'     => true,
        ]);

        config(['regtech.demo_mode' => true]);

        $summary = $this->service->getComplianceSummary();

        $this->assertArrayHasKey('total_scheduled', $summary);
        $this->assertArrayHasKey('upcoming_deadlines', $summary);
        $this->assertArrayHasKey('overdue_filings', $summary);
        $this->assertArrayHasKey('demo_mode', $summary);
        $this->assertEquals('all', $summary['jurisdiction']);
    }

    public function test_get_compliance_summary_filtered_by_jurisdiction(): void
    {
        FilingSchedule::create([
            'name'         => 'US Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
            'is_active'    => true,
        ]);

        FilingSchedule::create([
            'name'         => 'EU Schedule',
            'report_type'  => 'MiFID',
            'jurisdiction' => 'EU',
            'is_active'    => true,
        ]);

        $summary = $this->service->getComplianceSummary(Jurisdiction::US);

        $this->assertEquals('US', $summary['jurisdiction']);
    }

    public function test_get_endpoint_health(): void
    {
        RegulatoryEndpoint::create([
            'name'          => 'FinCEN API',
            'regulator'     => 'FinCEN',
            'jurisdiction'  => 'US',
            'base_url'      => 'https://api.fincen.gov',
            'health_status' => 'healthy',
            'is_active'     => true,
        ]);

        $health = $this->service->getEndpointHealth();

        $this->assertArrayHasKey('FinCEN API', $health);
        $this->assertEquals('healthy', $health['FinCEN API']['status']);
    }

    public function test_get_applicable_regulations(): void
    {
        $regulations = $this->service->getApplicableRegulations(
            15000.0,
            'USD',
            'wire_transfer'
        );

        $this->assertIsArray($regulations);
        $this->assertArrayHasKey('ctr', $regulations);
        $this->assertTrue($regulations['ctr']['required']);
    }

    public function test_get_applicable_regulations_below_threshold(): void
    {
        $regulations = $this->service->getApplicableRegulations(
            5000.0,
            'USD',
            'wire_transfer'
        );

        // Below CTR threshold, should not require CTR
        $this->assertArrayNotHasKey('ctr', $regulations);
    }

    public function test_get_applicable_regulations_mifid(): void
    {
        $regulations = $this->service->getApplicableRegulations(
            15000.0,
            'EUR',
            'security_trade'
        );

        $this->assertArrayHasKey('mifid', $regulations);
        $this->assertTrue($regulations['mifid']['required']);
    }

    public function test_get_applicable_regulations_mica_crypto(): void
    {
        $regulations = $this->service->getApplicableRegulations(
            2000.0,
            'EUR',
            'crypto_transfer',
            ['is_crypto' => true]
        );

        $this->assertArrayHasKey('mica_travel_rule', $regulations);
    }

    public function test_get_applicable_regulations_unknown_currency(): void
    {
        $regulations = $this->service->getApplicableRegulations(
            15000.0,
            'XYZ',
            'wire_transfer'
        );

        $this->assertEmpty($regulations);
    }
}
