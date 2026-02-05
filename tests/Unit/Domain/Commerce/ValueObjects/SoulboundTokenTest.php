<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\TokenType;
use App\Domain\Commerce\ValueObjects\SoulboundToken;

uses(Tests\TestCase::class);

describe('SoulboundToken Value Object', function (): void {
    it('creates a valid token', function (): void {
        $token = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: ['badge_type' => 'kyc'],
            issuedAt: new DateTimeImmutable('2026-01-01'),
            expiresAt: new DateTimeImmutable('2027-01-01'),
        );

        expect($token->tokenId)->toBe('token-123');
        expect($token->type)->toBe(TokenType::SOULBOUND);
        expect($token->issuerId)->toBe('issuer-1');
        expect($token->recipientId)->toBe('user-456');
        expect($token->metadata)->toBe(['badge_type' => 'kyc']);
    });

    it('detects expired tokens', function (): void {
        $expiredToken = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: [],
            issuedAt: new DateTimeImmutable('2020-01-01'),
            expiresAt: new DateTimeImmutable('2021-01-01'),
        );

        expect($expiredToken->isExpired())->toBeTrue();
        expect($expiredToken->isValid())->toBeFalse();
    });

    it('handles non-expiring tokens', function (): void {
        $token = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: [],
            issuedAt: new DateTimeImmutable(),
            expiresAt: null,
        );

        expect($token->isExpired())->toBeFalse();
        expect($token->isValid())->toBeTrue();
        expect($token->getRemainingValiditySeconds())->toBe(PHP_INT_MAX);
    });

    it('detects revoked tokens', function (): void {
        $revokedToken = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: [],
            issuedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 year'),
            revokedAt: new DateTimeImmutable(),
            revocationReason: 'Fraud detected',
        );

        expect($revokedToken->isRevoked())->toBeTrue();
        expect($revokedToken->isValid())->toBeFalse();
        expect($revokedToken->revocationReason)->toBe('Fraud detected');
    });

    it('generates consistent token hash', function (): void {
        $token = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: [],
            issuedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );

        $hash1 = $token->getTokenHash();
        $hash2 = $token->getTokenHash();

        expect($hash1)->toBe($hash2);
        expect(strlen($hash1))->toBe(64); // SHA256 produces 64 hex chars
    });

    it('retrieves metadata correctly', function (): void {
        $token = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: ['tier' => 'gold', 'level' => 3],
            issuedAt: new DateTimeImmutable(),
        );

        expect($token->getMetadata('tier'))->toBe('gold');
        expect($token->getMetadata('level'))->toBe(3);
        expect($token->getMetadata('missing', 'default'))->toBe('default');
    });

    it('serializes to array correctly', function (): void {
        $issuedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $expiresAt = new DateTimeImmutable('2027-01-01T00:00:00+00:00');

        $token = new SoulboundToken(
            tokenId: 'token-123',
            type: TokenType::SOULBOUND,
            issuerId: 'issuer-1',
            recipientId: 'user-456',
            metadata: ['key' => 'value'],
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
        );

        $array = $token->toArray();

        expect($array['token_id'])->toBe('token-123');
        expect($array['type'])->toBe('soulbound');
        expect($array['issuer_id'])->toBe('issuer-1');
        expect($array['metadata'])->toBe(['key' => 'value']);
    });

    it('deserializes from array correctly', function (): void {
        $data = [
            'token_id'     => 'token-123',
            'type'         => 'soulbound',
            'issuer_id'    => 'issuer-1',
            'recipient_id' => 'user-456',
            'metadata'     => ['key' => 'value'],
            'issued_at'    => '2026-01-01T00:00:00+00:00',
            'expires_at'   => '2027-01-01T00:00:00+00:00',
        ];

        $token = SoulboundToken::fromArray($data);

        expect($token->tokenId)->toBe('token-123');
        expect($token->type)->toBe(TokenType::SOULBOUND);
        expect($token->metadata)->toBe(['key' => 'value']);
    });
});
