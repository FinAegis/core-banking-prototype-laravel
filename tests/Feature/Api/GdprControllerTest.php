<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Domain\Compliance\Services\GdprService;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create([
        'privacy_policy_accepted_at' => now()->subDays(10),
        'terms_accepted_at' => now()->subDays(10),
        'marketing_consent_at' => now()->subDays(5),
        'data_retention_consent' => true,
    ]);
    
    Sanctum::actingAs($this->user);
});

describe('consent management', function () {
    it('can get user consent status', function () {
        $response = $this->getJson('/api/compliance/gdpr/consent');
        
        $response->assertOk()
            ->assertJsonStructure([
                'consents' => [
                    'privacy_policy',
                    'terms',
                    'marketing',
                    'data_retention',
                ],
                'dates' => [
                    'privacy_policy_accepted_at',
                    'terms_accepted_at',
                    'marketing_consent_at',
                ],
            ])
            ->assertJson([
                'consents' => [
                    'privacy_policy' => true,
                    'terms' => true,
                    'marketing' => true,
                    'data_retention' => true,
                ],
            ]);
    });
    
    it('can update consent preferences', function () {
        $response = $this->postJson('/api/compliance/gdpr/consent', [
            'marketing' => false,
            'data_retention' => false,
        ]);
        
        $response->assertOk()
            ->assertJson([
                'message' => 'Consent preferences updated successfully',
            ]);
        
        $this->user->refresh();
        expect($this->user->marketing_consent_at)->toBeNull();
        expect($this->user->data_retention_consent)->toBeFalse();
        
        // Verify audit log
        $auditLog = AuditLog::where('action', 'gdpr.consent_updated')
            ->where('user_uuid', $this->user->uuid)
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
    });
    
    it('validates consent update request', function () {
        $response = $this->postJson('/api/compliance/gdpr/consent', [
            'marketing' => 'invalid',
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['marketing']);
    });
    
    it('can revoke all consents', function () {
        $response = $this->postJson('/api/compliance/gdpr/consent', [
            'privacy_policy' => false,
            'terms' => false,
            'marketing' => false,
            'data_retention' => false,
        ]);
        
        $response->assertOk();
        
        $this->user->refresh();
        expect($this->user->privacy_policy_accepted_at)->toBeNull();
        expect($this->user->terms_accepted_at)->toBeNull();
        expect($this->user->marketing_consent_at)->toBeNull();
        expect($this->user->data_retention_consent)->toBeFalse();
    });
});

describe('data export', function () {
    beforeEach(function () {
        // Create some user data
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);
        
        $this->kycDocument = KycDocument::factory()->create([
            'user_uuid' => $this->user->uuid,
            'document_type' => 'passport',
            'status' => 'verified',
        ]);
        
        // Create some audit logs
        AuditLog::create([
            'user_uuid' => $this->user->uuid,
            'action' => 'auth.login',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->uuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
        ]);
    });
    
    it('can request data export', function () {
        $response = $this->postJson('/api/compliance/gdpr/export');
        
        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'preview' => [
                    'sections',
                    'generated_at',
                ],
            ])
            ->assertJson([
                'message' => 'Data export requested. You will receive an email with your data shortly.',
            ]);
        
        // Verify the preview contains expected sections
        expect($response->json('preview.sections'))->toContain(
            'user',
            'accounts',
            'transactions',
            'kyc_documents',
            'audit_logs',
            'consents'
        );
        
        // Verify audit log
        $auditLog = AuditLog::where('action', 'gdpr.data_exported')
            ->where('user_uuid', $this->user->uuid)
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
    });
    
    it('handles data export errors gracefully', function () {
        // Mock the GDPR service to throw an exception
        $gdprService = Mockery::mock(GdprService::class);
        $gdprService->shouldReceive('exportUserData')
            ->andThrow(new \Exception('Export failed'));
        
        $this->app->instance(GdprService::class, $gdprService);
        
        $response = $this->postJson('/api/compliance/gdpr/export');
        
        $response->assertServerError()
            ->assertJson([
                'error' => 'Failed to process data export request',
            ]);
    });
});

describe('account deletion', function () {
    it('can request account deletion with valid confirmation', function () {
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => true,
            'reason' => 'No longer using the service',
        ]);
        
        $response->assertOk()
            ->assertJson([
                'message' => 'Account deletion request processed. Your account will be deleted within 30 days.',
            ]);
        
        // Verify audit log
        $auditLog = AuditLog::where('action', 'gdpr.deletion_requested')
            ->where('user_uuid', $this->user->uuid)
            ->latest()
            ->first();
            
        expect($auditLog)->not->toBeNull();
        expect($auditLog->metadata['options']['reason'])->toBe('No longer using the service');
    });
    
    it('requires confirmation to delete account', function () {
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => false,
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['confirm']);
    });
    
    it('prevents deletion when user has active accounts with balance', function () {
        // Create account with balance
        Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance' => 10000, // $100
        ]);
        
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => true,
        ]);
        
        $response->assertBadRequest()
            ->assertJson([
                'error' => 'Account cannot be deleted at this time',
                'reasons' => ['User has active accounts with positive balance'],
            ]);
    });
    
    it('prevents deletion when KYC is in review', function () {
        $this->user->update(['kyc_status' => 'in_review']);
        
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => true,
        ]);
        
        $response->assertBadRequest()
            ->assertJson([
                'error' => 'Account cannot be deleted at this time',
                'reasons' => ['KYC verification is in progress'],
            ]);
    });
    
    it('handles deletion errors gracefully', function () {
        // Mock the GDPR service to throw an exception
        $gdprService = Mockery::mock(GdprService::class);
        $gdprService->shouldReceive('canDeleteUserData')
            ->andReturn(['can_delete' => true, 'reasons' => []]);
        $gdprService->shouldReceive('deleteUserData')
            ->andThrow(new \Exception('Deletion failed'));
        
        $this->app->instance(GdprService::class, $gdprService);
        
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => true,
        ]);
        
        $response->assertServerError()
            ->assertJson([
                'error' => 'Failed to process deletion request',
            ]);
    });
    
    it('validates reason field length', function () {
        $response = $this->postJson('/api/compliance/gdpr/delete', [
            'confirm' => true,
            'reason' => str_repeat('a', 501), // Exceeds 500 character limit
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});

describe('data retention policy', function () {
    it('can get data retention policy', function () {
        $response = $this->getJson('/api/compliance/gdpr/retention-policy');
        
        $response->assertOk()
            ->assertJsonStructure([
                'policy' => [
                    'transaction_data',
                    'kyc_documents',
                    'audit_logs',
                    'marketing_data',
                    'inactive_accounts',
                ],
                'user_rights' => [
                    'access',
                    'rectification',
                    'erasure',
                    'portability',
                    'object',
                ],
            ]);
        
        // Verify specific retention periods
        expect($response->json('policy.transaction_data'))->toBe('7 years (regulatory requirement)');
        expect($response->json('policy.kyc_documents'))->toBe('5 years after account closure');
    });
});

describe('authentication', function () {
    it('requires authentication for all GDPR endpoints', function () {
        // Create a new test instance without authentication
        $this->refreshApplication();
        
        $endpoints = [
            ['GET', '/api/compliance/gdpr/consent'],
            ['POST', '/api/compliance/gdpr/consent'],
            ['POST', '/api/compliance/gdpr/export'],
            ['POST', '/api/compliance/gdpr/delete'],
            ['GET', '/api/compliance/gdpr/retention-policy'],
        ];
        
        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertUnauthorized();
        }
    });
});