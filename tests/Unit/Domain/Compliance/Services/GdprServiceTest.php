<?php

namespace Tests\Unit\Domain\Compliance\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\ServiceTestCase;

class GdprServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    private GdprService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GdprService();
        Storage::fake('private');
    }

    #[Test]
    public function test_export_user_data_returns_complete_data_structure(): void
    {
        $user = User::factory()->create([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Create related data
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        TransactionProjection::factory()->count(3)->create([
            'account_uuid' => $account->uuid,
            'type'         => 'deposit',
            'amount'       => 10000,
            'asset_code'   => 'USD',
            'metadata'     => [],
        ]);
        KycDocument::factory()->count(2)->create(['user_uuid' => $user->uuid]);

        $exportedData = $this->service->exportUserData($user);

        $this->assertArrayHasKey('user', $exportedData);
        $this->assertArrayHasKey('accounts', $exportedData);
        $this->assertArrayHasKey('transactions', $exportedData);
        $this->assertArrayHasKey('kyc_documents', $exportedData);
        $this->assertArrayHasKey('audit_logs', $exportedData);
        $this->assertArrayHasKey('consents', $exportedData);
    }

    #[Test]
    public function test_export_user_data_creates_audit_log(): void
    {
        $user = User::factory()->create();

        $this->service->exportUserData($user);

        // Check if audit log was created (user_uuid will be null in tests)
        $auditLog = AuditLog::where('action', 'gdpr.data_exported')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($user->uuid, $auditLog->metadata['requested_by']);
        $this->assertStringContainsString('gdpr', $auditLog->tags);
        $this->assertStringContainsString('data-export', $auditLog->tags);
    }

    #[Test]
    public function test_delete_user_data_anonymizes_user(): void
    {
        $user = User::factory()->create([
            'name'  => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $originalUuid = $user->uuid;

        $this->service->deleteUserData($user);

        $user->refresh();

        // Check user is anonymized
        $this->assertStringStartsWith('ANONYMIZED_', $user->name);
        $this->assertStringContainsString('@anonymized.local', $user->email);
        $this->assertEquals($originalUuid, $user->uuid); // UUID should remain for data integrity
    }

    #[Test]
    public function test_delete_user_data_creates_deletion_audit_log(): void
    {
        $user = User::factory()->create();

        $this->service->deleteUserData($user);

        // Check if audit log was created (user_uuid will be null in tests)
        $auditLog = AuditLog::where('action', 'gdpr.deletion_requested')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('gdpr', $auditLog->tags);
        $this->assertStringContainsString('deletion', $auditLog->tags);
    }

    #[Test]
    public function test_delete_user_data_with_document_deletion_option(): void
    {
        $user = User::factory()->create();

        // Create KYC documents
        $documents = KycDocument::factory()->count(3)->create([
            'user_uuid' => $user->uuid,
            'file_path' => 'kyc/test-document.pdf',
        ]);

        // Store fake files
        foreach ($documents as $doc) {
            Storage::disk('private')->put($doc->file_path, 'fake content');
        }

        $this->service->deleteUserData($user, ['delete_documents' => true]);

        // Check documents are deleted
        foreach ($documents as $doc) {
            $this->assertDatabaseMissing('kyc_documents', ['id' => $doc->id]);
            Storage::disk('private')->assertMissing($doc->file_path);
        }
    }

    #[Test]
    public function test_delete_user_data_without_document_deletion(): void
    {
        $user = User::factory()->create();
        $document = KycDocument::factory()->create(['user_uuid' => $user->uuid]);

        $this->service->deleteUserData($user, ['delete_documents' => false]);

        // Document should still exist
        $this->assertDatabaseHas('kyc_documents', ['id' => $document->id]);
    }

    #[Test]
    public function test_get_user_data_returns_sanitized_data(): void
    {
        $user = User::factory()->create([
            'password'       => bcrypt('secret'),
            'remember_token' => 'token123',
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getUserData');
        $method->setAccessible(true);

        $userData = $method->invoke($this->service, $user);

        // Sensitive fields should not be included
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);

        // Regular fields should be included
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
    }

    #[Test]
    public function test_export_includes_transaction_history(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        // Create various transaction types
        TransactionProjection::factory()->create([
            'account_uuid' => $account->uuid,
            'type'         => 'deposit',
            'amount'       => 10000,
            'asset_code'   => 'USD',
            'metadata'     => [],
        ]);

        TransactionProjection::factory()->create([
            'account_uuid' => $account->uuid,
            'type'         => 'withdrawal',
            'amount'       => 5000,
            'asset_code'   => 'USD',
            'metadata'     => [],
        ]);

        $exportedData = $this->service->exportUserData($user);

        $this->assertNotEmpty($exportedData['transactions']);
        $this->assertCount(2, $exportedData['transactions']);
    }

    #[Test]
    public function test_anonymize_user_preserves_data_relationships(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('anonymizeUser');
        $method->setAccessible(true);

        $method->invoke($this->service, $user);

        // Account should still be linked to user
        $this->assertEquals($user->uuid, $account->fresh()->user_uuid);
    }

    #[Test]
    public function test_export_handles_user_with_no_data(): void
    {
        $user = User::factory()->create();

        $exportedData = $this->service->exportUserData($user);

        $this->assertNotEmpty($exportedData['user']);
        $this->assertEmpty($exportedData['accounts']);
        $this->assertEmpty($exportedData['transactions']);
        $this->assertEmpty($exportedData['kyc_documents']);
    }

    #[Test]
    public function test_delete_creates_final_audit_log_after_deletion(): void
    {
        $user = User::factory()->create();

        $this->service->deleteUserData($user);

        // Check if audit log was created (user_uuid will be null in tests)
        $deletionCompleteLog = AuditLog::where('action', 'gdpr.deletion_completed')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($deletionCompleteLog);
    }
}
