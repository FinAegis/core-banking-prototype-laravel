<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Enums;

use App\Domain\AgentProtocol\Enums\AgentScope;
use PHPUnit\Framework\TestCase;

class AgentScopeTest extends TestCase
{
    public function test_has_correct_string_values(): void
    {
        $this->assertEquals('payments:read', AgentScope::PAYMENTS_READ->value);
        $this->assertEquals('payments:*', AgentScope::PAYMENTS_ALL->value);
        $this->assertEquals('messages:send', AgentScope::MESSAGES_SEND->value);
        $this->assertEquals('escrow:create', AgentScope::ESCROW_CREATE->value);
        $this->assertEquals('*', AgentScope::UNIVERSAL->value);
    }

    public function test_returns_correct_descriptions(): void
    {
        $this->assertStringContainsString('View payment', AgentScope::PAYMENTS_READ->description());
        $this->assertStringContainsString('Create new payment', AgentScope::PAYMENTS_CREATE->description());
        $this->assertStringContainsString('A2A messages', AgentScope::MESSAGES_READ->description());
        $this->assertStringContainsString('Full access', AgentScope::UNIVERSAL->description());
    }

    public function test_identifies_categories_correctly(): void
    {
        $this->assertEquals('payments', AgentScope::PAYMENTS_READ->category());
        $this->assertEquals('payments', AgentScope::PAYMENTS_ALL->category());
        $this->assertEquals('messages', AgentScope::MESSAGES_SEND->category());
        $this->assertEquals('escrow', AgentScope::ESCROW_CREATE->category());
        $this->assertEquals('*', AgentScope::UNIVERSAL->category());
    }

    public function test_identifies_wildcards(): void
    {
        $this->assertTrue(AgentScope::PAYMENTS_ALL->isWildcard());
        $this->assertTrue(AgentScope::MESSAGES_ALL->isWildcard());
        $this->assertTrue(AgentScope::UNIVERSAL->isWildcard());

        $this->assertFalse(AgentScope::PAYMENTS_READ->isWildcard());
        $this->assertFalse(AgentScope::MESSAGES_SEND->isWildcard());
    }

    public function test_universal_scope_covers_everything(): void
    {
        $this->assertTrue(AgentScope::UNIVERSAL->covers(AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::UNIVERSAL->covers(AgentScope::MESSAGES_ALL));
        $this->assertTrue(AgentScope::UNIVERSAL->covers('any:scope'));
    }

    public function test_exact_match_covers_itself(): void
    {
        $this->assertTrue(AgentScope::PAYMENTS_READ->covers(AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::MESSAGES_SEND->covers('messages:send'));
    }

    public function test_category_wildcard_covers_category_scopes(): void
    {
        $this->assertTrue(AgentScope::PAYMENTS_ALL->covers(AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::PAYMENTS_ALL->covers(AgentScope::PAYMENTS_CREATE));
        $this->assertTrue(AgentScope::PAYMENTS_ALL->covers('payments:cancel'));

        $this->assertFalse(AgentScope::PAYMENTS_ALL->covers(AgentScope::ESCROW_READ));
        $this->assertFalse(AgentScope::PAYMENTS_ALL->covers('escrow:create'));
    }

    public function test_specific_scope_does_not_cover_other_scopes(): void
    {
        $this->assertFalse(AgentScope::PAYMENTS_READ->covers(AgentScope::PAYMENTS_CREATE));
        $this->assertFalse(AgentScope::MESSAGES_READ->covers(AgentScope::MESSAGES_SEND));
    }

    public function test_gets_scopes_in_category(): void
    {
        $paymentScopes = AgentScope::inCategory('payments');

        $this->assertContains(AgentScope::PAYMENTS_READ, $paymentScopes);
        $this->assertContains(AgentScope::PAYMENTS_CREATE, $paymentScopes);
        $this->assertContains(AgentScope::PAYMENTS_ALL, $paymentScopes);
        $this->assertNotContains(AgentScope::ESCROW_READ, $paymentScopes);
    }

    public function test_returns_default_scopes(): void
    {
        $defaults = AgentScope::defaults();

        $this->assertContains(AgentScope::PAYMENTS_READ, $defaults);
        $this->assertContains(AgentScope::WALLET_READ, $defaults);
        $this->assertContains(AgentScope::AGENT_READ, $defaults);
        $this->assertContains(AgentScope::REPUTATION_READ, $defaults);
        $this->assertContains(AgentScope::MESSAGES_READ, $defaults);

        // Should not contain write scopes
        $this->assertNotContains(AgentScope::PAYMENTS_CREATE, $defaults);
        $this->assertNotContains(AgentScope::MESSAGES_SEND, $defaults);
    }

    public function test_returns_read_only_scopes(): void
    {
        $readOnly = AgentScope::readOnly();

        $this->assertContains(AgentScope::PAYMENTS_READ, $readOnly);
        $this->assertContains(AgentScope::ESCROW_READ, $readOnly);
        $this->assertContains(AgentScope::MESSAGES_READ, $readOnly);

        // Should not contain write scopes
        $this->assertNotContains(AgentScope::PAYMENTS_CREATE, $readOnly);
        $this->assertNotContains(AgentScope::ESCROW_CREATE, $readOnly);
    }

    public function test_returns_write_scopes(): void
    {
        $writeScopes = AgentScope::writeScopes();

        $this->assertContains(AgentScope::PAYMENTS_CREATE, $writeScopes);
        $this->assertContains(AgentScope::ESCROW_CREATE, $writeScopes);
        $this->assertContains(AgentScope::MESSAGES_SEND, $writeScopes);

        // Should not contain read scopes
        $this->assertNotContains(AgentScope::PAYMENTS_READ, $writeScopes);
        $this->assertNotContains(AgentScope::ESCROW_READ, $writeScopes);
    }

    public function test_returns_wildcard_scopes(): void
    {
        $wildcards = AgentScope::wildcards();

        $this->assertContains(AgentScope::PAYMENTS_ALL, $wildcards);
        $this->assertContains(AgentScope::ESCROW_ALL, $wildcards);
        $this->assertContains(AgentScope::MESSAGES_ALL, $wildcards);
        $this->assertContains(AgentScope::UNIVERSAL, $wildcards);

        // Should not contain specific scopes
        $this->assertNotContains(AgentScope::PAYMENTS_READ, $wildcards);
        $this->assertNotContains(AgentScope::MESSAGES_SEND, $wildcards);
    }

    public function test_converts_values_to_enums(): void
    {
        $values = ['payments:read', 'messages:send', 'invalid:scope'];
        $enums = AgentScope::fromValues($values);

        $this->assertCount(2, $enums);
        $this->assertContains(AgentScope::PAYMENTS_READ, $enums);
        $this->assertContains(AgentScope::MESSAGES_SEND, $enums);
    }

    public function test_checks_scope_membership_with_empty_array(): void
    {
        // Empty scopes means all allowed (backward compatibility)
        $this->assertTrue(AgentScope::hasScope([], AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::hasScope([], 'any:scope'));
    }

    public function test_checks_scope_membership_with_universal(): void
    {
        $scopes = ['*'];
        $this->assertTrue(AgentScope::hasScope($scopes, AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::hasScope($scopes, 'any:scope'));
    }

    public function test_checks_scope_membership_with_exact_match(): void
    {
        $scopes = ['payments:read', 'messages:send'];

        $this->assertTrue(AgentScope::hasScope($scopes, 'payments:read'));
        $this->assertTrue(AgentScope::hasScope($scopes, AgentScope::MESSAGES_SEND));
        $this->assertFalse(AgentScope::hasScope($scopes, 'escrow:create'));
    }

    public function test_checks_scope_membership_with_category_wildcard(): void
    {
        $scopes = ['payments:*'];

        $this->assertTrue(AgentScope::hasScope($scopes, 'payments:read'));
        $this->assertTrue(AgentScope::hasScope($scopes, 'payments:create'));
        $this->assertTrue(AgentScope::hasScope($scopes, AgentScope::PAYMENTS_CANCEL));
        $this->assertFalse(AgentScope::hasScope($scopes, 'escrow:read'));
    }

    public function test_checks_scope_membership_with_enum_values(): void
    {
        $scopes = [AgentScope::PAYMENTS_READ, AgentScope::MESSAGES_ALL];

        $this->assertTrue(AgentScope::hasScope($scopes, AgentScope::PAYMENTS_READ));
        $this->assertTrue(AgentScope::hasScope($scopes, AgentScope::MESSAGES_SEND));
        $this->assertFalse(AgentScope::hasScope($scopes, AgentScope::ESCROW_READ));
    }
}
