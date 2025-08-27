<?php

namespace Tests\Unit\Domain\Compliance\Services;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\ServiceTestCase;

class KycServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    private KycService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KycService();
        Storage::fake('private');
    }

    #[Test]
    public function test_submit_kyc_updates_user_status(): void
    {
        $user = User::factory()->create([
            'kyc_status'       => 'not_started',
            'kyc_submitted_at' => null,
        ]);

        $documents = [
            [
                'type' => 'passport',
                'file' => UploadedFile::fake()->image('passport.jpg', 1000, 1000),
            ],
            [
                'type' => 'utility_bill',
                'file' => UploadedFile::fake()->create('utility_bill.pdf', 500),
            ],
        ];

        $this->service->submitKyc($user, $documents);

        $user->refresh();
        $this->assertEquals('pending', $user->kyc_status);
        $this->assertNotNull($user->kyc_submitted_at);
    }

    #[Test]
    public function test_submit_kyc_stores_documents(): void
    {
        $user = User::factory()->create();

        $documents = [
            [
                'type' => 'passport',
                'file' => UploadedFile::fake()->image('passport.jpg'),
            ],
            [
                'type' => 'drivers_license',
                'file' => UploadedFile::fake()->image('license.jpg'),
            ],
        ];

        $this->service->submitKyc($user, $documents);

        // Check documents were created
        $storedDocs = KycDocument::where('user_uuid', $user->uuid)->get();
        $this->assertCount(2, $storedDocs);

        // Check document types
        $this->assertTrue($storedDocs->contains('document_type', 'passport'));
        $this->assertTrue($storedDocs->contains('document_type', 'drivers_license'));

        // Check files were stored
        foreach ($storedDocs as $doc) {
            Storage::disk('private')->assertExists($doc->file_path);
        }
    }

    #[Test]
    public function test_submit_kyc_creates_audit_log(): void
    {
        $user = User::factory()->create();

        $documents = [
            [
                'type' => 'passport',
                'file' => UploadedFile::fake()->image('passport.jpg'),
            ],
        ];

        $this->service->submitKyc($user, $documents);

        // Check if audit log was created (user_uuid will be null in tests)
        $auditLog = AuditLog::where('action', 'kyc.submitted')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals(1, $auditLog->new_values['documents']);
        $this->assertContains('passport', $auditLog->metadata['document_types']);
        $this->assertEquals('kyc,compliance', $auditLog->tags);
    }

    #[Test]
    public function test_verify_kyc_approves_user(): void
    {
        $user = User::factory()->create([
            'kyc_status'      => 'pending',
            'kyc_approved_at' => null,
        ]);

        $this->service->verifyKyc($user, 'admin-123', ['notes' => 'All documents verified']);

        $user->refresh();
        $this->assertEquals('approved', $user->kyc_status);
        $this->assertNotNull($user->kyc_approved_at);
    }

    #[Test]
    public function test_verify_kyc_creates_audit_log(): void
    {
        $user = User::factory()->create(['kyc_status' => 'pending']);

        $this->service->verifyKyc($user, 'admin-456', ['notes' => 'Verified']);

        // Check if audit log was created (user_uuid will be null in tests)
        $auditLog = AuditLog::where('action', 'kyc.approved')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('pending', $auditLog->old_values['kyc_status']);
        $this->assertEquals('approved', $auditLog->new_values['kyc_status']);
        $this->assertEquals('admin-456', $auditLog->metadata['verified_by']);
    }

    #[Test]
    public function test_reject_kyc_updates_status(): void
    {
        $user = User::factory()->create(['kyc_status' => 'pending']);

        $this->service->rejectKyc($user, 'admin-789', 'Invalid documents');

        $user->refresh();
        $this->assertEquals('rejected', $user->kyc_status);
        $this->assertNotNull($user->kyc_rejected_at);
    }

    #[Test]
    public function test_get_kyc_status_returns_user_status(): void
    {
        $user = User::factory()->create(['kyc_status' => 'approved']);

        $status = $this->service->getKycStatus($user);

        $this->assertEquals('approved', $status);
    }

    #[Test]
    public function test_is_kyc_approved_returns_correct_boolean(): void
    {
        $approvedUser = User::factory()->create(['kyc_status' => 'approved']);
        $pendingUser = User::factory()->create(['kyc_status' => 'pending']);
        $rejectedUser = User::factory()->create(['kyc_status' => 'rejected']);

        $this->assertTrue($this->service->isKycApproved($approvedUser));
        $this->assertFalse($this->service->isKycApproved($pendingUser));
        $this->assertFalse($this->service->isKycApproved($rejectedUser));
    }

    #[Test]
    public function test_store_document_creates_hash(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('test.jpg');
        $document = [
            'type' => 'passport',
            'file' => $file,
        ];

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('storeDocument');
        $method->setAccessible(true);

        $kycDocument = $method->invoke($this->service, $user, $document);

        $this->assertNotNull($kycDocument->file_hash);
        $this->assertEquals(64, strlen($kycDocument->file_hash)); // SHA-256 hash length
        $this->assertEquals('passport', $kycDocument->document_type);
        $this->assertEquals('test.jpg', $kycDocument->metadata['original_name']);
    }

    #[Test]
    public function test_handle_document_with_different_types(): void
    {
        $user = User::factory()->create();

        $documentTypes = [
            'passport',
            'drivers_license',
            'national_id',
            'utility_bill',
            'selfie',
            'residence_permit',
        ];

        foreach ($documentTypes as $type) {
            $documents = [[
                'type' => $type,
                'file' => UploadedFile::fake()->create("{$type}.pdf", 100),
            ]];

            $this->service->submitKyc($user, $documents);
        }

        $storedDocs = KycDocument::where('user_uuid', $user->uuid)->pluck('document_type');

        foreach ($documentTypes as $type) {
            $this->assertContains($type, $storedDocs);
        }
    }
}
