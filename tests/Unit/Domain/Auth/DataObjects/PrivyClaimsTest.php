<?php

declare(strict_types=1);

use App\Domain\Auth\DataObjects\PrivyClaims;

uses(Tests\TestCase::class);

/**
 * @param array<int, array<string, mixed>> $linkedAccounts
 */
function privy_claims_with(array $linkedAccounts): PrivyClaims
{
    return PrivyClaims::fromPayload([
        'sub'             => 'did:privy:test',
        'iss'             => 'privy.io',
        'aud'             => 'app',
        'iat'             => time() - 60,
        'exp'             => time() + 3600,
        'linked_accounts' => $linkedAccounts,
    ]);
}

it('returns the first linked email lowercased', function (): void {
    $claims = privy_claims_with([
        ['type' => 'wallet', 'address' => '0xabc'],
        ['type' => 'email', 'address' => 'Jane@Example.com'],
        ['type' => 'email', 'address' => 'second@example.com'],
    ]);

    expect($claims->email())->toBe('jane@example.com');
});

it('returns null when there are no linked accounts', function (): void {
    expect(privy_claims_with([])->email())->toBeNull();
});

it('returns null when no linked account is of type email', function (): void {
    $claims = privy_claims_with([
        ['type' => 'wallet', 'address' => '0xabc'],
        ['type' => 'phone', 'address' => '+15551234'],
    ]);

    expect($claims->email())->toBeNull();
});

it('skips email accounts with empty addresses', function (): void {
    $claims = privy_claims_with([
        ['type' => 'email', 'address' => ''],
        ['type' => 'email', 'address' => 'real@example.com'],
    ]);

    expect($claims->email())->toBe('real@example.com');
});

it('skips email accounts where the address field is missing or non-string', function (): void {
    $claims = privy_claims_with([
        ['type' => 'email'],
        ['type' => 'email', 'address' => 123],
        ['type' => 'email', 'address' => 'good@example.com'],
    ]);

    expect($claims->email())->toBe('good@example.com');
});
