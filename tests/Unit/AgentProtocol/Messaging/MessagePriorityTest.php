<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use Tests\TestCase;

class MessagePriorityTest extends TestCase
{
    public function test_priority_values(): void
    {
        $this->assertEquals(100, MessagePriority::CRITICAL->value);
        $this->assertEquals(75, MessagePriority::HIGH->value);
        $this->assertEquals(50, MessagePriority::NORMAL->value);
        $this->assertEquals(25, MessagePriority::LOW->value);
        $this->assertEquals(10, MessagePriority::BACKGROUND->value);
    }

    public function test_queue_names(): void
    {
        $this->assertEquals('agent-messages-critical', MessagePriority::CRITICAL->getQueueName());
        $this->assertEquals('agent-messages-high', MessagePriority::HIGH->getQueueName());
        $this->assertEquals('agent-messages', MessagePriority::NORMAL->getQueueName());
        $this->assertEquals('agent-messages-low', MessagePriority::LOW->getQueueName());
        $this->assertEquals('agent-messages-background', MessagePriority::BACKGROUND->getQueueName());
    }

    public function test_max_processing_time(): void
    {
        $this->assertEquals(5, MessagePriority::CRITICAL->getMaxProcessingTime());
        $this->assertEquals(15, MessagePriority::HIGH->getMaxProcessingTime());
        $this->assertEquals(30, MessagePriority::NORMAL->getMaxProcessingTime());
        $this->assertEquals(60, MessagePriority::LOW->getMaxProcessingTime());
        $this->assertEquals(300, MessagePriority::BACKGROUND->getMaxProcessingTime());
    }

    public function test_retry_delay_multiplier(): void
    {
        $this->assertEquals(0.5, MessagePriority::CRITICAL->getRetryDelayMultiplier());
        $this->assertEquals(1.0, MessagePriority::HIGH->getRetryDelayMultiplier());
        $this->assertEquals(1.5, MessagePriority::NORMAL->getRetryDelayMultiplier());
        $this->assertEquals(2.0, MessagePriority::LOW->getRetryDelayMultiplier());
        $this->assertEquals(3.0, MessagePriority::BACKGROUND->getRetryDelayMultiplier());
    }

    public function test_max_retries(): void
    {
        $this->assertEquals(5, MessagePriority::CRITICAL->getMaxRetries());
        $this->assertEquals(4, MessagePriority::HIGH->getMaxRetries());
        $this->assertEquals(3, MessagePriority::NORMAL->getMaxRetries());
        $this->assertEquals(2, MessagePriority::LOW->getMaxRetries());
        $this->assertEquals(1, MessagePriority::BACKGROUND->getMaxRetries());
    }

    public function test_from_numeric_conversion(): void
    {
        $this->assertEquals(MessagePriority::CRITICAL, MessagePriority::fromNumeric(100));
        $this->assertEquals(MessagePriority::CRITICAL, MessagePriority::fromNumeric(95));
        $this->assertEquals(MessagePriority::HIGH, MessagePriority::fromNumeric(75));
        $this->assertEquals(MessagePriority::HIGH, MessagePriority::fromNumeric(70));
        $this->assertEquals(MessagePriority::NORMAL, MessagePriority::fromNumeric(50));
        $this->assertEquals(MessagePriority::NORMAL, MessagePriority::fromNumeric(40));
        $this->assertEquals(MessagePriority::LOW, MessagePriority::fromNumeric(25));
        $this->assertEquals(MessagePriority::LOW, MessagePriority::fromNumeric(20));
        $this->assertEquals(MessagePriority::BACKGROUND, MessagePriority::fromNumeric(10));
        $this->assertEquals(MessagePriority::BACKGROUND, MessagePriority::fromNumeric(0));
    }
}
