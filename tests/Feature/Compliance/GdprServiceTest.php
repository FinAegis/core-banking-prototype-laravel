<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\GdprService;
use App\Models\User;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->gdprService = app(GdprService::class);
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'privacy_policy_accepted_at' => now()->subMonth(),
        'terms_accepted_at' => now()->subMonth(),
        'marketing_consent_at' => now()->subMonth(),
        'data_retention_consent' => true,
    ]);
});

test('can export user data', function () {
    // Create some data for the user
    $account = Account::factory()->forUser($this->user)->create();
    $kycDoc = KycDocument::factory()->create(['user_uuid' => $this->user->uuid]);
    
    $data = $this->gdprService->exportUserData($this->user);
    
    expect($data)->toHaveKeys(['user', 'accounts', 'transactions', 'kyc_documents', 'audit_logs', 'consents']);
    
    // Check user data
    expect($data['user']['uuid'])->toBe($this->user->uuid);
    expect($data['user']['name'])->toBe('Test User');
    expect($data['user']['email'])->toBe('test@example.com');
    
    // Check accounts data
    expect($data['accounts'])->toHaveCount(1);
    expect($data['accounts'][0]['uuid'])->toBe((string) $account->uuid);
    
    // Check KYC data
    expect($data['kyc_documents'])->toHaveCount(1);
    expect($data['kyc_documents'][0]['id'])->toBe((string) $kycDoc->id);
    
    // Check consents
    expect($data['consents']['privacy_policy_accepted_at'])->not->toBeNull();
    expect($data['consents']['data_retention_consent'])->toBeTrue();
    
    // Check audit log created
    $log = AuditLog::where('action', 'gdpr.data_exported')->first();
    expect($log)->not->toBeNull();
    expect($log->user_uuid)->toBe($this->user->uuid);
});

test('can update consent preferences', function () {
    $this->gdprService->updateConsent($this->user, [
        'marketing' => false,
        'data_retention' => false,
        'privacy_policy' => true,
        'terms' => true,
    ]);
    
    $this->user->refresh();
    expect($this->user->marketing_consent_at)->toBeNull();
    expect($this->user->data_retention_consent)->toBeFalse();
    expect($this->user->privacy_policy_accepted_at)->not->toBeNull();
    expect($this->user->terms_accepted_at)->not->toBeNull();
    
    // Check audit log
    $log = AuditLog::where('action', 'gdpr.consent_updated')->first();
    expect($log)->not->toBeNull();
    expect($log->old_values)->toHaveKey('marketing_consent');
    expect($log->new_values)->toHaveKey('marketing');
});

test('can check if user data can be deleted', function () {
    // User with no balance - can delete
    $account = Account::factory()->forUser($this->user)->create(['balance' => 0]);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeTrue();
    expect($check['reasons'])->toBeEmpty();
    
    // User with positive balance - cannot delete
    $account->update(['balance' => 10000]);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeFalse();
    expect($check['reasons'])->toContain('User has active accounts with positive balance');
    
    // User with KYC in review - cannot delete
    $account->update(['balance' => 0]);
    $this->user->update(['kyc_status' => 'in_review']);
    $check = $this->gdprService->canDeleteUserData($this->user);
    expect($check['can_delete'])->toBeFalse();
    expect($check['reasons'])->toContain('KYC verification is in progress');
});

test('can anonymize user data', function () {
    $originalName = $this->user->name;
    $originalEmail = $this->user->email;
    $originalUuid = $this->user->uuid;
    
    // Create KYC document
    $kycDoc = KycDocument::factory()->create([
        'user_uuid' => $this->user->uuid,
        'file_path' => 'kyc/' . $this->user->uuid . '/document.pdf',
    ]);
    Storage::disk('private')->put($kycDoc->file_path, 'fake content');
    
    $this->gdprService->deleteUserData($this->user, [
        'delete_documents' => true,
        'anonymize_transactions' => true,
    ]);
    
    $this->user->refresh();
    
    // Check user is anonymized
    expect($this->user->name)->toStartWith('Deleted User');
    expect($this->user->email)->toBe('deleted-' . $originalUuid . '@anonymous.local');
    expect($this->user->kyc_data)->toBeNull();
    
    // Check KYC documents deleted
    expect(KycDocument::where('user_uuid', $this->user->uuid)->count())->toBe(0);
    Storage::disk('private')->assertMissing($kycDoc->file_path);
    
    // Check audit logs
    $deletionLog = AuditLog::where('action', 'gdpr.deletion_requested')->first();
    expect($deletionLog)->not->toBeNull();
    
    $anonymizationLog = AuditLog::where('action', 'gdpr.transactions_anonymized')->first();
    expect($anonymizationLog)->not->toBeNull();
});

test('consent tracking works correctly', function () {
    $user = User::factory()->create([
        'privacy_policy_accepted_at' => null,
        'terms_accepted_at' => null,
        'marketing_consent_at' => null,
        'data_retention_consent' => false,
    ]);
    
    // Initially no consents
    expect($user->privacy_policy_accepted_at)->toBeNull();
    expect($user->terms_accepted_at)->toBeNull();
    expect($user->marketing_consent_at)->toBeNull();
    expect($user->data_retention_consent)->toBeFalse();
    
    // Update consents
    $this->gdprService->updateConsent($user, [
        'privacy_policy' => true,
        'terms' => true,
        'marketing' => true,
        'data_retention' => true,
    ]);
    
    $user->refresh();
    expect($user->privacy_policy_accepted_at)->not->toBeNull();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->marketing_consent_at)->not->toBeNull();
    expect($user->data_retention_consent)->toBeTrue();
    
    // Revoke marketing consent
    $this->gdprService->updateConsent($user, ['marketing' => false]);
    
    $user->refresh();
    expect($user->marketing_consent_at)->toBeNull();
    expect($user->privacy_policy_accepted_at)->not->toBeNull(); // Others unchanged
});