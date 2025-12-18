<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use App\Domain\AgentProtocol\Services\EncryptionService;
use App\Domain\AgentProtocol\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DigitalSignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private DigitalSignatureService $service;

    private SignatureService $signatureService;

    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signatureService = new SignatureService();
        $this->encryptionService = new EncryptionService();
        $this->service = new DigitalSignatureService(
            $this->signatureService,
            $this->encryptionService
        );

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function itCanGenerateAgentKeyPair()
    {
        $agentId = 'agent_' . uniqid();

        // Create agent
        Agent::factory()->create(['agent_id' => $agentId]);

        $result = $this->service->generateAgentKeyPair($agentId, 'RSA');

        $this->assertArrayHasKey('agent_id', $result);
        $this->assertArrayHasKey('public_key', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertEquals($agentId, $result['agent_id']);
        $this->assertEquals('RSA', $result['type']);
        $this->assertNotEmpty($result['public_key']);

        // Verify keys are stored
        $this->assertTrue(Cache::has("agent_private_key:{$agentId}"));
        $this->assertTrue(Cache::has("agent_public_key:{$agentId}"));
    }

    /** @test */
    public function itCanSignAndVerifyAgentTransaction()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'      => 1000.00,
            'currency'    => 'USD',
            'recipient'   => 'agent_recipient_123',
            'description' => 'Test transaction',
        ];

        // Generate key pair for agent
        Agent::factory()->create(['agent_id' => $agentId]);
        $this->service->generateAgentKeyPair($agentId);

        // Sign transaction
        $signature = $this->service->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            ['security_level' => 'standard']
        );

        $this->assertArrayHasKey('signature', $signature);
        $this->assertArrayHasKey('algorithm', $signature);
        $this->assertArrayHasKey('transaction_id', $signature);
        $this->assertArrayHasKey('agent_id', $signature);
        $this->assertArrayHasKey('nonce', $signature);
        $this->assertArrayHasKey('expires_at', $signature);
        $this->assertEquals($transactionId, $signature['transaction_id']);
        $this->assertEquals($agentId, $signature['agent_id']);

        // Verify signature
        $verification = $this->service->verifyAgentSignature(
            $transactionId,
            $agentId,
            $transactionData,
            $signature['signature'],
            $signature
        );

        $this->assertTrue($verification['is_valid']);
        $this->assertEquals($transactionId, $verification['transaction_id']);
        $this->assertEquals($agentId, $verification['agent_id']);
    }

    /** @test */
    public function itDetectsInvalidSignatures()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'   => 1000.00,
            'currency' => 'USD',
        ];

        // Generate key pair
        Agent::factory()->create(['agent_id' => $agentId]);
        $this->service->generateAgentKeyPair($agentId);

        // Sign transaction
        $signature = $this->service->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData
        );

        // Modify transaction data
        $tamperedData = array_merge($transactionData, ['amount' => 2000.00]);

        // Verify with tampered data
        $verification = $this->service->verifyAgentSignature(
            $transactionId,
            $agentId,
            $tamperedData,
            $signature['signature'],
            $signature
        );

        $this->assertFalse($verification['is_valid']);
    }

    /** @test */
    public function itDetectsExpiredSignatures()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = ['amount' => 1000.00];

        // Generate key pair
        Agent::factory()->create(['agent_id' => $agentId]);
        $this->service->generateAgentKeyPair($agentId);

        // Sign transaction with short TTL
        $signature = $this->service->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            ['ttl' => -1] // Already expired
        );

        // Verify expired signature
        $verification = $this->service->verifyAgentSignature(
            $transactionId,
            $agentId,
            $transactionData,
            $signature['signature'],
            $signature
        );

        $this->assertFalse($verification['is_valid']);
        $this->assertEquals('Signature has expired', $verification['reason']);
    }

    /** @test */
    public function itPreventsReplayAttacksWithNonceVerification()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = ['amount' => 1000.00];

        // Generate key pair
        Agent::factory()->create(['agent_id' => $agentId]);
        $this->service->generateAgentKeyPair($agentId);

        // Sign transaction
        $signature = $this->service->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData
        );

        // First verification should succeed
        $verification1 = $this->service->verifyAgentSignature(
            $transactionId,
            $agentId,
            $transactionData,
            $signature['signature'],
            $signature
        );

        $this->assertTrue($verification1['is_valid']);

        // Second verification with same nonce should fail (replay attack)
        $verification2 = $this->service->verifyAgentSignature(
            $transactionId,
            $agentId,
            $transactionData,
            $signature['signature'],
            $signature
        );

        $this->assertFalse($verification2['is_valid']);
        $this->assertEquals('Invalid or reused nonce', $verification2['reason']);
    }

    /** @test */
    public function itCanCreateMultiPartySignatures()
    {
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'   => 5000.00,
            'currency' => 'USD',
            'type'     => 'multi_party',
        ];

        // Generate key pairs for multiple agents
        $participatingAgents = [];
        for ($i = 1; $i <= 3; $i++) {
            $agentId = "agent_{$i}_" . uniqid();
            Agent::factory()->create(['agent_id' => $agentId]);
            $keyPair = $this->signatureService->generateKeyPair('RSA');
            $participatingAgents[$agentId] = $keyPair['private_key'];

            // Store public key for verification
            Cache::put("agent_public_key:{$agentId}", $keyPair['public_key']);
        }

        // Create multi-party signature
        $multiSig = $this->service->createMultiPartySignature(
            $transactionId,
            $participatingAgents,
            $transactionData,
            2 // Require 2 out of 3 signatures
        );

        $this->assertArrayHasKey('threshold_met', $multiSig);
        $this->assertArrayHasKey('signatures', $multiSig);
        $this->assertArrayHasKey('required_signatures', $multiSig);
        $this->assertTrue($multiSig['threshold_met']);
        $this->assertEquals(2, $multiSig['required_signatures']);
        $this->assertCount(3, $multiSig['signatures']);
    }

    /** @test */
    public function itCanRotateAgentKeys()
    {
        $agentId = 'agent_' . uniqid();

        // Generate initial key pair
        Agent::factory()->create(['agent_id' => $agentId]);
        $initialKeys = $this->service->generateAgentKeyPair($agentId);
        $initialPublicKey = $initialKeys['public_key'];

        // Rotate keys
        $rotationResult = $this->service->rotateAgentKeys($agentId);

        $this->assertArrayHasKey('agent_id', $rotationResult);
        $this->assertArrayHasKey('new_public_key', $rotationResult);
        $this->assertArrayHasKey('rotated_at', $rotationResult);
        $this->assertArrayHasKey('next_rotation', $rotationResult);
        $this->assertEquals($agentId, $rotationResult['agent_id']);
        $this->assertNotEquals($initialPublicKey, $rotationResult['new_public_key']);

        // Verify old keys are archived (check within a small time window to avoid timing issues)
        $archived = false;
        $currentTime = time();
        // Check for archived keys in a 2-second window
        for ($offset = -1; $offset <= 1; $offset++) {
            $timestamp = $currentTime + $offset;
            if (
                Cache::has("archived_private_key:{$agentId}:{$timestamp}") &&
                Cache::has("archived_public_key:{$agentId}:{$timestamp}")
            ) {
                $archived = true;
                break;
            }
        }
        $this->assertTrue($archived, 'Archived keys should exist in cache');
    }

    /** @test */
    public function itSelectsAppropriateAlgorithmBasedOnSecurityLevel()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = ['amount' => 1000.00];

        Agent::factory()->create(['agent_id' => $agentId]);
        $this->service->generateAgentKeyPair($agentId);

        // Test standard security
        $standardSig = $this->service->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            ['security_level' => 'standard']
        );
        $this->assertEquals('RS256', $standardSig['algorithm']);

        // Test enhanced security
        $enhancedSig = $this->service->signAgentTransaction(
            $transactionId . '_2',
            $agentId,
            $transactionData,
            ['security_level' => 'enhanced']
        );
        $this->assertEquals('RS384', $enhancedSig['algorithm']);

        // Test maximum security
        $maxSig = $this->service->signAgentTransaction(
            $transactionId . '_3',
            $agentId,
            $transactionData,
            ['security_level' => 'maximum']
        );
        $this->assertEquals('RS512', $maxSig['algorithm']);
    }

    /** @test */
    public function itCanCreateSignatureProofForZeroKnowledgeVerification()
    {
        $transactionId = 'txn_' . uniqid();
        $agentId = 'agent_' . uniqid();
        $commitments = [
            'commitment_1' => hash('sha256', 'data1'),
            'commitment_2' => hash('sha256', 'data2'),
        ];

        $proof = $this->service->createSignatureProof(
            $transactionId,
            $agentId,
            $commitments
        );

        $this->assertArrayHasKey('transaction_id', $proof);
        $this->assertArrayHasKey('agent_id', $proof);
        $this->assertArrayHasKey('challenge', $proof);
        $this->assertArrayHasKey('commitments', $proof);
        $this->assertArrayHasKey('proof_hash', $proof);
        $this->assertArrayHasKey('timestamp', $proof);
        $this->assertEquals($transactionId, $proof['transaction_id']);
        $this->assertEquals($agentId, $proof['agent_id']);
        $this->assertEquals($commitments, $proof['commitments']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
