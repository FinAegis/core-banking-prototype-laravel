<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Contracts\RiskScoringInterface;
use App\Domain\AgentProtocol\Contracts\TransactionVerifierInterface;
use App\Domain\AgentProtocol\Contracts\WalletOperationInterface;
use App\Domain\AgentProtocol\Services\AgentAuthenticationService;
use App\Domain\AgentProtocol\Services\AgentDiscoveryService;
use App\Domain\AgentProtocol\Services\AgentKycIntegrationService;
use App\Domain\AgentProtocol\Services\AgentNotificationService;
use App\Domain\AgentProtocol\Services\AgentPaymentIntegrationService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use App\Domain\AgentProtocol\Services\AgentWebhookService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use App\Domain\AgentProtocol\Services\DiscoveryService;
use App\Domain\AgentProtocol\Services\EncryptionService;
use App\Domain\AgentProtocol\Services\EscrowService;
use App\Domain\AgentProtocol\Services\FraudDetectionService;
use App\Domain\AgentProtocol\Services\JsonLDService;
use App\Domain\AgentProtocol\Services\RegulatoryReportingService;
use App\Domain\AgentProtocol\Services\ReputationService;
use App\Domain\AgentProtocol\Services\SignatureService;
use App\Domain\AgentProtocol\Services\TransactionVerificationService;
use Tests\TestCase;

/**
 * Integration tests for AgentProtocolServiceProvider.
 *
 * Verifies that all Agent Protocol services are correctly registered
 * and wired with their dependencies through the service container.
 */
class AgentProtocolServiceProviderTest extends TestCase
{
    // ==========================================
    // Contract Binding Tests
    // ==========================================

    /** @test */
    public function it_binds_wallet_operation_interface_to_agent_wallet_service(): void
    {
        $service = app(WalletOperationInterface::class);

        $this->assertInstanceOf(AgentWalletService::class, $service);
    }

    /** @test */
    public function it_binds_risk_scoring_interface_to_fraud_detection_service(): void
    {
        $service = app(RiskScoringInterface::class);

        $this->assertInstanceOf(FraudDetectionService::class, $service);
    }

    /** @test */
    public function it_binds_transaction_verifier_interface_to_verification_service(): void
    {
        $service = app(TransactionVerifierInterface::class);

        $this->assertInstanceOf(TransactionVerificationService::class, $service);
    }

    // ==========================================
    // Core Services Registration Tests
    // ==========================================

    /** @test */
    public function it_registers_did_service_as_singleton(): void
    {
        $service1 = app(DIDService::class);
        $service2 = app(DIDService::class);

        $this->assertInstanceOf(DIDService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_registers_discovery_service_with_did_dependency(): void
    {
        $service = app(DiscoveryService::class);

        $this->assertInstanceOf(DiscoveryService::class, $service);
    }

    /** @test */
    public function it_registers_agent_discovery_service_with_registry_dependency(): void
    {
        $service = app(AgentDiscoveryService::class);

        $this->assertInstanceOf(AgentDiscoveryService::class, $service);
    }

    /** @test */
    public function it_registers_agent_registry_service_as_singleton(): void
    {
        $service1 = app(AgentRegistryService::class);
        $service2 = app(AgentRegistryService::class);

        $this->assertInstanceOf(AgentRegistryService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_registers_json_ld_service(): void
    {
        $service = app(JsonLDService::class);

        $this->assertInstanceOf(JsonLDService::class, $service);
    }

    /** @test */
    public function it_registers_agent_wallet_service_as_singleton(): void
    {
        $service1 = app(AgentWalletService::class);
        $service2 = app(AgentWalletService::class);

        $this->assertInstanceOf(AgentWalletService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_registers_escrow_service_with_dependencies(): void
    {
        $service = app(EscrowService::class);

        $this->assertInstanceOf(EscrowService::class, $service);
    }

    /** @test */
    public function it_registers_reputation_service(): void
    {
        $service = app(ReputationService::class);

        $this->assertInstanceOf(ReputationService::class, $service);
    }

    /** @test */
    public function it_registers_notification_service(): void
    {
        $service = app(AgentNotificationService::class);

        $this->assertInstanceOf(AgentNotificationService::class, $service);
    }

    /** @test */
    public function it_registers_webhook_service(): void
    {
        $service = app(AgentWebhookService::class);

        $this->assertInstanceOf(AgentWebhookService::class, $service);
    }

    /** @test */
    public function it_registers_regulatory_reporting_service(): void
    {
        $service = app(RegulatoryReportingService::class);

        $this->assertInstanceOf(RegulatoryReportingService::class, $service);
    }

    // ==========================================
    // Security Services Registration Tests
    // ==========================================

    /** @test */
    public function it_registers_encryption_service_as_singleton(): void
    {
        $service1 = app(EncryptionService::class);
        $service2 = app(EncryptionService::class);

        $this->assertInstanceOf(EncryptionService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_registers_signature_service_as_singleton(): void
    {
        $service1 = app(SignatureService::class);
        $service2 = app(SignatureService::class);

        $this->assertInstanceOf(SignatureService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_registers_digital_signature_service_with_dependencies(): void
    {
        $service = app(DigitalSignatureService::class);

        $this->assertInstanceOf(DigitalSignatureService::class, $service);
    }

    /** @test */
    public function it_registers_fraud_detection_service(): void
    {
        $service = app(FraudDetectionService::class);

        $this->assertInstanceOf(FraudDetectionService::class, $service);
    }

    /** @test */
    public function it_registers_transaction_verification_service_with_all_dependencies(): void
    {
        $service = app(TransactionVerificationService::class);

        $this->assertInstanceOf(TransactionVerificationService::class, $service);
    }

    /** @test */
    public function it_registers_agent_authentication_service_with_did_dependency(): void
    {
        $service = app(AgentAuthenticationService::class);

        $this->assertInstanceOf(AgentAuthenticationService::class, $service);
    }

    // ==========================================
    // Integration Services Registration Tests
    // ==========================================

    /** @test */
    public function it_registers_agent_payment_integration_service(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->assertInstanceOf(AgentPaymentIntegrationService::class, $service);
    }

    /** @test */
    public function it_registers_agent_kyc_integration_service(): void
    {
        $service = app(AgentKycIntegrationService::class);

        $this->assertInstanceOf(AgentKycIntegrationService::class, $service);
    }

    // ==========================================
    // Service Functionality Tests
    // ==========================================

    /** @test */
    public function did_service_can_generate_valid_did(): void
    {
        $service = app(DIDService::class);

        $did = $service->generateDID('agent');

        $this->assertStringStartsWith('did:', $did);
    }

    /** @test */
    public function encryption_service_can_encrypt_and_decrypt(): void
    {
        $service = app(EncryptionService::class);
        $originalData = ['message' => 'test data', 'type' => 'test'];
        $keyId = 'test-key-' . time();

        $encrypted = $service->encryptData($originalData, $keyId, 'AES-256-GCM');

        // Verify encrypted result structure
        $this->assertArrayHasKey('encrypted_data', $encrypted);
        $this->assertArrayHasKey('cipher', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);

        $decrypted = $service->decryptData(
            $encrypted['encrypted_data'],
            $keyId,
            $encrypted['cipher'],
            ['iv' => $encrypted['iv'], 'tag' => $encrypted['tag']]
        );

        $this->assertEquals($originalData, $decrypted);
    }

    /** @test */
    public function signature_service_can_generate_key_pair(): void
    {
        $service = app(SignatureService::class);

        $keyPair = $service->generateKeyPair('RSA');

        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertNotEmpty($keyPair['public_key']);
        $this->assertNotEmpty($keyPair['private_key']);
    }

    /** @test */
    public function fraud_detection_service_can_analyze_transaction_risk(): void
    {
        $service = app(FraudDetectionService::class);

        $risk = $service->analyzeTransaction(
            transactionId: 'test-txn-123',
            agentId: 'agent-001',
            amount: 100.00,
            metadata: [
                'currency' => 'USD',
                'type'     => 'transfer',
            ]
        );

        $this->assertArrayHasKey('risk_score', $risk);
        $this->assertArrayHasKey('decision', $risk);
    }

    /** @test */
    public function reputation_service_can_get_agent_reputation(): void
    {
        $service = app(ReputationService::class);

        $reputation = $service->getAgentReputation('test-agent-id');

        // Returns ReputationScore value object
        $this->assertInstanceOf(\App\Domain\AgentProtocol\DataObjects\ReputationScore::class, $reputation);
        $this->assertGreaterThanOrEqual(0, $reputation->score);
        $this->assertLessThanOrEqual(100, $reputation->score);
        $this->assertContains($reputation->trustLevel, ['untrusted', 'low', 'neutral', 'high', 'trusted']);
    }

    /** @test */
    public function json_ld_service_can_serialize_data(): void
    {
        $service = app(JsonLDService::class);

        // serialize() takes array $data and optional array $context, returns JSON string
        $jsonString = $service->serialize([
            '@type' => 'Agent',
            'name'  => 'Test Agent',
        ]);

        $this->assertIsString($jsonString);
        $result = json_decode($jsonString, true);
        $this->assertArrayHasKey('@context', $result);
        $this->assertArrayHasKey('@type', $result);
    }

    // ==========================================
    // Service Dependency Chain Tests
    // ==========================================

    /** @test */
    public function transaction_verifier_chain_is_properly_wired(): void
    {
        // Get the verifier through the interface
        $verifier = app(TransactionVerifierInterface::class);

        // Verify it can perform its primary function
        // Interface: verify(string $transactionId, array $transactionData): array
        $result = $verifier->verify('test-txn-123', [
            'sender_id'   => 'agent-sender-001',
            'receiver_id' => 'agent-receiver-002',
            'amount'      => 100.00,
            'currency'    => 'USD',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('verified', $result);
        $this->assertArrayHasKey('verification_level', $result);
        $this->assertArrayHasKey('risk_score', $result);
    }

    /** @test */
    public function wallet_operations_chain_is_properly_wired(): void
    {
        $walletOps = app(WalletOperationInterface::class);

        // Verify the interface is bound to the correct implementation
        $this->assertInstanceOf(AgentWalletService::class, $walletOps);
    }

    /** @test */
    public function risk_scoring_chain_is_properly_wired(): void
    {
        $riskScoring = app(RiskScoringInterface::class);

        // Call the interface method
        $score = $riskScoring->calculateRisk('test-agent-id', 50.00, [
            'currency' => 'USD',
            'type'     => 'payment',
        ]);

        $this->assertIsArray($score);
        $this->assertArrayHasKey('score', $score);
        $this->assertArrayHasKey('level', $score);
    }
}
