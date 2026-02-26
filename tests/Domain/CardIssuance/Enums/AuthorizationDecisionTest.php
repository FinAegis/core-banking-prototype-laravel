<?php

declare(strict_types=1);

namespace Tests\Domain\CardIssuance\Enums;

use App\Domain\CardIssuance\Enums\AuthorizationDecision;
use PHPUnit\Framework\TestCase;

class AuthorizationDecisionTest extends TestCase
{
    public function test_only_approved_is_approved(): void
    {
        $this->assertTrue(AuthorizationDecision::APPROVED->isApproved());

        foreach (AuthorizationDecision::cases() as $decision) {
            if ($decision === AuthorizationDecision::APPROVED) {
                continue;
            }
            $this->assertFalse($decision->isApproved(), "{$decision->value} should not be approved");
        }
    }

    public function test_get_message_returns_non_empty_string_for_all_cases(): void
    {
        foreach (AuthorizationDecision::cases() as $decision) {
            $message = $decision->getMessage();
            $this->assertNotEmpty($message, "Message for {$decision->value} should not be empty");
            $this->assertNotEquals('', $message);
        }
    }

    public function test_approved_message_is_positive(): void
    {
        $this->assertStringContainsString('approved', strtolower(AuthorizationDecision::APPROVED->getMessage()));
    }

    public function test_declined_messages_describe_reason(): void
    {
        $this->assertStringContainsString('balance', strtolower(AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS->getMessage()));
        $this->assertStringContainsString('frozen', strtolower(AuthorizationDecision::DECLINED_CARD_FROZEN->getMessage()));
        $this->assertStringContainsString('cancelled', strtolower(AuthorizationDecision::DECLINED_CARD_CANCELLED->getMessage()));
        $this->assertStringContainsString('limit', strtolower(AuthorizationDecision::DECLINED_LIMIT_EXCEEDED->getMessage()));
        $this->assertStringContainsString('suspicious', strtolower(AuthorizationDecision::DECLINED_FRAUD_SUSPECTED->getMessage()));
        $this->assertStringContainsString('blocked', strtolower(AuthorizationDecision::DECLINED_MERCHANT_BLOCKED->getMessage()));
    }
}
