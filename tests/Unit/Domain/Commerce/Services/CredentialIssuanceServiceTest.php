<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\CredentialType;
use App\Domain\Commerce\Events\CredentialIssued;
use App\Domain\Commerce\Services\CredentialIssuanceService;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Event::fake();
    $this->service = new CredentialIssuanceService('did:test:issuer');
});

describe('CredentialIssuanceService', function (): void {
    describe('issueCredential', function (): void {
        it('issues a verifiable credential', function (): void {
            $credential = $this->service->issueCredential(
                type: CredentialType::KYC_VERIFICATION,
                subjectId: 'user-123',
                credentialSubject: ['verificationLevel' => 3],
            );

            expect($credential->credentialId)->toStartWith('urn:uuid:');
            expect($credential->type)->toBe(CredentialType::KYC_VERIFICATION);
            expect($credential->issuerId)->toBe('did:test:issuer');
            expect($credential->subjectId)->toBe('did:user:user-123');
            expect($credential->proof)->not->toBeEmpty();

            Event::assertDispatched(CredentialIssued::class, function ($event): bool {
                return $event->credentialType === CredentialType::KYC_VERIFICATION
                    && $event->subjectId === 'user-123';
            });
        });

        it('sets expiry based on credential type', function (): void {
            $credential = $this->service->issueCredential(
                type: CredentialType::KYC_VERIFICATION, // Default 365 days
                subjectId: 'user-123',
                credentialSubject: [],
            );

            expect($credential->expiresAt)->not->toBeNull();
            expect($credential->isValid())->toBeTrue();
        });

        it('respects custom validity days', function (): void {
            $credential = $this->service->issueCredential(
                type: CredentialType::KYC_VERIFICATION,
                subjectId: 'user-123',
                credentialSubject: [],
                validityDays: 30,
            );

            expect($credential->expiresAt)->not->toBeNull();
            // Verify expiry is approximately 30 days away
            $diff = $credential->expiresAt->getTimestamp() - time();
            expect($diff)->toBeLessThanOrEqual(30 * 24 * 60 * 60);
        });

        it('creates non-expiring credentials when validity is 0', function (): void {
            $credential = $this->service->issueCredential(
                type: CredentialType::KYC_VERIFICATION,
                subjectId: 'user-123',
                credentialSubject: [],
                validityDays: 0,
            );

            expect($credential->expiresAt)->toBeNull();
        });
    });

    describe('issueKycCredential', function (): void {
        it('issues KYC verification credential', function (): void {
            $credential = $this->service->issueKycCredential(
                userId: 'user-123',
                verificationLevel: 3,
                verificationMethod: 'document_verification',
            );

            expect($credential->type)->toBe(CredentialType::KYC_VERIFICATION);
            expect($credential->credentialSubject['verificationLevel'])->toBe(3);
            expect($credential->credentialSubject['verificationMethod'])->toBe('document_verification');
        });
    });

    describe('issueAccreditationCredential', function (): void {
        it('issues accreditation credential', function (): void {
            $credential = $this->service->issueAccreditationCredential(
                userId: 'user-123',
                accreditationType: 'qualified_investor',
                jurisdiction: 'US',
            );

            expect($credential->type)->toBe(CredentialType::ACCREDITATION);
            expect($credential->credentialSubject['accreditationType'])->toBe('qualified_investor');
            expect($credential->credentialSubject['jurisdiction'])->toBe('US');
        });
    });

    describe('issueProfessionalCredential', function (): void {
        it('issues professional credential', function (): void {
            $credential = $this->service->issueProfessionalCredential(
                userId: 'user-123',
                profession: 'Financial Advisor',
                licenseNumber: 'FA-12345',
                issuingAuthority: 'SEC',
            );

            expect($credential->type)->toBe(CredentialType::PROFESSIONAL);
            expect($credential->credentialSubject['profession'])->toBe('Financial Advisor');
            expect($credential->credentialSubject['licenseNumber'])->toBe('FA-12345');
        });
    });

    describe('issuePaymentHistoryCredential', function (): void {
        it('issues payment history credential', function (): void {
            $credential = $this->service->issuePaymentHistoryCredential(
                userId: 'user-123',
                ratingCategory: 'excellent',
                score: 850,
                transactionCount: 1000,
            );

            expect($credential->type)->toBe(CredentialType::PAYMENT_HISTORY);
            expect($credential->credentialSubject['ratingCategory'])->toBe('excellent');
            expect($credential->credentialSubject['score'])->toBe(850);
            expect($credential->credentialSubject['transactionCount'])->toBe(1000);
        });
    });

    describe('verifyCredential', function (): void {
        it('verifies valid credentials', function (): void {
            $credential = $this->service->issueKycCredential(
                userId: 'user-123',
                verificationLevel: 3,
                verificationMethod: 'document',
            );

            expect($this->service->verifyCredential($credential))->toBeTrue();
        });

        it('rejects credentials from different issuer', function (): void {
            $otherService = new CredentialIssuanceService('did:other:issuer');
            $credential = $otherService->issueKycCredential(
                userId: 'user-123',
                verificationLevel: 3,
                verificationMethod: 'document',
            );

            expect($this->service->verifyCredential($credential))->toBeFalse();
        });
    });

    describe('toW3CFormat', function (): void {
        it('converts credential to W3C format', function (): void {
            $credential = $this->service->issueKycCredential(
                userId: 'user-123',
                verificationLevel: 3,
                verificationMethod: 'document',
            );

            $w3c = $credential->toW3CFormat();

            expect($w3c['@context'])->toContain('https://www.w3.org/2018/credentials/v1');
            expect($w3c['type'])->toContain('VerifiableCredential');
            expect($w3c['type'])->toContain('KYCVerificationCredential');
            expect($w3c['issuer'])->toBe('did:test:issuer');
            expect($w3c['credentialSubject']['id'])->toBe('did:user:user-123');
            expect($w3c['proof'])->toHaveKey('proofValue');
        });
    });

    describe('createPresentation', function (): void {
        it('creates a verifiable presentation', function (): void {
            $credentials = [
                $this->service->issueKycCredential('user-123', 3, 'document'),
                $this->service->issueAccreditationCredential('user-123', 'investor', 'US'),
            ];

            $presentation = $this->service->createPresentation(
                credentials: $credentials,
                holderId: 'user-123',
            );

            expect($presentation['@context'])->toContain('https://www.w3.org/2018/credentials/v1');
            expect($presentation['type'])->toContain('VerifiablePresentation');
            expect($presentation['holder'])->toBe('did:user:user-123');
            expect($presentation['verifiableCredential'])->toHaveCount(2);
            expect($presentation['proof'])->toHaveKey('proofValue');
        });

        it('includes challenge when provided', function (): void {
            $credentials = [
                $this->service->issueKycCredential('user-123', 3, 'document'),
            ];

            $presentation = $this->service->createPresentation(
                credentials: $credentials,
                holderId: 'user-123',
                challenge: 'test-challenge-123',
            );

            expect($presentation['proof']['challenge'])->toBe('test-challenge-123');
        });
    });
});
