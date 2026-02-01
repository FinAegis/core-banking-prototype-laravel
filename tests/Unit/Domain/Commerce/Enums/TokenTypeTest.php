<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\TokenType;

describe('TokenType Enum', function (): void {
    it('has all expected token types', function (): void {
        $types = TokenType::cases();

        expect($types)->toHaveCount(4);
        expect(TokenType::SOULBOUND->value)->toBe('soulbound');
        expect(TokenType::TRANSFERABLE->value)->toBe('transferable');
        expect(TokenType::SEMI_FUNGIBLE->value)->toBe('semi_fungible');
        expect(TokenType::FUNGIBLE->value)->toBe('fungible');
    });

    it('returns correct labels', function (): void {
        expect(TokenType::SOULBOUND->label())->toBe('Soulbound Token');
        expect(TokenType::TRANSFERABLE->label())->toBe('Transferable Token');
        expect(TokenType::SEMI_FUNGIBLE->label())->toBe('Semi-Fungible Token');
        expect(TokenType::FUNGIBLE->label())->toBe('Fungible Token');
    });

    it('returns correct descriptions', function (): void {
        expect(TokenType::SOULBOUND->description())
            ->toContain('Non-transferable');
        expect(TokenType::TRANSFERABLE->description())
            ->toContain('freely transferred');
    });

    it('correctly identifies transferability', function (): void {
        expect(TokenType::SOULBOUND->isTransferable())->toBeFalse();
        expect(TokenType::TRANSFERABLE->isTransferable())->toBeTrue();
        expect(TokenType::SEMI_FUNGIBLE->isTransferable())->toBeTrue();
        expect(TokenType::FUNGIBLE->isTransferable())->toBeTrue();
    });
});
