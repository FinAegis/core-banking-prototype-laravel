<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\TokenType;
use App\Domain\Commerce\Events\SoulboundTokenIssued;
use App\Domain\Commerce\Events\SoulboundTokenRevoked;
use App\Domain\Commerce\Services\SoulboundTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Event::fake();
    Cache::flush();
    $this->service = new SoulboundTokenService('test-issuer');
});

describe('SoulboundTokenService', function (): void {
    describe('issueToken', function (): void {
        it('issues a soulbound token', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: ['badge_type' => 'kyc', 'level' => 3],
            );

            expect($token->tokenId)->not->toBeEmpty();
            expect($token->type)->toBe(TokenType::SOULBOUND);
            expect($token->recipientId)->toBe('user-123');
            expect($token->issuerId)->toBe('test-issuer');
            expect($token->getMetadata('badge_type'))->toBe('kyc');
            expect($token->isValid())->toBeTrue();

            Event::assertDispatched(SoulboundTokenIssued::class, function ($event) use ($token): bool {
                return $event->tokenId === $token->tokenId
                    && $event->recipientId === 'user-123';
            });
        });

        it('respects custom validity days', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: ['validity_days' => 30],
            );

            $remainingSeconds = $token->getRemainingValiditySeconds();
            // Should be approximately 30 days (with some tolerance)
            expect($remainingSeconds)->toBeLessThanOrEqual(30 * 24 * 60 * 60);
            expect($remainingSeconds)->toBeGreaterThan(29 * 24 * 60 * 60);
        });

        it('creates non-expiring tokens when validity is 0', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: ['validity_days' => 0],
            );

            expect($token->expiresAt)->toBeNull();
            expect($token->getRemainingValiditySeconds())->toBe(PHP_INT_MAX);
        });
    });

    describe('issueKycBadge', function (): void {
        it('issues a KYC verification badge', function (): void {
            $token = $this->service->issueKycBadge(
                userId: 'user-123',
                verificationLevel: 3,
                verificationDetails: ['method' => 'document'],
            );

            expect($token->type)->toBe(TokenType::SOULBOUND);
            expect($token->getMetadata('badge_type'))->toBe('kyc_verification');
            expect($token->getMetadata('verification_level'))->toBe(3);
            expect($token->getMetadata('details'))->toBe(['method' => 'document']);
        });
    });

    describe('issueMembershipToken', function (): void {
        it('issues a membership token', function (): void {
            $token = $this->service->issueMembershipToken(
                userId: 'user-123',
                tier: 'gold',
            );

            expect($token->getMetadata('badge_type'))->toBe('membership');
            expect($token->getMetadata('tier'))->toBe('gold');
        });
    });

    describe('issueReputationToken', function (): void {
        it('issues a reputation token', function (): void {
            $token = $this->service->issueReputationToken(
                userId: 'user-123',
                score: 850,
                category: 'payment_reliability',
            );

            expect($token->getMetadata('badge_type'))->toBe('reputation');
            expect($token->getMetadata('score'))->toBe(850);
            expect($token->getMetadata('category'))->toBe('payment_reliability');
        });
    });

    describe('verifyToken', function (): void {
        it('verifies valid tokens', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            expect($this->service->verifyToken($token))->toBeTrue();
        });

        it('rejects tokens from different issuer', function (): void {
            $otherService = new SoulboundTokenService('other-issuer');
            $token = $otherService->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            expect($this->service->verifyToken($token))->toBeFalse();
        });

        it('rejects revoked tokens', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            $this->service->revokeToken($token->tokenId, 'Test revocation');

            expect($this->service->verifyToken($token))->toBeFalse();
        });
    });

    describe('revokeToken', function (): void {
        it('revokes a token', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            $result = $this->service->revokeToken($token->tokenId, 'Fraud detected');

            expect($result)->toBeTrue();
            expect($this->service->isRevoked($token->tokenId))->toBeTrue();

            Event::assertDispatched(SoulboundTokenRevoked::class, function ($event) use ($token): bool {
                return $event->tokenId === $token->tokenId
                    && $event->reason === 'Fraud detected';
            });
        });

        it('returns false for already revoked token', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            $this->service->revokeToken($token->tokenId, 'First revocation');
            $result = $this->service->revokeToken($token->tokenId, 'Second revocation');

            expect($result)->toBeFalse();
        });

        it('stores revocation details', function (): void {
            $token = $this->service->issueToken(
                type: TokenType::SOULBOUND,
                recipientId: 'user-123',
                metadata: [],
            );

            $this->service->revokeToken($token->tokenId, 'Account closed');

            $details = $this->service->getRevocationDetails($token->tokenId);

            expect($details)->not->toBeNull();
            expect($details['reason'])->toBe('Account closed');
        });
    });
});
