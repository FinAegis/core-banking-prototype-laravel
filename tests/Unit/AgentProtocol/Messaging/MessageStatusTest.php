<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessageStatus;
use Tests\TestCase;

class MessageStatusTest extends TestCase
{
    public function test_terminal_statuses(): void
    {
        $this->assertTrue(MessageStatus::ACKNOWLEDGED->isTerminal());
        $this->assertTrue(MessageStatus::FAILED->isTerminal());
        $this->assertTrue(MessageStatus::EXPIRED->isTerminal());
        $this->assertTrue(MessageStatus::CANCELLED->isTerminal());
        $this->assertTrue(MessageStatus::COMPENSATED->isTerminal());
    }

    public function test_non_terminal_statuses(): void
    {
        $this->assertFalse(MessageStatus::PENDING->isTerminal());
        $this->assertFalse(MessageStatus::QUEUED->isTerminal());
        $this->assertFalse(MessageStatus::ROUTING->isTerminal());
        $this->assertFalse(MessageStatus::DELIVERING->isTerminal());
        $this->assertFalse(MessageStatus::DELIVERED->isTerminal());
        $this->assertFalse(MessageStatus::RETRYING->isTerminal());
    }

    public function test_successful_statuses(): void
    {
        $this->assertTrue(MessageStatus::DELIVERED->isSuccessful());
        $this->assertTrue(MessageStatus::ACKNOWLEDGED->isSuccessful());
        $this->assertTrue(MessageStatus::COMPENSATED->isSuccessful());
    }

    public function test_unsuccessful_statuses(): void
    {
        $this->assertFalse(MessageStatus::PENDING->isSuccessful());
        $this->assertFalse(MessageStatus::FAILED->isSuccessful());
        $this->assertFalse(MessageStatus::EXPIRED->isSuccessful());
    }

    public function test_retryable_statuses(): void
    {
        $this->assertTrue(MessageStatus::FAILED->canRetry());
        $this->assertTrue(MessageStatus::EXPIRED->canRetry());
    }

    public function test_non_retryable_statuses(): void
    {
        $this->assertFalse(MessageStatus::PENDING->canRetry());
        $this->assertFalse(MessageStatus::ACKNOWLEDGED->canRetry());
        $this->assertFalse(MessageStatus::CANCELLED->canRetry());
    }

    public function test_next_status_workflow(): void
    {
        $this->assertEquals(MessageStatus::QUEUED, MessageStatus::PENDING->getNextStatus());
        $this->assertEquals(MessageStatus::ROUTING, MessageStatus::QUEUED->getNextStatus());
        $this->assertEquals(MessageStatus::DELIVERING, MessageStatus::ROUTING->getNextStatus());
        $this->assertEquals(MessageStatus::DELIVERED, MessageStatus::DELIVERING->getNextStatus());
        $this->assertEquals(MessageStatus::ACKNOWLEDGED, MessageStatus::DELIVERED->getNextStatus());
    }

    public function test_terminal_statuses_have_no_next(): void
    {
        $this->assertNull(MessageStatus::ACKNOWLEDGED->getNextStatus());
        $this->assertNull(MessageStatus::FAILED->getNextStatus());
        $this->assertNull(MessageStatus::CANCELLED->getNextStatus());
    }
}
