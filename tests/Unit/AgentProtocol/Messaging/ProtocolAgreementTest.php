<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Messaging\NegotiationResult;
use App\Domain\AgentProtocol\Messaging\ProtocolAgreement;
use DateTimeImmutable;
use Tests\TestCase;

class ProtocolAgreementTest extends TestCase
{
    private function createAgreement(
        ?DateTimeImmutable $expiresAt = null
    ): ProtocolAgreement {
        return new ProtocolAgreement(
            agreementId: 'agreement_123',
            version: '1.0',
            initiatorDid: 'did:finaegis:key:agent1',
            responderDid: 'did:finaegis:key:agent2',
            encryptionMethod: 'aes-256-gcm',
            signatureMethod: 'ed25519',
            capabilities: ['messaging', 'payments', 'escrow'],
            agreedAt: new DateTimeImmutable(),
            expiresAt: $expiresAt ?? new DateTimeImmutable('+24 hours')
        );
    }

    public function test_creates_agreement(): void
    {
        $agreement = $this->createAgreement();

        $this->assertEquals('agreement_123', $agreement->agreementId);
        $this->assertEquals('1.0', $agreement->version);
        $this->assertEquals('did:finaegis:key:agent1', $agreement->initiatorDid);
        $this->assertEquals('did:finaegis:key:agent2', $agreement->responderDid);
        $this->assertEquals('aes-256-gcm', $agreement->encryptionMethod);
        $this->assertEquals('ed25519', $agreement->signatureMethod);
        $this->assertContains('messaging', $agreement->capabilities);
    }

    public function test_checks_expiration(): void
    {
        $validAgreement = $this->createAgreement(
            new DateTimeImmutable('+1 hour')
        );
        $this->assertFalse($validAgreement->isExpired());

        $expiredAgreement = $this->createAgreement(
            new DateTimeImmutable('-1 hour')
        );
        $this->assertTrue($expiredAgreement->isExpired());
    }

    public function test_checks_capability(): void
    {
        $agreement = $this->createAgreement();

        $this->assertTrue($agreement->hasCapability('messaging'));
        $this->assertTrue($agreement->hasCapability('payments'));
        $this->assertTrue($agreement->hasCapability('escrow'));
        $this->assertFalse($agreement->hasCapability('streaming'));
    }

    public function test_checks_agent_involvement(): void
    {
        $agreement = $this->createAgreement();

        $this->assertTrue($agreement->involvesAgent('did:finaegis:key:agent1'));
        $this->assertTrue($agreement->involvesAgent('did:finaegis:key:agent2'));
        $this->assertFalse($agreement->involvesAgent('did:finaegis:key:agent3'));
    }

    public function test_gets_other_party(): void
    {
        $agreement = $this->createAgreement();

        $this->assertEquals(
            'did:finaegis:key:agent2',
            $agreement->getOtherParty('did:finaegis:key:agent1')
        );

        $this->assertEquals(
            'did:finaegis:key:agent1',
            $agreement->getOtherParty('did:finaegis:key:agent2')
        );

        $this->assertNull($agreement->getOtherParty('did:finaegis:key:agent3'));
    }

    public function test_calculates_remaining_validity(): void
    {
        $agreement = $this->createAgreement(
            new DateTimeImmutable('+1 hour')
        );

        $remaining = $agreement->getRemainingValiditySeconds();

        // Should be approximately 3600 seconds (1 hour)
        $this->assertGreaterThan(3500, $remaining);
        $this->assertLessThanOrEqual(3600, $remaining);
    }

    public function test_expired_agreement_has_zero_remaining_validity(): void
    {
        $agreement = $this->createAgreement(
            new DateTimeImmutable('-1 hour')
        );

        $this->assertEquals(0, $agreement->getRemainingValiditySeconds());
    }

    public function test_converts_to_array_and_back(): void
    {
        $original = $this->createAgreement();

        $array = $original->toArray();
        $restored = ProtocolAgreement::fromArray($array);

        $this->assertEquals($original->agreementId, $restored->agreementId);
        $this->assertEquals($original->version, $restored->version);
        $this->assertEquals($original->initiatorDid, $restored->initiatorDid);
        $this->assertEquals($original->responderDid, $restored->responderDid);
        $this->assertEquals($original->encryptionMethod, $restored->encryptionMethod);
        $this->assertEquals($original->signatureMethod, $restored->signatureMethod);
        $this->assertEquals($original->capabilities, $restored->capabilities);
    }

    public function test_json_serializes(): void
    {
        $agreement = $this->createAgreement();

        $json = json_encode($agreement, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('agreement_123', $decoded['agreementId']);
        $this->assertEquals('1.0', $decoded['version']);
    }
}

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
