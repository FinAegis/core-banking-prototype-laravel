<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\KycDocument;
use App\Models\AuditLog;
use App\Domain\Compliance\Services\KycService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('private');
    
    $this->user = User::factory()->create([
        'kyc_status' => 'not_started',
        'kyc_level' => 'basic',
        'pep_status' => false,
        'data_retention_consent' => false,
    ]);
    
    Sanctum::actingAs($this->user);
});

describe('KYC status', function () {
    it('can get KYC status for authenticated user', function () {
        // Create some KYC documents
        $document = KycDocument::factory()->create([
            'user_uuid' => $this->user->uuid,
            'document_type' => 'passport',
            'status' => 'pending',
        ]);
        
        $response = $this->getJson('/api/compliance/kyc/status');
        
        $response->assertOk()
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
                'status' => 'not_started',
                'level' => 'basic',
                'needs_kyc' => true,
            ]);
        
        // Verify documents are included
        expect($response->json('documents'))->toHaveCount(1);
        expect($response->json('documents.0.type'))->toBe('passport');
    });
    
    it('shows needs_kyc as false when KYC is approved', function () {
        $this->user->update([
            'kyc_status' => 'approved',
            'kyc_level' => 'enhanced',
            'kyc_approved_at' => now(),
            'kyc_expires_at' => now()->addYears(2),
        ]);
        
        $response = $this->getJson('/api/compliance/kyc/status');
        
        $response->assertOk()
            ->assertJson([
                'status' => 'approved',
                'level' => 'enhanced',
                'needs_kyc' => false,
            ]);
    });
    
    it('shows needs_kyc as true when KYC is expired', function () {
        $this->user->update([
            'kyc_status' => 'approved',
            'kyc_expires_at' => now()->subDay(),
        ]);
        
        $response = $this->getJson('/api/compliance/kyc/status');
        
        $response->assertOk()
            ->assertJson([
                'needs_kyc' => true,
            ]);
    });
});

describe('KYC requirements', function () {
    it('can get requirements for basic level', function () {
        $response = $this->getJson('/api/compliance/kyc/requirements?level=basic');
        
        $response->assertOk()
            ->assertJson([
                'level' => 'basic',
                'requirements' => [
                    'documents' => ['national_id', 'selfie'],
                    'limits' => [
                        'daily_transaction' => 100000,
                        'monthly_transaction' => 500000,
                        'max_balance' => 1000000,
                    ],
                ],
            ]);
    });
    
    it('can get requirements for enhanced level', function () {
        $response = $this->getJson('/api/compliance/kyc/requirements?level=enhanced');
        
        $response->assertOk()
            ->assertJson([
                'level' => 'enhanced',
                'requirements' => [
                    'documents' => ['passport', 'utility_bill', 'selfie'],
                    'limits' => [
                        'daily_transaction' => 1000000,
                        'monthly_transaction' => 5000000,
                        'max_balance' => 10000000,
                    ],
                ],
            ]);
    });
    
    it('can get requirements for full level', function () {
        $response = $this->getJson('/api/compliance/kyc/requirements?level=full');
        
        $response->assertOk()
            ->assertJson([
                'level' => 'full',
                'requirements' => [
                    'documents' => ['passport', 'utility_bill', 'bank_statement', 'selfie', 'proof_of_income'],
                    'limits' => [
                        'daily_transaction' => null,
                        'monthly_transaction' => null,
                        'max_balance' => null,
                    ],
                ],
            ]);
    });
    
    it('validates level parameter', function () {
        $response = $this->getJson('/api/compliance/kyc/requirements?level=invalid');
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    });
    
    it('requires level parameter', function () {
        $response = $this->getJson('/api/compliance/kyc/requirements');
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    });
});

describe('KYC submission', function () {
    it('can submit KYC documents', function () {
        $passport = UploadedFile::fake()->image('passport.jpg', 100, 100);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100);
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $passport,
                ],
                [
                    'type' => 'selfie',
                    'file' => $selfie,
                ],
            ],
        ]);
        
        $response->assertOk()
            ->assertJson([
                'message' => 'KYC documents submitted successfully',
                'status' => 'pending',
            ]);
        
        // Verify user status updated
        $this->user->refresh();
        expect($this->user->kyc_status)->toBe('pending');
        expect($this->user->kyc_submitted_at)->not->toBeNull();
        
        // Verify documents created
        $documents = KycDocument::where('user_uuid', $this->user->uuid)->get();
        expect($documents)->toHaveCount(2);
        
        // Verify files stored
        Storage::disk('private')->assertExists("kyc/{$this->user->uuid}/" . $passport->hashName());
        Storage::disk('private')->assertExists("kyc/{$this->user->uuid}/" . $selfie->hashName());
        
        // Verify audit log
        $auditLog = AuditLog::where('action', 'kyc.submitted')
            ->where('user_uuid', $this->user->uuid)
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
        expect($auditLog->new_values['documents'])->toBe(2);
    });
    
    it('prevents submission when KYC is already approved', function () {
        $this->user->update(['kyc_status' => 'approved']);
        
        $document = UploadedFile::fake()->image('passport.jpg');
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $document,
                ],
            ],
        ]);
        
        $response->assertBadRequest()
            ->assertJson([
                'error' => 'KYC already approved',
            ]);
    });
    
    it('validates documents array', function () {
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['documents']);
    });
    
    it('validates document types', function () {
        $document = UploadedFile::fake()->image('document.jpg');
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'invalid_type',
                    'file' => $document,
                ],
            ],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.0.type']);
    });
    
    it('validates file format', function () {
        $document = UploadedFile::fake()->create('document.txt', 100);
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $document,
                ],
            ],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.0.file']);
    });
    
    it('validates file size', function () {
        $document = UploadedFile::fake()->image('large.jpg')->size(11000); // 11MB
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $document,
                ],
            ],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.0.file']);
    });
    
    it('handles submission errors gracefully', function () {
        // Mock the KYC service to throw an exception
        $kycService = Mockery::mock(KycService::class);
        $kycService->shouldReceive('submitKyc')
            ->andThrow(new \Exception('Submission failed'));
        
        $this->app->instance(KycService::class, $kycService);
        
        $document = UploadedFile::fake()->image('passport.jpg');
        
        $response = $this->postJson('/api/compliance/kyc/submit', [
            'documents' => [
                [
                    'type' => 'passport',
                    'file' => $document,
                ],
            ],
        ]);
        
        $response->assertServerError()
            ->assertJson([
                'error' => 'Failed to submit KYC documents',
            ]);
        
        // Verify error was logged
        $auditLog = AuditLog::where('action', 'kyc.submission_failed')
            ->where('user_uuid', $this->user->uuid)
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
    });
});

describe('document download', function () {
    beforeEach(function () {
        // Create a document with file
        Storage::disk('private')->put('kyc/test/document.pdf', 'test content');
        
        $this->document = KycDocument::factory()->create([
            'user_uuid' => $this->user->uuid,
            'file_path' => 'kyc/test/document.pdf',
            'metadata' => ['original_name' => 'passport.pdf'],
        ]);
    });
    
    it('can download own KYC document', function () {
        $response = $this->get("/api/compliance/kyc/documents/{$this->document->id}/download");
        
        $response->assertOk()
            ->assertDownload('passport.pdf');
        
        // Verify audit log
        $auditLog = AuditLog::where('action', 'kyc.document_downloaded')
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
    });
    
    it('cannot download other user\'s documents', function () {
        $otherUser = User::factory()->create();
        $otherDocument = KycDocument::factory()->create([
            'user_uuid' => $otherUser->uuid,
        ]);
        
        $response = $this->get("/api/compliance/kyc/documents/{$otherDocument->id}/download");
        
        $response->assertNotFound();
    });
    
    it('returns 404 when document file does not exist', function () {
        Storage::disk('private')->delete($this->document->file_path);
        
        $response = $this->get("/api/compliance/kyc/documents/{$this->document->id}/download");
        
        $response->assertNotFound();
    });
    
    it('returns 404 for non-existent document ID', function () {
        $response = $this->get('/api/compliance/kyc/documents/999/download');
        
        $response->assertNotFound();
    });
});

describe('authentication', function () {
    it('requires authentication for all KYC endpoints', function () {
        // Create a new test instance without authentication
        $this->refreshApplication();
        
        $endpoints = [
            ['GET', '/api/compliance/kyc/status'],
            ['GET', '/api/compliance/kyc/requirements?level=basic'],
            ['POST', '/api/compliance/kyc/submit'],
            ['GET', '/api/compliance/kyc/documents/1/download'],
        ];
        
        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertUnauthorized();
        }
    });
});