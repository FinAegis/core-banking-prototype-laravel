<?php

declare(strict_types=1);

namespace App\Providers;

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
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\ComplianceAlertService;
use App\Domain\Compliance\Services\ComplianceService;
use App\Domain\Compliance\Services\KycService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Agent Protocol domain.
 *
 * Registers all services and contract bindings required for the Agent Protocol (AP2 & A2A)
 * implementation, enabling AI agent commerce and autonomous financial transactions.
 *
 * @see https://github.com/google-agentic-commerce/AP2/blob/main/docs/specification.md
 */
class AgentProtocolServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerContractBindings();
        $this->registerCoreServices();
        $this->registerSecurityServices();
        $this->registerIntegrationServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register event listeners for agent protocol events if needed
        // This is handled by Laravel's auto-discovery for event listeners
    }

    /**
     * Register contract-to-implementation bindings.
     */
    private function registerContractBindings(): void
    {
        // Wallet operations contract
        $this->app->bind(WalletOperationInterface::class, AgentWalletService::class);

        // Risk scoring contract
        $this->app->bind(RiskScoringInterface::class, FraudDetectionService::class);

        // Transaction verification contract
        $this->app->bind(TransactionVerifierInterface::class, function ($app) {
            return new TransactionVerificationService(
                $app->make(DigitalSignatureService::class),
                $app->make(EncryptionService::class),
                $app->make(FraudDetectionService::class)
            );
        });
    }

    /**
     * Register core Agent Protocol services.
     */
    private function registerCoreServices(): void
    {
        // DID Service - Decentralized Identifier management
        $this->app->singleton(DIDService::class, function ($app) {
            return new DIDService();
        });

        // Discovery Service - AP2 .well-known endpoint support
        $this->app->singleton(DiscoveryService::class, function ($app) {
            return new DiscoveryService(
                $app->make(DIDService::class)
            );
        });

        // Agent Discovery Service - Agent lookup and capability matching
        $this->app->singleton(AgentDiscoveryService::class, function ($app) {
            return new AgentDiscoveryService(
                $app->make(AgentRegistryService::class)
            );
        });

        // Agent Registry Service - Agent registration and management
        $this->app->singleton(AgentRegistryService::class, function ($app) {
            return new AgentRegistryService();
        });

        // JSON-LD Service - Semantic data support
        $this->app->singleton(JsonLDService::class, function ($app) {
            return new JsonLDService();
        });

        // Agent Wallet Service - Wallet operations
        $this->app->singleton(AgentWalletService::class, function ($app) {
            return new AgentWalletService();
        });

        // Escrow Service - Escrow transaction management
        $this->app->singleton(EscrowService::class, function ($app) {
            $complianceService = null;
            if ($app->bound(ComplianceService::class)) {
                $complianceService = $app->make(ComplianceService::class);
            }

            return new EscrowService(
                $app->make(AgentWalletService::class),
                $complianceService ?? new ComplianceService()
            );
        });

        // Reputation Service - Agent reputation scoring
        $this->app->singleton(ReputationService::class, function ($app) {
            return new ReputationService();
        });

        // Notification Service - Agent notifications
        $this->app->singleton(AgentNotificationService::class, function ($app) {
            return new AgentNotificationService();
        });

        // Webhook Service - Webhook delivery
        $this->app->singleton(AgentWebhookService::class, function ($app) {
            return new AgentWebhookService();
        });

        // Regulatory Reporting Service - CTR/SAR generation
        $this->app->singleton(RegulatoryReportingService::class, function ($app) {
            $complianceAlertService = null;
            if ($app->bound(ComplianceAlertService::class)) {
                $complianceAlertService = $app->make(ComplianceAlertService::class);
            }

            return new RegulatoryReportingService(
                $complianceAlertService ?? new ComplianceAlertService()
            );
        });
    }

    /**
     * Register security-related services.
     */
    private function registerSecurityServices(): void
    {
        // Encryption Service - AES-256-GCM encryption
        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService();
        });

        // Signature Service - Digital signature operations
        $this->app->singleton(SignatureService::class, function ($app) {
            return new SignatureService();
        });

        // Digital Signature Service - Agent transaction signing
        $this->app->singleton(DigitalSignatureService::class, function ($app) {
            return new DigitalSignatureService(
                $app->make(SignatureService::class),
                $app->make(EncryptionService::class)
            );
        });

        // Fraud Detection Service - ML-based fraud analysis
        $this->app->singleton(FraudDetectionService::class, function ($app) {
            return new FraudDetectionService();
        });

        // Transaction Verification Service - Comprehensive verification
        $this->app->singleton(TransactionVerificationService::class, function ($app) {
            return new TransactionVerificationService(
                $app->make(DigitalSignatureService::class),
                $app->make(EncryptionService::class),
                $app->make(FraudDetectionService::class)
            );
        });

        // Agent Authentication Service - DID and API key auth
        $this->app->singleton(AgentAuthenticationService::class, function ($app) {
            return new AgentAuthenticationService(
                $app->make(DIDService::class)
            );
        });
    }

    /**
     * Register integration services that connect to other domains.
     */
    private function registerIntegrationServices(): void
    {
        // Agent Payment Integration Service - Connects to main Payment domain
        $this->app->singleton(AgentPaymentIntegrationService::class, function ($app) {
            // ExchangeRateService may not be available in all environments
            $exchangeRateService = null;
            if ($app->bound(ExchangeRateService::class)) {
                $exchangeRateService = $app->make(ExchangeRateService::class);
            }

            return new AgentPaymentIntegrationService($exchangeRateService);
        });

        // Agent KYC Integration Service - Connects to Compliance domain
        $this->app->singleton(AgentKycIntegrationService::class, function ($app) {
            $kycService = null;
            $amlService = null;

            // KycService may not be available in all environments
            if ($app->bound(KycService::class)) {
                $kycService = $app->make(KycService::class);
            }

            // AmlScreeningService may not be available in all environments
            if ($app->bound(AmlScreeningService::class)) {
                $amlService = $app->make(AmlScreeningService::class);
            }

            return new AgentKycIntegrationService($kycService, $amlService);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            // Contracts
            WalletOperationInterface::class,
            RiskScoringInterface::class,
            TransactionVerifierInterface::class,

            // Core Services
            DIDService::class,
            DiscoveryService::class,
            AgentDiscoveryService::class,
            AgentRegistryService::class,
            JsonLDService::class,
            AgentWalletService::class,
            EscrowService::class,
            ReputationService::class,
            AgentNotificationService::class,
            AgentWebhookService::class,
            RegulatoryReportingService::class,

            // Security Services
            EncryptionService::class,
            SignatureService::class,
            DigitalSignatureService::class,
            FraudDetectionService::class,
            TransactionVerificationService::class,
            AgentAuthenticationService::class,

            // Integration Services
            AgentPaymentIntegrationService::class,
            AgentKycIntegrationService::class,
        ];
    }
}
