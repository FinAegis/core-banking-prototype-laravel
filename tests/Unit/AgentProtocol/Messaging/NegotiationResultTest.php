<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Messaging\NegotiationResult;
use App\Domain\AgentProtocol\Messaging\ProtocolAgreement;
use DateTimeImmutable;
use Tests\TestCase;

class NegotiationResultTest extends TestCase
{
    public function test_creates_success_result(): void
    {
        $agreement = new ProtocolAgreement(
            agreementId: 'agreement_123',
            version: '1.0',
            initiatorDid: 'did:finaegis:key:agent1',
            responderDid: 'did:finaegis:key:agent2',
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging'],
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $result = NegotiationResult::success($agreement);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->agreement);
        $this->assertNull($result->error);
        $this->assertFalse($result->timeout);
        $this->assertFalse($result->usedExistingAgreement());
    }

    public function test_creates_from_existing_agreement(): void
    {
        $agreement = new ProtocolAgreement(
            agreementId: 'agreement_existing',
            version: '1.0',
            initiatorDid: 'did:finaegis:key:agent1',
            responderDid: 'did:finaegis:key:agent2',
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging'],
            agreedAt: new DateTimeImmutable('-1 hour'),
            expiresAt: new DateTimeImmutable('+23 hours')
        );

        $result = NegotiationResult::fromExisting($agreement);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->usedExistingAgreement());
    }

    public function test_creates_timeout_result(): void
    {
        $result = NegotiationResult::timeout(
            'did:finaegis:key:agent1',
            'did:finaegis:key:agent2'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->timeout);
        $this->assertNull($result->agreement);
        $this->assertEquals('Negotiation timed out', $result->error);
    }

    public function test_creates_rejected_result(): void
    {
        $result = NegotiationResult::rejected(
            'did:finaegis:key:agent1',
            'did:finaegis:key:agent2',
            'Incompatible protocol versions'
        );

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->timeout);
        $this->assertEquals('Incompatible protocol versions', $result->error);
    }

    public function test_gets_version_from_agreement(): void
    {
        $agreement = new ProtocolAgreement(
            agreementId: 'agreement_123',
            version: '1.1',
            initiatorDid: 'did:finaegis:key:agent1',
            responderDid: 'did:finaegis:key:agent2',
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: [],
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $result = NegotiationResult::success($agreement);

        $this->assertEquals('1.1', $result->getVersion());
    }

    public function test_converts_to_array(): void
    {
        $result = NegotiationResult::timeout(
            'did:finaegis:key:agent1',
            'did:finaegis:key:agent2'
        );

        $array = $result->toArray();

        $this->assertFalse($array['success']);
        $this->assertTrue($array['timeout']);
        $this->assertEquals('did:finaegis:key:agent1', $array['initiatorDid']);
        $this->assertEquals('did:finaegis:key:agent2', $array['targetDid']);
    }
}
