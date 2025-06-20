<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use App\Models\KycDocument;
use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->kycService = app(KycService::class);
    $this->user = User::factory()->create();
});

test('can submit kyc documents', function () {
    $documents = [
        [
            'type' => 'passport',
            'file' => UploadedFile::fake()->image('passport.jpg'),
        ],
        [
            'type' => 'utility_bill',
            'file' => UploadedFile::fake()->create('utility_bill.pdf', 1000),
        ],
    ];
    
    $this->kycService->submitKyc($this->user, $documents);
    
    // Check user status updated
    $this->user->refresh();
    expect($this->user->kyc_status)->toBe('pending');
    expect($this->user->kyc_submitted_at)->not->toBeNull();
    
    // Check documents created
    expect($this->user->kycDocuments)->toHaveCount(2);
    
    $passportDoc = $this->user->kycDocuments->where('document_type', 'passport')->first();
    expect($passportDoc)->not->toBeNull();
    expect($passportDoc->status)->toBe('pending');
    expect($passportDoc->file_hash)->not->toBeNull();
    
    // Check files stored
    Storage::disk('private')->assertExists($passportDoc->file_path);
    
    // Check audit log created
    $log = AuditLog::where('action', 'kyc.submitted')->first();
    expect($log)->not->toBeNull();
    expect($log->user_uuid)->toBe($this->user->uuid);
});

test('can verify kyc', function () {
    // Submit documents first
    $documents = [
        ['type' => 'passport', 'file' => UploadedFile::fake()->image('passport.jpg')],
    ];
    $this->kycService->submitKyc($this->user, $documents);
    
    // Verify KYC
    $this->kycService->verifyKyc($this->user, 'admin-123', [
        'level' => 'enhanced',
        'risk_rating' => 'low',
        'pep_status' => false,
    ]);
    
    // Check user status
    $this->user->refresh();
    expect($this->user->kyc_status)->toBe('approved');
    expect($this->user->kyc_approved_at)->not->toBeNull();
    expect($this->user->kyc_expires_at)->not->toBeNull();
    expect($this->user->kyc_level)->toBe('enhanced');
    expect($this->user->risk_rating)->toBe('low');
    expect($this->user->pep_status)->toBeFalse();
    
    // Check documents verified
    $document = $this->user->kycDocuments->first();
    expect($document->status)->toBe('verified');
    expect($document->verified_at)->not->toBeNull();
    expect($document->verified_by)->toBe('admin-123');
    
    // Check audit log
    $log = AuditLog::where('action', 'kyc.verified')->first();
    expect($log)->not->toBeNull();
});

test('can reject kyc', function () {
    // Submit documents first
    $documents = [
        ['type' => 'passport', 'file' => UploadedFile::fake()->image('passport.jpg')],
    ];
    $this->kycService->submitKyc($this->user, $documents);
    
    // Reject KYC
    $reason = 'Document is blurry and unreadable';
    $this->kycService->rejectKyc($this->user, $reason, 'admin-456');
    
    // Check user status
    $this->user->refresh();
    expect($this->user->kyc_status)->toBe('rejected');
    
    // Check documents rejected
    $document = $this->user->kycDocuments->first();
    expect($document->status)->toBe('rejected');
    expect($document->rejection_reason)->toBe($reason);
    expect($document->verified_by)->toBe('admin-456');
    
    // Check audit log
    $log = AuditLog::where('action', 'kyc.rejected')->first();
    expect($log)->not->toBeNull();
    expect($log->metadata['reason'])->toBe($reason);
});

test('checks for expired kyc', function () {
    // Set user as approved with expired date
    $this->user->update([
        'kyc_status' => 'approved',
        'kyc_expires_at' => now()->subDay(),
    ]);
    
    $expired = $this->kycService->checkExpiredKyc($this->user);
    
    expect($expired)->toBeTrue();
    
    $this->user->refresh();
    expect($this->user->kyc_status)->toBe('expired');
    
    // Check audit log
    $log = AuditLog::where('action', 'kyc.expired')->first();
    expect($log)->not->toBeNull();
});

test('provides correct requirements for kyc levels', function () {
    $basic = $this->kycService->getRequirements('basic');
    expect($basic['documents'])->toContain('national_id', 'selfie');
    expect($basic['limits']['daily_transaction'])->toBe(100000);
    
    $enhanced = $this->kycService->getRequirements('enhanced');
    expect($enhanced['documents'])->toContain('passport', 'utility_bill', 'selfie');
    expect($enhanced['limits']['daily_transaction'])->toBe(1000000);
    
    $full = $this->kycService->getRequirements('full');
    expect($full['documents'])->toContain('passport', 'utility_bill', 'bank_statement', 'selfie', 'proof_of_income');
    expect($full['limits']['daily_transaction'])->toBeNull();
});

test('user model has correct kyc methods', function () {
    // Test needs KYC
    expect($this->user->needsKyc())->toBeTrue();
    expect($this->user->hasCompletedKyc())->toBeFalse();
    
    // Approve KYC
    $this->user->update([
        'kyc_status' => 'approved',
        'kyc_expires_at' => now()->addYear(),
    ]);
    
    expect($this->user->needsKyc())->toBeFalse();
    expect($this->user->hasCompletedKyc())->toBeTrue();
    
    // Expired KYC
    $this->user->update(['kyc_expires_at' => now()->subDay()]);
    expect($this->user->needsKyc())->toBeTrue();
    expect($this->user->hasCompletedKyc())->toBeFalse();
});