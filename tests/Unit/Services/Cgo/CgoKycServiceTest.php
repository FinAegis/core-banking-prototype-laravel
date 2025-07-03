<?php

namespace Tests\Unit\Services\Cgo;

use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\EnhancedKycService;
use App\Domain\Compliance\Services\KycService;
use App\Models\CgoInvestment;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Cgo\CgoKycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CgoKycServiceTest extends TestCase
{
    use RefreshDatabase;

    private CgoKycService $service;
    private $kycService;
    private $riskService;
    private $enhancedKycService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->kycService = Mockery::mock(KycService::class);
        $this->riskService = Mockery::mock(CustomerRiskService::class);
        $this->enhancedKycService = Mockery::mock(EnhancedKycService::class);
        
        // Set default mock expectations that all tests will need
        $this->kycService->shouldReceive('checkExpiredKyc')
            ->withAnyArgs()
            ->andReturn(false)
            ->byDefault();
            
        // Default mock for risk service
        $this->riskService->shouldReceive('calculateRiskScore')
            ->withAnyArgs()
            ->andReturn(0.0)
            ->byDefault();
            
        $this->service = new CgoKycService(
            $this->kycService,
            $this->riskService,
            $this->enhancedKycService
        );
    }

    public function test_check_kyc_requirements_for_basic_level()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'basic',
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 500, // Below basic threshold
        ]);
        
        $this->kycService->shouldReceive('getRequirements')
            ->with('basic')
            ->andReturn(['documents' => ['national_id', 'selfie']]);
        
        $result = $this->service->checkKycRequirements($investment);
        
        $this->assertEquals('basic', $result['required_level']);
        $this->assertEquals('basic', $result['current_level']);
        $this->assertTrue($result['is_sufficient']);
        $this->assertEquals(['national_id', 'selfie'], $result['required_documents']);
    }

    public function test_check_kyc_requirements_for_enhanced_level()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'basic',
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 5000, // Above basic, below enhanced threshold
        ]);
        
        $this->kycService->shouldReceive('getRequirements')
            ->with('enhanced')
            ->andReturn(['documents' => ['passport', 'utility_bill', 'selfie']]);
        
        $result = $this->service->checkKycRequirements($investment);
        
        $this->assertEquals('enhanced', $result['required_level']);
        $this->assertEquals('basic', $result['current_level']);
        $this->assertFalse($result['is_sufficient']);
    }

    public function test_check_kyc_requirements_for_full_level()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'enhanced',
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 75000, // Above full threshold
        ]);
        
        $this->kycService->shouldReceive('getRequirements')
            ->with('full')
            ->andReturn(['documents' => ['passport', 'utility_bill', 'bank_statement', 'selfie', 'proof_of_income']]);
        
        $result = $this->service->checkKycRequirements($investment);
        
        $this->assertEquals('full', $result['required_level']);
        $this->assertEquals('enhanced', $result['current_level']);
        $this->assertFalse($result['is_sufficient']);
    }

    public function test_verify_investor_blocks_insufficient_kyc()
    {
        $user = User::factory()->create([
            'kyc_status' => 'not_started',
            'kyc_level' => null,
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 2000,
            'status' => 'pending',
        ]);
        
        $this->kycService->shouldReceive('getRequirements')->andReturn(['documents' => []]);
        
        $result = $this->service->verifyInvestor($investment);
        
        $this->assertFalse($result);
        
        $investment->refresh();
        $this->assertEquals('kyc_required', $investment->status);
        $this->assertStringContainsString('requires enhanced KYC verification', $investment->notes);
    }

    public function test_verify_investor_performs_aml_checks_for_high_value()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'full',
            'pep_status' => false,
            'risk_rating' => 'low',
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 15000, // Above enhanced threshold
            'status' => 'pending',
        ]);
        
        $this->kycService->shouldReceive('getRequirements')->andReturn(['documents' => []]);
        $this->riskService->shouldReceive('calculateRiskScore')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn(25.5);
        
        $result = $this->service->verifyInvestor($investment);
        
        $this->assertTrue($result);
        
        $investment->refresh();
        $this->assertNotNull($investment->kyc_verified_at);
        $this->assertEquals('full', $investment->kyc_level);
        $this->assertEquals(25.5, $investment->risk_assessment);
    }

    public function test_verify_investor_flags_pep_status()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'full',
            'pep_status' => true, // PEP user
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 15000,
            'status' => 'pending',
        ]);
        
        $this->kycService->shouldReceive('getRequirements')->andReturn(['documents' => []]);
        
        $result = $this->service->verifyInvestor($investment);
        
        $this->assertFalse($result);
        
        $investment->refresh();
        $this->assertEquals('aml_review', $investment->status);
        $this->assertStringContainsString('pep_status', $investment->notes);
    }

    public function test_verify_investor_flags_sanctioned_country()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level' => 'full',
            'country_code' => 'IR', // Iran - sanctioned country
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 15000,
            'status' => 'pending',
        ]);
        
        $this->kycService->shouldReceive('getRequirements')->andReturn(['documents' => []]);
        
        // Force reload the investment to ensure user relationship is fresh
        $investment->load('user');
        
        $result = $this->service->verifyInvestor($investment);
        
        $investment->refresh();
        
        $this->assertFalse($result);
        $this->assertEquals('aml_review', $investment->status);
        $this->assertStringContainsString('sanctions_hit', $investment->notes);
    }

    public function test_check_transaction_patterns_flags_rapid_investments()
    {
        $user = User::factory()->create();
        
        // Create multiple recent investments
        CgoInvestment::factory()->count(4)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(3),
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 5000,
        ]);
        
        $method = new \ReflectionMethod($this->service, 'checkTransactionPatterns');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service, $user, $investment);
        
        $this->assertFalse($result['normal']);
        $this->assertEquals('rapid_successive_investments', $result['reason']);
    }

    public function test_determine_risk_level()
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(30), // New user
            'pep_status' => false,
            'country_code' => 'US',
        ]);
        
        $investment = CgoInvestment::factory()->create([
            'amount' => 60000, // High amount
        ]);
        
        $method = new \ReflectionMethod($this->service, 'determineRiskLevel');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service, $user, $investment);
        
        $this->assertEquals('medium', $result); // High amount + new user = 2 factors = medium risk
    }

    public function test_create_verification_request()
    {
        $user = User::factory()->create();
        $investment = CgoInvestment::factory()->create([
            'user_id' => $user->id,
            'amount' => 5000,
        ]);
        
        $verification = $this->service->createVerificationRequest($investment, 'enhanced');
        
        $this->assertInstanceOf(KycVerification::class, $verification);
        $this->assertEquals($user->id, $verification->user_id);
        $this->assertEquals(KycVerification::TYPE_IDENTITY, $verification->type);
        $this->assertEquals(KycVerification::STATUS_PENDING, $verification->status);
        $this->assertEquals($investment->id, $verification->verification_data['investment_id']);
        $this->assertEquals('enhanced', $verification->verification_data['required_level']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}