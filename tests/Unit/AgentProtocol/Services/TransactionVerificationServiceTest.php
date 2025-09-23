<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use App\Domain\AgentProtocol\Services\EncryptionService;
use App\Domain\AgentProtocol\Services\FraudDetectionService;
use App\Domain\AgentProtocol\Services\SignatureService;
use App\Domain\AgentProtocol\Services\TransactionVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionVerificationService $service;

    private DigitalSignatureService $signatureService;

    private EncryptionService $encryptionService;

    private FraudDetectionService $fraudService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signatureService = new DigitalSignatureService(
            new SignatureService(),
            new EncryptionService()
        );
        $this->encryptionService = new EncryptionService();
        $this->fraudService = new FraudDetectionService();

        $this->service = new TransactionVerificationService(
            $this->signatureService,
            $this->encryptionService,
            $this->fraudService
        );

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_verifies_valid_transaction_with_basic_level()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'    => 100.00,
            'currency'  => 'USD',
            'recipient' => 'recipient_123',
        ];

        // Setup agent
        $agent = Agent::factory()->create([
            'agent_id'     => $agentId,
            'status'       => 'active',
            'is_suspended' => false,
        ]);

        // Generate keys and sign transaction
        $this->signatureService->generateAgentKeyPair($agentId);
        $signature = $this->signatureService->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData
        );

        // Verify transaction
        $result = $this->service->verifyTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            $signature,
            'basic'
        );

        $this->assertEquals('approved', $result['status']);
        $this->assertArrayHasKey('checks', $result);
        $this->assertTrue($result['checks']['signature']['passed']);
        $this->assertTrue($result['checks']['agent']['passed']);
        $this->assertLessThanOrEqual(30, $result['risk_score']);
        $this->assertEquals('low', $result['risk_level']);
    }

    /** @test */
    public function it_rejects_transaction_with_invalid_signature()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'   => 100.00,
            'currency' => 'USD',
        ];

        // Setup agent
        Agent::factory()->create([
            'agent_id' => $agentId,
            'status'   => 'active',
        ]);

        // Invalid signature metadata
        $invalidMetadata = [
            'signature'  => 'invalid_signature_data',
            'algorithm'  => 'RS256',
            'public_key' => 'invalid_key',
        ];

        // Verify transaction
        $result = $this->service->verifyTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            $invalidMetadata,
            'standard'
        );

        $this->assertEquals('rejected', $result['status']);
        $this->assertFalse($result['checks']['signature']['passed']);
        $this->assertEquals('critical', $result['checks']['signature']['severity']);
    }

    /** @test */
    public function it_detects_suspended_agent()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = ['amount' => 100.00];

        // Setup suspended agent
        Agent::factory()->create([
            'agent_id'     => $agentId,
            'status'       => 'active',
            'is_suspended' => true,
        ]);

        // Generate valid signature
        $this->signatureService->generateAgentKeyPair($agentId);
        $signature = $this->signatureService->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData
        );

        // Verify transaction
        $result = $this->service->verifyTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            $signature,
            'standard'
        );

        $this->assertEquals('rejected', $result['status']);
        $this->assertFalse($result['checks']['agent']['passed']);
        $this->assertEquals('Agent suspended', $result['checks']['agent']['reason']);
    }

    /** @test */
    public function it_performs_velocity_checks()
    {
        $agentId = 'agent_' . uniqid();
        $amount = 5000.00;

        // Create agent with transactions
        Agent::factory()->create(['agent_id' => $agentId]);

        // Create an AgentIdentity once for this test
        $fromIdentity = \App\Domain\AgentProtocol\Models\AgentIdentity::factory()->create([
            'agent_id' => $agentId,
        ]);

        // Create recent transactions to trigger velocity limits
        for ($i = 0; $i < 15; $i++) {
            AgentTransaction::factory()->create([
                'from_agent_id' => $agentId,
                'amount'        => 1000,
                'status'        => 'completed',
                'created_at'    => now()->subMinutes($i * 5),
            ]);
        }

        // Perform velocity check
        $result = $this->service->performVelocityChecks($agentId, $amount);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['violations']);
        $this->assertArrayHasKey('type', $result['violations'][0]);
        $this->assertArrayHasKey('period', $result['violations'][0]);
    }

    /** @test */
    public function it_verifies_transaction_integrity()
    {
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'    => 1000.00,
            'currency'  => 'USD',
            'recipient' => 'recipient_123',
            'sender'    => 'sender_456',
        ];

        // Create agent identities first
        $senderIdentity = \App\Domain\AgentProtocol\Models\AgentIdentity::factory()->create([
            'agent_id' => 'sender_456',
        ]);
        $recipientIdentity = \App\Domain\AgentProtocol\Models\AgentIdentity::factory()->create([
            'agent_id' => 'recipient_123',
        ]);

        // Create transaction in database
        AgentTransaction::factory()->create([
            'transaction_id' => $transactionId,
            'amount'         => 1000.00,
            'to_agent_id'    => 'recipient_123',
            'from_agent_id'  => 'sender_456',
        ]);

        // Calculate expected hash
        ksort($transactionData);
        $json = json_encode($transactionData);
        $expectedHash = hash('sha256', $json !== false ? $json : '');

        // Verify integrity
        $isValid = $this->service->verifyTransactionIntegrity(
            $transactionId,
            $transactionData,
            $expectedHash
        );

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_detects_tampered_transaction_data()
    {
        $transactionId = 'txn_' . uniqid();
        $originalData = [
            'amount'   => 1000.00,
            'currency' => 'USD',
        ];
        $tamperedData = [
            'amount'   => 2000.00, // Changed amount
            'currency' => 'USD',
        ];

        // Create transaction with original amount
        AgentTransaction::factory()->create([
            'transaction_id' => $transactionId,
            'amount'         => 1000.00,
        ]);

        // Calculate hash of original data
        ksort($originalData);
        $json = json_encode($originalData);
        $originalHash = hash('sha256', $json !== false ? $json : '');

        // Verify with tampered data
        $isValid = $this->service->verifyTransactionIntegrity(
            $transactionId,
            $tamperedData,
            $originalHash
        );

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_verifies_compliance_requirements()
    {
        $transactionId = 'txn_' . uniqid();
        $agentId = 'agent_' . uniqid();
        $transactionData = [
            'amount'   => 15000.00, // Above CTR threshold
            'currency' => 'USD',
        ];

        // Create KYC-verified agent with appropriate limits
        Agent::factory()->create([
            'agent_id'                 => $agentId,
            'kyc_verified'             => true,
            'kyc_status'               => 'verified',
            'kyc_verified_at'          => now(),
            'single_transaction_limit' => 20000,  // Higher than $15,000
            'daily_limit'              => 100000,
            'monthly_limit'            => 500000,
        ]);

        // Verify compliance
        $result = $this->service->verifyCompliance(
            $transactionId,
            $agentId,
            $transactionData
        );

        $this->assertTrue($result['is_compliant']);
        $this->assertTrue($result['checks']['kyc']['passed']);
        $this->assertTrue($result['checks']['limits']['passed']);
        $this->assertTrue($result['checks']['sanctions']['passed']);
        $this->assertTrue($result['checks']['reporting']['ctr_required']); // CTR required for >$10k
    }

    /** @test */
    public function it_performs_multi_factor_verification()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $factors = [
            'password'  => ['value' => 'hashed_password'],
            'totp'      => ['code' => '123456'],
            'biometric' => ['fingerprint' => 'fingerprint_hash'],
        ];

        // Verify multi-factor
        $result = $this->service->verifyMultiFactor(
            $agentId,
            $transactionId,
            $factors
        );

        $this->assertTrue($result['verified']);
        $this->assertGreaterThanOrEqual(2, $result['verified_count']);
        $this->assertEquals(2, $result['required_count']);
    }

    /** @test */
    public function it_calculates_risk_scores_accurately()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'   => 50000.00, // High amount
            'currency' => 'USD',
        ];

        // Create agent with low reputation
        Agent::factory()->create([
            'agent_id'         => $agentId,
            'status'           => 'active',
            'reputation_score' => 30,
        ]);

        // Generate signature
        $this->signatureService->generateAgentKeyPair($agentId);
        $signature = $this->signatureService->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData
        );

        // Verify with enhanced level (includes fraud checks)
        $result = $this->service->verifyTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            $signature,
            'enhanced'
        );

        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertGreaterThan(30, $result['risk_score']);
        $this->assertContains($result['risk_level'], ['medium', 'high']);
    }

    /** @test */
    public function it_handles_maximum_security_level_verification()
    {
        $agentId = 'agent_' . uniqid();
        $transactionId = 'txn_' . uniqid();
        $transactionData = [
            'amount'   => 100000.00, // Very high amount
            'currency' => 'USD',
        ];

        // Setup fully compliant agent
        Agent::factory()->create([
            'agent_id'                 => $agentId,
            'status'                   => 'active',
            'kyc_verified'             => true,
            'single_transaction_limit' => 200000,
            'daily_limit'              => 500000,
        ]);

        // Generate keys and sign
        $this->signatureService->generateAgentKeyPair($agentId);
        $signature = $this->signatureService->signAgentTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            ['security_level' => 'maximum']
        );

        // Add encryption
        $encryptedData = $this->encryptionService->encryptData(
            $transactionData,
            "txn_key_{$transactionId}"
        );
        $signature['encrypted'] = true;
        $signature['cipher'] = $encryptedData['cipher'];

        // Add multi-factor auth
        $signature['auth_factors'] = [
            'password' => ['value' => 'password_hash'],
            'totp'     => ['code' => '654321'],
        ];

        // Verify with maximum level
        $result = $this->service->verifyTransaction(
            $transactionId,
            $agentId,
            $transactionData,
            $signature,
            'maximum'
        );

        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('encryption', $result['checks']);
        $this->assertArrayHasKey('multi_factor', $result['checks']);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('risk_level', $result);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
