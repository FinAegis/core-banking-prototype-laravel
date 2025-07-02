<?php

namespace Tests\Feature\Http\Controllers\Api\V2;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\KycVerification;
use App\Models\AmlScreening;
use App\Models\ComplianceDocument;
use App\Domain\Compliance\Services\KycService;
use App\Domain\Compliance\Services\AmlService;
use App\Domain\Compliance\Services\RiskAssessmentService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ComplianceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $apiPrefix = '/api/v2';
    protected $mockKycService;
    protected $mockAmlService;
    protected $mockRiskService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Mock compliance services
        $this->mockKycService = Mockery::mock(KycService::class);
        $this->mockAmlService = Mockery::mock(AmlService::class);
        $this->mockRiskService = Mockery::mock(RiskAssessmentService::class);
        
        $this->app->instance(KycService::class, $this->mockKycService);
        $this->app->instance(AmlService::class, $this->mockAmlService);
        $this->app->instance(RiskAssessmentService::class, $this->mockRiskService);
        
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_gets_kyc_status()
    {
        Sanctum::actingAs($this->user);
        
        $kycVerification = KycVerification::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'verified',
            'level' => 'advanced',
            'verified_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'status',
                'level',
                'verified_at',
                'expires_at',
                'limits' => [
                    'daily_transaction_limit',
                    'monthly_transaction_limit',
                    'max_balance',
                    'allowed_products',
                ],
                'required_documents',
                'completed_steps',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'status' => 'verified',
                'level' => 'advanced',
            ],
        ]);
    }

    /** @test */
    public function it_returns_unverified_status_for_new_users()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'status' => 'unverified',
                'level' => 'none',
                'limits' => [
                    'daily_transaction_limit' => 0,
                    'monthly_transaction_limit' => 0,
                    'max_balance' => 0,
                    'allowed_products' => [],
                ],
            ],
        ]);
    }

    /** @test */
    public function it_starts_kyc_verification()
    {
        Sanctum::actingAs($this->user);
        
        $verificationData = [
            'level' => 'basic',
            'personal_info' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'US',
                'tax_id' => '123-45-6789',
            ],
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'postal_code' => '10001',
                'country' => 'US',
            ],
        ];
        
        $this->mockKycService
            ->shouldReceive('startVerification')
            ->with($this->user->uuid, 'basic', Mockery::type('array'))
            ->once()
            ->andReturn([
                'verification_id' => 'kyc_123',
                'status' => 'pending',
                'next_steps' => ['upload_id_document', 'upload_proof_of_address'],
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", $verificationData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'verification_id',
                'status',
                'next_steps',
            ],
        ]);
        
        $this->assertDatabaseHas('kyc_verifications', [
            'user_uuid' => $this->user->uuid,
            'status' => 'pending',
            'level' => 'basic',
        ]);
    }

    /** @test */
    public function it_validates_kyc_verification_data()
    {
        Sanctum::actingAs($this->user);
        
        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['level', 'personal_info', 'address']);
        
        // Invalid level
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", [
            'level' => 'invalid',
            'personal_info' => ['first_name' => 'John'],
            'address' => ['street' => '123 Main'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['level']);
        
        // Invalid date format
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", [
            'level' => 'basic',
            'personal_info' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => 'invalid-date',
            ],
            'address' => ['street' => '123 Main'],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['personal_info.date_of_birth']);
    }

    /** @test */
    public function it_uploads_kyc_document()
    {
        Sanctum::actingAs($this->user);
        
        $verification = KycVerification::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'pending',
        ]);
        
        $file = UploadedFile::fake()->image('passport.jpg', 1200, 800);
        
        $this->mockKycService
            ->shouldReceive('processDocument')
            ->once()
            ->andReturn([
                'document_id' => 'doc_123',
                'type' => 'passport',
                'status' => 'processing',
                'extracted_data' => null,
            ]);
        
        $response = $this->postJson(
            "{$this->apiPrefix}/compliance/kyc/{$verification->uuid}/document",
            [
                'document_type' => 'passport',
                'document' => $file,
                'side' => 'front',
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'document_id',
                'type',
                'status',
                'uploaded_at',
            ],
        ]);
        
        Storage::disk('local')->assertExists('kyc-documents/' . $this->user->uuid);
    }

    /** @test */
    public function it_validates_document_upload()
    {
        Sanctum::actingAs($this->user);
        
        // No verification started
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/dummy-verification-id/document", [
            'document_type' => 'passport',
            'document' => UploadedFile::fake()->image('test.jpg'),
        ]);
        $response->assertStatus(400);
        
        $verification = KycVerification::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'pending',
        ]);
        
        // Missing file
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->uuid}/document", [
            'document_type' => 'passport',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);
        
        // Invalid file type
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->uuid}/document", [
            'document_type' => 'passport',
            'document' => UploadedFile::fake()->create('test.txt', 100),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);
        
        // File too large
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->uuid}/document", [
            'document_type' => 'passport',
            'document' => UploadedFile::fake()->image('large.jpg')->size(11000), // 11MB
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_uploads_selfie_for_biometric_verification()
    {
        Sanctum::actingAs($this->user);
        
        $verification = KycVerification::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'documents_verified',
        ]);
        
        $selfie = UploadedFile::fake()->image('selfie.jpg', 640, 480);
        
        $this->mockKycService
            ->shouldReceive('processBiometric')
            ->once()
            ->andReturn([
                'verification_id' => 'bio_123',
                'status' => 'processing',
                'confidence_score' => null,
            ]);
        
        $response = $this->postJson(
            "{$this->apiPrefix}/compliance/kyc/{$verification->uuid}/selfie",
            [
                'selfie' => $selfie,
                'liveness_data' => [
                    'challenge_response' => 'blink_twice',
                    'timestamp' => now()->toISOString(),
                ],
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'verification_id',
                'status',
                'message',
            ],
        ]);
    }

    /** @test */
    public function it_gets_aml_screening_status()
    {
        Sanctum::actingAs($this->user);
        
        $screening = AmlScreening::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'clear',
            'screened_at' => now(),
            'results' => [
                'pep' => false,
                'sanctions' => false,
                'adverse_media' => false,
                'risk_score' => 10,
            ],
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/aml/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'status',
                'last_screened_at',
                'next_screening_due',
                'results' => [
                    'pep',
                    'sanctions',
                    'adverse_media',
                    'risk_score',
                ],
                'monitoring_enabled',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'status' => 'clear',
                'results' => [
                    'pep' => false,
                    'sanctions' => false,
                    'adverse_media' => false,
                    'risk_score' => 10,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_requests_manual_aml_screening()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockAmlService
            ->shouldReceive('initiateScreening')
            ->with($this->user->uuid, 'manual')
            ->once()
            ->andReturn([
                'screening_id' => 'scr_123',
                'status' => 'processing',
                'estimated_completion' => now()->addMinutes(5)->toISOString(),
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/compliance/aml/request-screening", [
            'reason' => 'High value transaction',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => [
                'screening_id',
                'status',
                'estimated_completion',
                'message',
            ],
        ]);
    }

    /** @test */
    public function it_gets_risk_profile()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockRiskService
            ->shouldReceive('calculateRiskProfile')
            ->with($this->user->uuid)
            ->once()
            ->andReturn([
                'overall_risk' => 'medium',
                'risk_score' => 45,
                'factors' => [
                    'country_risk' => 20,
                    'product_risk' => 15,
                    'behavior_risk' => 10,
                ],
                'last_assessment' => now()->toISOString(),
                'next_review' => now()->addMonths(6)->toISOString(),
            ]);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/risk-profile");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'overall_risk',
                'risk_score',
                'factors' => [
                    'country_risk',
                    'product_risk',
                    'behavior_risk',
                ],
                'last_assessment',
                'next_review',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'overall_risk' => 'medium',
                'risk_score' => 45,
            ],
        ]);
    }

    /** @test */
    public function it_checks_transaction_eligibility()
    {
        Sanctum::actingAs($this->user);
        
        $transactionData = [
            'type' => 'wire_transfer',
            'amount' => 50000, // 500.00 EUR
            'currency' => 'EUR',
            'destination_country' => 'US',
            'purpose' => 'business_payment',
        ];
        
        $this->mockRiskService
            ->shouldReceive('checkTransactionEligibility')
            ->with($this->user->uuid, Mockery::type('array'))
            ->once()
            ->andReturn([
                'eligible' => true,
                'requires_additional_verification' => false,
                'restrictions' => [],
                'warnings' => [],
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", $transactionData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'eligible',
                'requires_additional_verification',
                'restrictions',
                'warnings',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'eligible' => true,
                'requires_additional_verification' => false,
            ],
        ]);
    }

    /** @test */
    public function it_blocks_high_risk_transactions()
    {
        Sanctum::actingAs($this->user);
        
        $transactionData = [
            'type' => 'wire_transfer',
            'amount' => 10000000, // 100,000.00 EUR
            'currency' => 'EUR',
            'destination_country' => 'NG', // High-risk country
            'purpose' => 'other',
        ];
        
        $this->mockRiskService
            ->shouldReceive('checkTransactionEligibility')
            ->once()
            ->andReturn([
                'eligible' => false,
                'requires_additional_verification' => true,
                'restrictions' => [
                    'HIGH_RISK_COUNTRY' => 'Destination country is on high-risk list',
                    'AMOUNT_LIMIT_EXCEEDED' => 'Transaction amount exceeds your limit',
                ],
                'warnings' => [
                    'ENHANCED_DUE_DILIGENCE_REQUIRED' => 'Additional documentation required',
                ],
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", $transactionData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'eligible' => false,
                'requires_additional_verification' => true,
            ],
        ]);
        $response->assertJsonCount(2, 'data.restrictions');
    }

    /** @test */
    public function it_validates_transaction_check_request()
    {
        Sanctum::actingAs($this->user);
        
        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type', 'amount', 'currency']);
        
        // Invalid transaction type
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", [
            'type' => 'invalid_type',
            'amount' => 1000,
            'currency' => 'EUR',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
        
        // Negative amount
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", [
            'type' => 'wire_transfer',
            'amount' => -100,
            'currency' => 'EUR',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");
        $response->assertStatus(401);
        
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", []);
        $response->assertStatus(401);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/aml/status");
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_kyc_verification_expiry()
    {
        Sanctum::actingAs($this->user);
        
        $expiredVerification = KycVerification::factory()->create([
            'user_uuid' => $this->user->uuid,
            'status' => 'verified',
            'verified_at' => now()->subYears(2),
            'expires_at' => now()->subDay(),
        ]);
        
        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'status' => 'expired',
                'requires_reverification' => true,
            ],
        ]);
    }
}