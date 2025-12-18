<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessageType;
use Tests\TestCase;

class MessageTypeTest extends TestCase
{
    public function test_request_requires_response(): void
    {
        $this->assertTrue(MessageType::REQUEST->requiresResponse());
        $this->assertTrue(MessageType::PAYMENT_REQUEST->requiresResponse());
        $this->assertTrue(MessageType::DISCOVERY_QUERY->requiresResponse());
        $this->assertTrue(MessageType::PROTOCOL_NEGOTIATION->requiresResponse());
        $this->assertTrue(MessageType::STATUS_INQUIRY->requiresResponse());
    }

    public function test_events_do_not_require_response(): void
    {
        $this->assertFalse(MessageType::EVENT->requiresResponse());
        $this->assertFalse(MessageType::NOTIFICATION->requiresResponse());
        $this->assertFalse(MessageType::RESPONSE->requiresResponse());
        $this->assertFalse(MessageType::HEARTBEAT->requiresResponse());
    }

    public function test_payment_related_types(): void
    {
        $this->assertTrue(MessageType::PAYMENT_REQUEST->isPaymentRelated());
        $this->assertTrue(MessageType::PAYMENT_RESPONSE->isPaymentRelated());
        $this->assertTrue(MessageType::PAYMENT_CONFIRMATION->isPaymentRelated());
        $this->assertTrue(MessageType::ESCROW_CREATE->isPaymentRelated());
        $this->assertTrue(MessageType::ESCROW_RELEASE->isPaymentRelated());

        $this->assertFalse(MessageType::REQUEST->isPaymentRelated());
        $this->assertFalse(MessageType::HEARTBEAT->isPaymentRelated());
    }

    public function test_expected_response_types(): void
    {
        $this->assertEquals(
            MessageType::RESPONSE,
            MessageType::REQUEST->getExpectedResponseType()
        );

        $this->assertEquals(
            MessageType::PAYMENT_RESPONSE,
            MessageType::PAYMENT_REQUEST->getExpectedResponseType()
        );

        $this->assertEquals(
            MessageType::DISCOVERY_RESPONSE,
            MessageType::DISCOVERY_QUERY->getExpectedResponseType()
        );

        $this->assertEquals(
            MessageType::PROTOCOL_AGREEMENT,
            MessageType::PROTOCOL_NEGOTIATION->getExpectedResponseType()
        );

        // Types without expected response return null
        $this->assertNull(MessageType::EVENT->getExpectedResponseType());
        $this->assertNull(MessageType::HEARTBEAT->getExpectedResponseType());
    }

    public function test_default_timeouts(): void
    {
        $this->assertEquals(5, MessageType::HEARTBEAT->getDefaultTimeout());
        $this->assertEquals(10, MessageType::STATUS_INQUIRY->getDefaultTimeout());
        $this->assertEquals(60, MessageType::PAYMENT_REQUEST->getDefaultTimeout());
        $this->assertEquals(30, MessageType::REQUEST->getDefaultTimeout());
    }

    public function test_should_persist(): void
    {
        // Heartbeat and status messages should not be persisted
        $this->assertFalse(MessageType::HEARTBEAT->shouldPersist());
        $this->assertFalse(MessageType::STATUS_INQUIRY->shouldPersist());
        $this->assertFalse(MessageType::STATUS_REPORT->shouldPersist());

        // All other messages should be persisted
        $this->assertTrue(MessageType::REQUEST->shouldPersist());
        $this->assertTrue(MessageType::PAYMENT_REQUEST->shouldPersist());
        $this->assertTrue(MessageType::ESCROW_CREATE->shouldPersist());
    }
}
