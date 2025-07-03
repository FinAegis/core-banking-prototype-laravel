<?php

namespace Tests\Unit\Services\Cgo;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\User;
use App\Services\Cgo\InvestmentAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Barryvdh\DomPDF\Facade\Pdf;

class InvestmentAgreementServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvestmentAgreementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        $this->service = new InvestmentAgreementService();
    }

    public function test_generate_agreement_creates_pdf_and_updates_investment()
    {
        // Create test data
        $user = User::factory()->create();
        $round = CgoPricingRound::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'round_id' => $round->id,
            'amount' => 5000,
            'tier' => 'silver',
            'status' => 'confirmed',
        ]);
        
        // Mock PDF generation
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('cgo.agreements.investment-agreement', \Mockery::type('array'))
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('A4', 'portrait')->andReturnSelf();
        Pdf::shouldReceive('setOption')->twice()->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturn('pdf content');
        
        // Generate agreement
        $path = $this->service->generateAgreement($investment);
        
        // Assertions
        $this->assertStringContainsString('cgo/agreements/', $path);
        $this->assertStringContainsString('.pdf', $path);
        
        // Check file was saved
        Storage::assertExists($path);
        
        // Check investment was updated
        $investment->refresh();
        $this->assertEquals($path, $investment->agreement_path);
        $this->assertNotNull($investment->agreement_generated_at);
    }

    public function test_generate_certificate_requires_confirmed_investment()
    {
        $investment = CgoInvestment::factory()->create([
            'status' => 'pending',
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Investment must be confirmed to generate certificate');
        
        $this->service->generateCertificate($investment);
    }

    public function test_generate_certificate_creates_pdf_with_certificate_number()
    {
        // Create test data
        $user = User::factory()->create();
        $round = CgoPricingRound::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'round_id' => $round->id,
            'amount' => 10000,
            'tier' => 'gold',
            'status' => 'confirmed',
            'payment_completed_at' => now(),
        ]);
        
        // Mock PDF generation
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('cgo.agreements.investment-certificate', \Mockery::type('array'))
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('A4', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('setOption')->twice()->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturn('pdf content');
        
        // Generate certificate
        $path = $this->service->generateCertificate($investment);
        
        // Assertions
        $this->assertStringContainsString('cgo/certificates/', $path);
        $this->assertStringContainsString('.pdf', $path);
        
        // Check file was saved
        Storage::assertExists($path);
        
        // Check investment was updated
        $investment->refresh();
        $this->assertEquals($path, $investment->certificate_path);
        $this->assertNotNull($investment->certificate_number);
        $this->assertNotNull($investment->certificate_issued_at);
        $this->assertStringStartsWith('CGO-', $investment->certificate_number);
    }

    public function test_prepare_agreement_data_includes_all_required_fields()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $round = CgoPricingRound::factory()->create([
            'name' => 'Series A',
            'pre_money_valuation' => 1000000,
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'round_id' => $round->id,
            'amount' => 25000,
            'currency' => 'USD',
            'shares_purchased' => 2500,
            'share_price' => 10,
            'ownership_percentage' => 2.5,
            'tier' => 'silver',
        ]);
        
        // Use reflection to test protected method
        $method = new \ReflectionMethod($this->service, 'prepareAgreementData');
        $method->setAccessible(true);
        
        $data = $method->invoke($this->service, $investment);
        
        // Check investor data
        $this->assertEquals('John Doe', $data['investor']['name']);
        $this->assertEquals('john@example.com', $data['investor']['email']);
        
        // Check investment details
        $this->assertEquals(25000, $data['investment_details']['amount']);
        $this->assertEquals('USD', $data['investment_details']['currency']);
        $this->assertEquals(2500, $data['investment_details']['shares']);
        $this->assertEquals(10, $data['investment_details']['share_price']);
        $this->assertEquals(2.5, $data['investment_details']['ownership_percentage']);
        $this->assertEquals('Silver', $data['investment_details']['tier']);
        $this->assertEquals('Series A', $data['investment_details']['round_name']);
        $this->assertEquals(1000000, $data['investment_details']['valuation']);
        
        // Check terms
        $this->assertArrayHasKey('lock_in_period', $data['terms']);
        $this->assertArrayHasKey('dividend_rights', $data['terms']);
        $this->assertArrayHasKey('voting_rights', $data['terms']);
        
        // Check risks
        $this->assertIsArray($data['risks']);
        $this->assertNotEmpty($data['risks']);
    }

    public function test_get_investment_terms_returns_tier_specific_terms()
    {
        $method = new \ReflectionMethod($this->service, 'getInvestmentTerms');
        $method->setAccessible(true);
        
        // Test bronze tier
        $bronzeInvestment = CgoInvestment::factory()->make(['tier' => 'bronze']);
        $bronzeTerms = $method->invoke($this->service, $bronzeInvestment);
        $this->assertEquals('None', $bronzeTerms['dilution_protection']);
        $this->assertEquals('Annual financial statements', $bronzeTerms['information_rights']);
        
        // Test silver tier
        $silverInvestment = CgoInvestment::factory()->make(['tier' => 'silver']);
        $silverTerms = $method->invoke($this->service, $silverInvestment);
        $this->assertEquals('None', $silverTerms['dilution_protection']);
        $this->assertEquals('Semi-annual financial statements', $silverTerms['information_rights']);
        
        // Test gold tier
        $goldInvestment = CgoInvestment::factory()->make(['tier' => 'gold']);
        $goldTerms = $method->invoke($this->service, $goldInvestment);
        $this->assertEquals('Anti-dilution protection for first 24 months', $goldTerms['dilution_protection']);
        $this->assertEquals('Quarterly financial statements and board updates', $goldTerms['information_rights']);
        $this->assertArrayHasKey('board_observer', $goldTerms);
    }

    public function test_generate_filename_creates_unique_filename()
    {
        $investment = CgoInvestment::factory()->create([
            'uuid' => '12345678-1234-1234-1234-123456789012',
            'tier' => 'gold',
        ]);
        
        $method = new \ReflectionMethod($this->service, 'generateFilename');
        $method->setAccessible(true);
        
        $filename = $method->invoke($this->service, $investment);
        
        $this->assertStringStartsWith('agreement_gold_12345678_', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}