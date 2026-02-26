<?php

declare(strict_types=1);

namespace Tests\Domain\CardIssuance\Enums;

use App\Domain\CardIssuance\Enums\CardStatus;
use PHPUnit\Framework\TestCase;

class CardStatusTest extends TestCase
{
    public function test_only_active_is_usable(): void
    {
        $this->assertTrue(CardStatus::ACTIVE->isUsable());
        $this->assertFalse(CardStatus::PENDING->isUsable());
        $this->assertFalse(CardStatus::FROZEN->isUsable());
        $this->assertFalse(CardStatus::CANCELLED->isUsable());
        $this->assertFalse(CardStatus::EXPIRED->isUsable());
    }

    public function test_pending_can_transition_to_active_or_cancelled(): void
    {
        $this->assertTrue(CardStatus::PENDING->canTransitionTo(CardStatus::ACTIVE));
        $this->assertTrue(CardStatus::PENDING->canTransitionTo(CardStatus::CANCELLED));
        $this->assertFalse(CardStatus::PENDING->canTransitionTo(CardStatus::FROZEN));
        $this->assertFalse(CardStatus::PENDING->canTransitionTo(CardStatus::EXPIRED));
    }

    public function test_active_can_transition_to_frozen_cancelled_expired(): void
    {
        $this->assertTrue(CardStatus::ACTIVE->canTransitionTo(CardStatus::FROZEN));
        $this->assertTrue(CardStatus::ACTIVE->canTransitionTo(CardStatus::CANCELLED));
        $this->assertTrue(CardStatus::ACTIVE->canTransitionTo(CardStatus::EXPIRED));
        $this->assertFalse(CardStatus::ACTIVE->canTransitionTo(CardStatus::PENDING));
    }

    public function test_frozen_can_transition_to_active_or_cancelled(): void
    {
        $this->assertTrue(CardStatus::FROZEN->canTransitionTo(CardStatus::ACTIVE));
        $this->assertTrue(CardStatus::FROZEN->canTransitionTo(CardStatus::CANCELLED));
        $this->assertFalse(CardStatus::FROZEN->canTransitionTo(CardStatus::PENDING));
        $this->assertFalse(CardStatus::FROZEN->canTransitionTo(CardStatus::EXPIRED));
    }

    public function test_cancelled_cannot_transition(): void
    {
        foreach (CardStatus::cases() as $status) {
            $this->assertFalse(CardStatus::CANCELLED->canTransitionTo($status));
        }
    }

    public function test_expired_cannot_transition(): void
    {
        foreach (CardStatus::cases() as $status) {
            $this->assertFalse(CardStatus::EXPIRED->canTransitionTo($status));
        }
    }

    public function test_all_cases_have_string_values(): void
    {
        $this->assertEquals('pending', CardStatus::PENDING->value);
        $this->assertEquals('active', CardStatus::ACTIVE->value);
        $this->assertEquals('frozen', CardStatus::FROZEN->value);
        $this->assertEquals('cancelled', CardStatus::CANCELLED->value);
        $this->assertEquals('expired', CardStatus::EXPIRED->value);
    }
}
