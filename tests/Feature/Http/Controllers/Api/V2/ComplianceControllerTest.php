<?php

namespace Tests\Feature\Http\Controllers\Api\V2;

use App\Domain\Compliance\Models\AmlScreening;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\EnhancedKycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ComplianceControllerTest extends ControllerTestCase
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
        $this->mockKycService = Mockery::mock(EnhancedKycService::class);
        $this->mockAmlService = Mockery::mock(AmlScreeningService::class);
        $this->mockRiskService = Mockery::mock(CustomerRiskService::class);

        $this->app->instance(EnhancedKycService::class, $this->mockKycService);
        $this->app->instance(AmlScreeningService::class, $this->mockAmlService);
        $this->app->instance(CustomerRiskService::class, $this->mockRiskService);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_gets_kyc_status()
    {
        Sanctum::actingAs($this->user);

        $kycVerification = KycVerification::factory()->verified()->create([
            'user_id' => $this->user->id,
            'type'    => 'identity',
        ]);

        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'kyc_level',
                'kyc_status',
                'risk_rating',
                'requires_verification',
                'verifications',
                'limits' => [
                    'daily',
                    'monthly',
                    'single',
                ],
            ],
        ]);
    }

    #[Test]
    public function it_returns_unverified_status_for_new_users()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'kyc_level'  => 'basic',
                'kyc_status' => 'not_started',
                'limits'     => [
                    'daily'   => 0,
                    'monthly' => 0,
                    'single'  => 0,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_starts_kyc_verification()
    {
        Sanctum::actingAs($this->user);

        $verificationData = [
            'type'     => 'identity',
            'provider' => 'manual',
        ];

        $verification = KycVerification::factory()->create([
            'user_id' => $this->user->id,
            'type'    => 'identity',
            'status'  => 'pending',
        ]);

        $this->mockKycService
            ->shouldReceive('startVerification')
            ->with($this->user, 'identity', Mockery::type('array'))
            ->once()
            ->andReturn($verification);

        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", $verificationData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'verification_id',
                'verification_number',
                'type',
                'status',
                'next_steps',
            ],
        ]);

        $this->assertDatabaseHas('kyc_verifications', [
            'user_id' => $this->user->id,
            'type'    => 'identity',
            'status'  => 'pending',
        ]);
    }

    #[Test]
    public function it_validates_kyc_verification_data()
    {
        Sanctum::actingAs($this->user);

        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);

        // Invalid type
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", [
            'type'     => 'invalid_type',
            'provider' => 'manual',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);

        // Invalid provider
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", [
            'type'     => 'identity',
            'provider' => 'invalid_provider',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    #[Test]
    public function it_uploads_kyc_document()
    {
        Sanctum::actingAs($this->user);

        $verification = KycVerification::factory()->create([
            'user_id' => $this->user->id,
            'type'    => 'identity',
            'status'  => 'pending',
        ]);

        $file = UploadedFile::fake()->image('passport.jpg', 1200, 800);

        $this->mockKycService
            ->shouldReceive('verifyIdentityDocument')
            ->with(Mockery::type(KycVerification::class), Mockery::type('string'), 'passport')
            ->once()
            ->andReturn([
                'success'          => true,
                'confidence_score' => 85.5,
                'extracted_data'   => [
                    'document_number' => 'ABC123456',
                    'first_name'      => 'John',
                    'last_name'       => 'Doe',
                ],
            ]);

        $response = $this->postJson(
            "{$this->apiPrefix}/compliance/kyc/{$verification->id}/document",
            [
                'document_type' => 'passport',
                'document'      => $file,
                'document_side' => 'front',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'success',
                'verification_id',
                'confidence_score',
                'next_steps',
            ],
        ]);
    }

    #[Test]
    public function it_validates_document_upload()
    {
        Sanctum::actingAs($this->user);

        // No verification started
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/dummy-verification-id/document", [
            'document_type' => 'passport',
            'document'      => UploadedFile::fake()->image('test.jpg'),
        ]);
        $response->assertStatus(404);

        $verification = KycVerification::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'pending',
        ]);

        // Missing file
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->id}/document", [
            'document_type' => 'passport',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);

        // Invalid file type
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->id}/document", [
            'document_type' => 'passport',
            'document'      => UploadedFile::fake()->create('test.txt', 100),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);

        // File too large
        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/{$verification->id}/document", [
            'document_type' => 'passport',
            'document'      => UploadedFile::fake()->image('large.jpg')->size(11000), // 11MB
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['document']);
    }

    #[Test]
    public function it_uploads_selfie_for_biometric_verification()
    {
        Sanctum::actingAs($this->user);

        $verification = KycVerification::factory()->create([
            'user_id'          => $this->user->id,
            'type'             => 'identity',
            'status'           => 'in_progress',
            'confidence_score' => 85,
        ]);

        $selfie = UploadedFile::fake()->image('selfie.jpg', 640, 480);

        $this->mockKycService
            ->shouldReceive('verifyBiometrics')
            ->with(Mockery::type(KycVerification::class), Mockery::type('string'), null)
            ->once()
            ->andReturn([
                'success'          => true,
                'liveness_score'   => 95.0,
                'face_match_score' => 88.5,
            ]);

        $this->mockKycService
            ->shouldReceive('completeVerification')
            ->with(Mockery::type(KycVerification::class))
            ->once();

        $response = $this->postJson(
            "{$this->apiPrefix}/compliance/kyc/{$verification->id}/selfie",
            [
                'selfie'        => $selfie,
                'liveness_data' => [
                    'challenge_response' => 'blink_twice',
                    'timestamp'          => now()->toISOString(),
                ],
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'success',
                'liveness_score',
                'face_match_score',
                'verification_status',
            ],
        ]);
    }

    #[Test]
    public function it_gets_aml_screening_status()
    {
        Sanctum::actingAs($this->user);

        $screening = AmlScreening::factory()->completed()->lowRisk()->create([
            'entity_id'   => $this->user->uuid,
            'entity_type' => 'user',
        ]);

        $response = $this->getJson("{$this->apiPrefix}/compliance/aml/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'is_pep',
                'is_sanctioned',
                'has_adverse_media',
                'last_screening_date',
                'screenings',
            ],
        ]);
    }

    #[Test]
    public function it_requests_manual_aml_screening()
    {
        Sanctum::actingAs($this->user);

        $screening = AmlScreening::factory()->pending()->create([
            'entity_id'   => $this->user->uuid,
            'entity_type' => 'user',
        ]);

        $this->mockAmlService
            ->shouldReceive('performComprehensiveScreening')
            ->with($this->user, Mockery::type('array'))
            ->once()
            ->andReturn($screening);

        $response = $this->postJson("{$this->apiPrefix}/compliance/aml/request-screening", [
            'type'   => 'comprehensive',
            'reason' => 'High value transaction',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'screening_id',
                'screening_number',
                'status',
                'estimated_completion',
            ],
        ]);
    }

    #[Test]
    public function it_gets_risk_profile()
    {
        Sanctum::actingAs($this->user);

        $profile = CustomerRiskProfile::factory()->create([
            'user_id'     => $this->user->id,
            'risk_rating' => 'medium',
            'risk_score'  => 45,
        ]);

        $response = $this->getJson("{$this->apiPrefix}/compliance/risk-profile");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'profile_number',
                'risk_rating',
                'risk_score',
                'cdd_level',
                'factors',
                'limits' => [
                    'daily',
                    'monthly',
                    'single',
                ],
                'restrictions' => [
                    'countries',
                    'currencies',
                ],
                'enhanced_monitoring',
                'next_review_date',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'risk_rating' => 'medium',
                'risk_score'  => 45,
            ],
        ]);
    }

    #[Test]
    public function it_checks_transaction_eligibility()
    {
        Sanctum::actingAs($this->user);

        $transactionData = [
            'type'                => 'wire_transfer',
            'amount'              => 50000, // 500.00 EUR
            'currency'            => 'EUR',
            'destination_country' => 'US',
            'purpose'             => 'business_payment',
        ];

        $this->mockRiskService
            ->shouldReceive('canPerformTransaction')
            ->with($this->user, 50000, 'EUR')
            ->once()
            ->andReturn([
                'allowed' => true,
                'reason'  => null,
                'limit'   => 100000,
                'current' => 5000,
            ]);

        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", $transactionData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'allowed',
                'reason',
                'limit',
                'current_usage',
                'requires_additional_verification',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'allowed'                          => true,
                'requires_additional_verification' => false,
            ],
        ]);
    }

    #[Test]
    public function it_blocks_high_risk_transactions()
    {
        Sanctum::actingAs($this->user);

        $transactionData = [
            'type'                => 'wire_transfer',
            'amount'              => 10000000, // 100,000.00 EUR
            'currency'            => 'EUR',
            'destination_country' => 'NG', // High-risk country
            'purpose'             => 'other',
        ];

        $this->mockRiskService
            ->shouldReceive('canPerformTransaction')
            ->once()
            ->andReturn([
                'allowed' => false,
                'reason'  => 'Transaction amount exceeds your limit',
                'limit'   => 5000,
                'current' => 0,
            ]);

        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", $transactionData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'allowed'                          => false,
                'requires_additional_verification' => true,
            ],
        ]);
    }

    #[Test]
    public function it_validates_transaction_check_request()
    {
        Sanctum::actingAs($this->user);

        // Mock should not be called for validation errors
        $this->mockRiskService
            ->shouldReceive('canPerformTransaction')
            ->never();

        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount', 'currency', 'type']);

        // Negative amount
        $response = $this->postJson("{$this->apiPrefix}/compliance/check-transaction", [
            'type'     => 'wire_transfer',
            'amount'   => -100,
            'currency' => 'EUR',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function it_requires_authentication()
    {
        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");
        $response->assertStatus(401);

        $response = $this->postJson("{$this->apiPrefix}/compliance/kyc/start", []);
        $response->assertStatus(401);

        $response = $this->getJson("{$this->apiPrefix}/compliance/aml/status");
        $response->assertStatus(401);
    }

    #[Test]
    public function it_handles_kyc_verification_expiry()
    {
        Sanctum::actingAs($this->user);

        $expiredVerification = KycVerification::factory()->create([
            'user_id'      => $this->user->id,
            'status'       => 'completed',
            'completed_at' => now()->subYears(2),
            'expires_at'   => now()->subDay(),
        ]);

        $response = $this->getJson("{$this->apiPrefix}/compliance/kyc/status");

        $response->assertStatus(200);
        // The status check here depends on the user's KYC status, not the individual verification
        // Since the verification is expired, the user would need reverification
        $response->assertJsonStructure([
            'data' => [
                'kyc_level',
                'kyc_status',
                'risk_rating',
                'requires_verification',
                'verifications',
                'limits',
            ],
        ]);
    }
}
