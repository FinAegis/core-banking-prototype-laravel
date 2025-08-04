<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class KycControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected KycService|\Mockery\MockInterface $kycService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'kyc_status' => 'not_started',
            'kyc_level'  => 'basic',
        ]);

        $this->kycService = \Mockery::mock(KycService::class);
        $this->app->instance(KycService::class, $this->kycService);

        Storage::fake('private');
    }

    #[Test]
    public function test_status_returns_user_kyc_status(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/compliance/kyc/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'level',
                'submitted_at',
                'approved_at',
                'expires_at',
                'needs_kyc',
                'documents',
            ])
            ->assertJson([
                'status'    => 'not_started',
                'level'     => 'basic',
                'needs_kyc' => true,
                'documents' => [],
            ]);
    }

    #[Test]
    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/kyc/status');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_requirements_returns_kyc_requirements_for_level(): void
    {
        Sanctum::actingAs($this->user);

        $this->kycService->shouldReceive('getRequirements')
            ->with('enhanced')
            ->once()
            ->andReturn([
                [
                    'document_type' => 'passport',
                    'description'   => 'Valid passport copy',
                    'required'      => true,
                ],
                [
                    'document_type' => 'utility_bill',
                    'description'   => 'Recent utility bill (within 3 months)',
                    'required'      => true,
                ],
            ]);

        $response = $this->getJson('/api/compliance/kyc/requirements?level=enhanced');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'level',
                'requirements' => [
                    '*' => [
                        'document_type',
                        'description',
                        'required',
                    ],
                ],
            ])
            ->assertJson([
                'level' => 'enhanced',
            ])
            ->assertJsonCount(2, 'requirements');
    }

    #[Test]
    public function test_requirements_validates_level_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/compliance/kyc/requirements?level=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function test_requirements_requires_level_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/compliance/kyc/requirements');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function test_submit_documents_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $this->kycService->shouldReceive('submitKyc')
            ->once()
            ->with(\Mockery::type(User::class), \Mockery::any());

        $file1 = UploadedFile::fake()->image('passport.jpg', 800, 600)->size(1000);
        $file2 = UploadedFile::fake()->create('utility_bill.pdf', 500);

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $file1,
                ],
                [
                    'type' => 'utility_bill',
                    'file' => $file2,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'KYC documents submitted successfully',
                'status'  => 'pending',
            ]);
    }

    #[Test]
    public function test_submit_prevents_resubmission_when_already_approved(): void
    {
        $approvedUser = User::factory()->create([
            'kyc_status' => 'approved',
        ]);

        Sanctum::actingAs($approvedUser);

        $file = UploadedFile::fake()->image('passport.jpg');

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $file,
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'KYC already approved',
            ]);
    }

    #[Test]
    public function test_submit_validates_document_type(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('document.jpg');

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'invalid_type',
                    'file' => $file,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['documents.0.type']);
    }

    #[Test]
    public function test_submit_validates_file_format(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('document.exe', 100);

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $file,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['documents.0.file']);
    }

    #[Test]
    public function test_submit_validates_file_size(): void
    {
        Sanctum::actingAs($this->user);

        // Create a file larger than 10MB
        $file = UploadedFile::fake()->image('large.jpg')->size(11000); // 11MB

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $file,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['documents.0.file']);
    }

    #[Test]
    public function test_submit_requires_authentication(): void
    {
        $response = $this->postJson('/api/compliance/kyc/submit', []);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_submit_handles_service_exception(): void
    {
        Sanctum::actingAs($this->user);

        $this->kycService->shouldReceive('submitKyc')
            ->once()
            ->andThrow(new \Exception('Service error'));

        $file = UploadedFile::fake()->image('passport.jpg');

        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $file,
                ],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to submit KYC documents',
            ]);
    }

    #[Test]
    public function test_upload_single_document_successfully(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('passport.jpg', 800, 600)->size(1000);

        $response = $this->postJson('/api/compliance/kyc/documents', [
            'document' => $file,
            'type'     => 'passport',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'document_id',
            ])
            ->assertJson([
                'message' => 'Document uploaded successfully',
            ]);

        // Verify file was stored
        Storage::disk('private')->assertExists('kyc/' . $this->user->uuid . '/' . $file->hashName());
    }

    #[Test]
    public function test_upload_validates_document_file(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/compliance/kyc/documents', [
            'type' => 'passport',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    #[Test]
    public function test_upload_validates_document_type(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('document.jpg');

        $response = $this->postJson('/api/compliance/kyc/documents', [
            'document' => $file,
            'type'     => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/compliance/kyc/documents', []);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_download_document_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/kyc/documents/123/download');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_download_document_returns_404_for_non_existent_document(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/compliance/kyc/documents/999/download');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_requirements_accepts_all_valid_levels(): void
    {
        Sanctum::actingAs($this->user);

        $levels = ['basic', 'enhanced', 'full'];

        foreach ($levels as $level) {
            $this->kycService->shouldReceive('getRequirements')
                ->with($level)
                ->once()
                ->andReturn([]);

            $response = $this->getJson("/api/compliance/kyc/requirements?level={$level}");

            $response->assertStatus(200)
                ->assertJson([
                    'level'        => $level,
                    'requirements' => [],
                ]);
        }
    }
}
