<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\EscrowRequest;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\AgentPaymentIntegrationService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Domain\AgentProtocol\Services\EscrowService;
use App\Domain\AgentProtocol\Services\ReputationService;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\Services\VisaCliPaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bridge Service connecting AI Domain with Agent Protocol for payments and reputation.
 *
 * This service enables AI agents to:
 * - Have DID identities within the Agent Protocol ecosystem
 * - Make and receive payments between AI agents
 * - Build and track reputation across the platform
 * - Use escrow for secure inter-agent transactions
 * - Coordinate multi-agent financial operations
 */
class AIAgentProtocolBridgeService
{
    /**
     * Cache prefix for AI agent DID mappings.
     */
    private const CACHE_PREFIX = 'ai_agent_protocol_bridge:';

    /**
     * Default capabilities for AI agents in the protocol.
     *
     * @var array<string>
     */
    private const DEFAULT_AI_AGENT_CAPABILITIES = [
        'ai_conversation',
        'multi_agent_coordination',
        'automated_payments',
        'escrow_transactions',
    ];

    /**
     * AI Agent type identifier.
     */
    private const AGENT_TYPE_AI = 'ai_agent';

    public function __construct(
        private readonly AgentRegistryService $agentRegistry,
        private readonly DIDService $didService,
        private readonly AgentPaymentIntegrationService $paymentIntegration,
        private readonly ReputationService $reputationService,
        /** @phpstan-ignore-next-line property.onlyWritten - Reserved for future escrow integration */
        private readonly EscrowService $escrowService,
        private readonly ?VisaCliPaymentService $visaCliPaymentService = null,
    ) {
    }

    /**
     * Register an AI agent with the Agent Protocol ecosystem.
     *
     * Creates a DID identity for the AI agent and registers it in the
     * Agent Protocol registry, enabling payments and reputation tracking.
     *
     * @param  string  $aiAgentName     The name/identifier of the AI agent in the AI domain
     * @param  array   $capabilities    Additional capabilities beyond defaults
     * @param  array   $metadata        Additional metadata for the agent
     * @return array{did: string, agent_id: int, wallet_address: string|null}
     */
    public function registerAIAgent(
        string $aiAgentName,
        array $capabilities = [],
        array $metadata = []
    ): array {
        // Check if already registered
        $existingDid = $this->getAIAgentDid($aiAgentName);
        if ($existingDid !== null) {
            $agentData = $this->agentRegistry->getAgentByDID($existingDid);

            return [
                'did'            => $existingDid,
                'agent_id'       => $agentData['agent_id'] ?? 0,
                'wallet_address' => $agentData['wallet_address'] ?? null,
            ];
        }

        // Generate DID for AI agent
        $did = $this->generateAIAgentDid($aiAgentName);

        // Merge capabilities
        $allCapabilities = array_unique(array_merge(
            self::DEFAULT_AI_AGENT_CAPABILITIES,
            $capabilities
        ));

        // Generate agent ID
        $agentId = 'ai-' . Str::uuid()->toString();

        // Register with Agent Protocol
        $agent = $this->agentRegistry->registerAgent([
            'agentId'      => $agentId,
            'did'          => $did,
            'name'         => $aiAgentName,
            'type'         => self::AGENT_TYPE_AI,
            'capabilities' => $allCapabilities,
            'metadata'     => array_merge($metadata, [
                'ai_domain_source' => true,
                'registered_at'    => now()->toIso8601String(),
            ]),
        ]);

        // Initialize reputation
        $this->reputationService->initializeAgentReputation($did);

        // Cache the mapping
        $this->cacheAIAgentDid($aiAgentName, $did);

        Log::info('AI Agent registered with Agent Protocol', [
            'ai_agent_name' => $aiAgentName,
            'did'           => $did,
            'agent_id'      => $agent->id,
        ]);

        return [
            'did'            => $did,
            'agent_id'       => $agent->id,
            'wallet_address' => $agent->wallet_address,
        ];
    }

    /**
     * Initiate a payment between two AI agents.
     *
     * @param  string  $fromAIAgent   Source AI agent name
     * @param  string  $toAIAgent     Destination AI agent name
     * @param  float   $amount        Payment amount
     * @param  string  $currency      Currency code
     * @param  string  $purpose       Payment purpose description
     * @param  array<string, mixed>  $metadata      Additional payment metadata
     * @return array{transaction_id: string, status: string, fees: float, from_did: string, to_did: string}
     */
    public function initiateAIAgentPayment(
        string $fromAIAgent,
        string $toAIAgent,
        float $amount,
        string $currency = 'USD',
        string $purpose = 'ai_agent_transaction',
        array $metadata = []
    ): array {
        // Get DIDs for both agents
        $fromDid = $this->getOrRegisterAIAgentDid($fromAIAgent);
        $toDid = $this->getOrRegisterAIAgentDid($toAIAgent);

        // Create payment request
        $paymentRequest = new AgentPaymentRequest(
            fromAgentDid: $fromDid,
            toAgentDid: $toDid,
            amount: $amount,
            currency: $currency,
            purpose: $purpose,
            metadata: array_merge($metadata, [
                'ai_to_ai_transfer'    => true,
                'source_ai_agent'      => $fromAIAgent,
                'destination_ai_agent' => $toAIAgent,
            ])
        );

        // Calculate fee from config
        $fee = $this->calculateTransactionFee($amount);

        // Update reputation for initiator
        $this->reputationService->updateReputationFromTransaction(
            agentId: $fromDid,
            transactionId: $paymentRequest->transactionId ?? Str::uuid()->toString(),
            outcome: 'completed',
            transactionValue: $amount
        );

        Log::info('AI Agent payment initiated', [
            'from'           => $fromAIAgent,
            'to'             => $toAIAgent,
            'amount'         => $amount,
            'currency'       => $currency,
            'transaction_id' => $paymentRequest->transactionId,
        ]);

        return [
            'transaction_id' => $paymentRequest->transactionId ?? Str::uuid()->toString(),
            'status'         => 'initiated',
            'fees'           => $fee,
            'from_did'       => $fromDid,
            'to_did'         => $toDid,
        ];
    }

    /**
     * Initiate a Visa CLI payment from an AI agent.
     *
     * @param  string  $aiAgentName  Source AI agent name
     * @param  string  $url          Payment target URL
     * @param  int     $amountCents  Amount in USD cents
     * @param  string  $purpose      Payment purpose
     * @param  array<string, mixed>  $metadata Additional metadata
     * @return array{transaction_id: string, status: string, amount_cents: int, payment_reference: string|null, from_did: string}
     */
    public function initiateVisaCliPayment(
        string $aiAgentName,
        string $url,
        int $amountCents,
        string $purpose = 'ai_agent_visa_payment',
        array $metadata = []
    ): array {
        if ($this->visaCliPaymentService === null) {
            return [
                'transaction_id'    => '',
                'status'            => 'unavailable',
                'amount_cents'      => $amountCents,
                'payment_reference' => null,
                'from_did'          => '',
            ];
        }

        $fromDid = $this->getOrRegisterAIAgentDid($aiAgentName);

        $request = new VisaCliPaymentRequest(
            agentId: $fromDid,
            url: $url,
            amountCents: $amountCents,
            purpose: $purpose,
            metadata: array_merge($metadata, [
                'ai_agent_name' => $aiAgentName,
                'source'        => 'agent_protocol_bridge',
            ]),
        );

        $result = $this->visaCliPaymentService->executePayment($request);

        Log::info('AI Agent Visa CLI payment initiated', [
            'agent'     => $aiAgentName,
            'url'       => $url,
            'amount'    => $amountCents,
            'reference' => $result->paymentReference,
        ]);

        return [
            'transaction_id'    => $request->requestId,
            'status'            => $result->status->value,
            'amount_cents'      => $result->amountCents,
            'payment_reference' => $result->paymentReference,
            'from_did'          => $fromDid,
        ];
    }

    /**
     * Create an escrow for a multi-agent transaction.
     *
     * Useful when AI agents need to coordinate payments with conditions.
     *
     * @param  string        $buyerAIAgent      AI agent funding the escrow
     * @param  string        $sellerAIAgent     AI agent receiving funds
     * @param  float         $amount            Escrow amount
     * @param  array<string> $releaseConditions Conditions that must be met
     * @param  int           $timeoutSeconds    Escrow timeout
     * @return array{escrow_id: string, buyer_did: string, seller_did: string, amount: float, conditions: array<string, bool>, timeout_at: string}
     */
    public function createAIAgentEscrow(
        string $buyerAIAgent,
        string $sellerAIAgent,
        float $amount,
        array $releaseConditions = [],
        int $timeoutSeconds = 86400
    ): array {
        $buyerDid = $this->getOrRegisterAIAgentDid($buyerAIAgent);
        $sellerDid = $this->getOrRegisterAIAgentDid($sellerAIAgent);

        // Prepare conditions array
        $conditions = [];
        foreach ($releaseConditions as $condition) {
            $conditions[$condition] = false;
        }

        $escrowRequest = new EscrowRequest(
            buyerDid: $buyerDid,
            sellerDid: $sellerDid,
            amount: $amount,
            currency: 'USD',
            conditions: $conditions,
            releaseConditions: $releaseConditions,
            timeoutSeconds: $timeoutSeconds,
            metadata: [
                'ai_agent_escrow' => true,
                'buyer_ai_agent'  => $buyerAIAgent,
                'seller_ai_agent' => $sellerAIAgent,
            ]
        );

        Log::info('AI Agent escrow created', [
            'buyer'     => $buyerAIAgent,
            'seller'    => $sellerAIAgent,
            'amount'    => $amount,
            'escrow_id' => $escrowRequest->escrowId,
        ]);

        return [
            'escrow_id'  => $escrowRequest->escrowId,
            'buyer_did'  => $buyerDid,
            'seller_did' => $sellerDid,
            'amount'     => $amount,
            'conditions' => $conditions,
            'timeout_at' => $escrowRequest->getTimeoutAt()->toIso8601String(),
        ];
    }

    /**
     * Get reputation score for an AI agent.
     *
     * @param  string  $aiAgentName  AI agent name
     * @return array{score: float, level: string, transaction_count: int, trust_level: string}
     */
    public function getAIAgentReputation(string $aiAgentName): array
    {
        $did = $this->getAIAgentDid($aiAgentName);

        if ($did === null) {
            return [
                'score'             => 0.0,
                'level'             => 'unregistered',
                'transaction_count' => 0,
                'trust_level'       => 'untrusted',
            ];
        }

        $reputation = $this->reputationService->getAgentReputation($did);

        return [
            'score'             => $reputation->score,
            'level'             => $this->determineReputationLevel($reputation->score),
            'transaction_count' => $reputation->totalTransactions,
            'trust_level'       => $reputation->trustLevel,
        ];
    }

    /**
     * Check if an AI agent meets reputation threshold for an operation.
     *
     * @param  string  $aiAgentName  AI agent name
     * @param  string  $threshold    Threshold level ('basic', 'standard', 'premium', 'trusted')
     * @return bool
     */
    public function meetsReputationThreshold(string $aiAgentName, string $threshold): bool
    {
        $did = $this->getAIAgentDid($aiAgentName);

        if ($did === null) {
            return false;
        }

        return $this->reputationService->meetsThreshold($did, $threshold);
    }

    /**
     * Get payment capabilities for AI agents.
     *
     * @return array{supported_currencies: array<string>, default_currency: string, fee_structure: array<string, float>, limits: array<string, float>, escrow_enabled: bool}
     */
    public function getPaymentCapabilities(): array
    {
        $defaultCurrency = $this->paymentIntegration->getDefaultCurrency();

        return [
            'supported_currencies' => config('agent_protocol.supported_currencies', ['USD', 'EUR', 'GBP', 'USDC', 'USDT']),
            'default_currency'     => $defaultCurrency,
            'fee_structure'        => [
                'standard_rate'       => config('agent_protocol.fees.standard_rate', 0.025),
                'minimum_fee'         => config('agent_protocol.fees.minimum_fee', 0.50),
                'maximum_fee'         => config('agent_protocol.fees.maximum_fee', 100.00),
                'exemption_threshold' => config('agent_protocol.fees.exemption_threshold', 1.00),
            ],
            'limits' => [
                'single_transaction' => config('agent_protocol.limits.single_transaction', 10000.00),
                'daily'              => config('agent_protocol.limits.daily', 50000.00),
            ],
            'escrow_enabled' => true,
        ];
    }

    /**
     * Discover AI agents with specific capabilities.
     *
     * @param  array<string>  $requiredCapabilities  Capabilities to search for
     * @return Collection<int, array>
     */
    public function discoverAIAgents(array $requiredCapabilities = []): Collection
    {
        $agents = $this->agentRegistry->searchByCapability('ai_conversation');

        if (empty($requiredCapabilities)) {
            return $agents;
        }

        return $agents->filter(function ($agent) use ($requiredCapabilities) {
            $agentCapabilities = is_array($agent['capabilities'] ?? null)
                ? $agent['capabilities']
                : [];

            foreach ($requiredCapabilities as $required) {
                if (! in_array($required, $agentCapabilities, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Calculate trust level between two AI agents.
     *
     * @param  string  $aiAgent1  First AI agent name
     * @param  string  $aiAgent2  Second AI agent name
     * @return array{trust_score: float, transaction_history: int, recommendation: string}
     */
    public function calculateTrustBetweenAIAgents(string $aiAgent1, string $aiAgent2): array
    {
        $did1 = $this->getAIAgentDid($aiAgent1);
        $did2 = $this->getAIAgentDid($aiAgent2);

        if ($did1 === null || $did2 === null) {
            return [
                'trust_score'         => 0.0,
                'transaction_history' => 0,
                'recommendation'      => 'one_or_both_agents_not_registered',
            ];
        }

        // calculateTrustRelationship returns a float (0-100 scale)
        $trustScore = $this->reputationService->calculateTrustRelationship($did1, $did2);

        // Normalize to 0-1 scale for recommendation
        $normalizedScore = $trustScore / 100.0;

        $recommendation = match (true) {
            $normalizedScore >= 0.8 => 'highly_trusted',
            $normalizedScore >= 0.6 => 'trusted',
            $normalizedScore >= 0.4 => 'moderate_trust',
            $normalizedScore >= 0.2 => 'low_trust',
            default                 => 'untrusted_use_escrow',
        };

        return [
            'trust_score'         => $normalizedScore,
            'transaction_history' => 0, // Would need separate method to get this
            'recommendation'      => $recommendation,
        ];
    }

    /**
     * Get bridged AI agents (those registered with Agent Protocol).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getBridgedAIAgents(): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return Agent::where('type', self::AGENT_TYPE_AI)
            ->where('status', 'active')
            ->get()
            ->map(fn (Agent $agent) => [
                'did'          => $agent->did,
                'name'         => $agent->name,
                'capabilities' => $agent->capabilities ?? [],
                'reputation'   => $this->reputationService->getAgentReputation($agent->did),
                'created_at'   => $agent->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Deactivate an AI agent in the protocol.
     *
     * @param  string  $aiAgentName  AI agent name
     * @return bool
     */
    public function deactivateAIAgent(string $aiAgentName): bool
    {
        $did = $this->getAIAgentDid($aiAgentName);

        if ($did === null) {
            return false;
        }

        $this->agentRegistry->updateAgentStatus($did, 'inactive');
        $this->clearCachedDid($aiAgentName);

        Log::info('AI Agent deactivated in protocol', [
            'ai_agent_name' => $aiAgentName,
            'did'           => $did,
        ]);

        return true;
    }

    /**
     * Generate a DID for an AI agent.
     *
     * Uses the 'agent' method to generate DIDs in the format: did:agent:{identifier}
     */
    private function generateAIAgentDid(string $aiAgentName): string
    {
        // Use DIDService to generate a unique DID with 'agent' method
        return $this->didService->generateDID('agent');
    }

    /**
     * Get AI agent DID from cache or database.
     */
    private function getAIAgentDid(string $aiAgentName): ?string
    {
        $cacheKey = self::CACHE_PREFIX . Str::slug($aiAgentName);

        return Cache::get($cacheKey, function () use ($aiAgentName) {
            $agent = Agent::where('name', $aiAgentName)
                ->where('type', self::AGENT_TYPE_AI)
                ->first();

            return $agent?->did;
        });
    }

    /**
     * Get or register DID for an AI agent.
     */
    private function getOrRegisterAIAgentDid(string $aiAgentName): string
    {
        $did = $this->getAIAgentDid($aiAgentName);

        if ($did === null) {
            $result = $this->registerAIAgent($aiAgentName);
            $did = $result['did'];
        }

        return $did;
    }

    /**
     * Cache the AI agent DID mapping.
     */
    private function cacheAIAgentDid(string $aiAgentName, string $did): void
    {
        $cacheKey = self::CACHE_PREFIX . Str::slug($aiAgentName);
        Cache::put($cacheKey, $did, now()->addHours(24));
    }

    /**
     * Clear cached DID for an AI agent.
     */
    private function clearCachedDid(string $aiAgentName): void
    {
        $cacheKey = self::CACHE_PREFIX . Str::slug($aiAgentName);
        Cache::forget($cacheKey);
    }

    /**
     * Determine reputation level from score.
     */
    private function determineReputationLevel(float $score): string
    {
        return match (true) {
            $score >= 900 => 'elite',
            $score >= 800 => 'trusted',
            $score >= 600 => 'established',
            $score >= 400 => 'developing',
            $score >= 200 => 'new',
            default       => 'unranked',
        };
    }

    /**
     * Calculate transaction fee from config.
     */
    private function calculateTransactionFee(float $amount): float
    {
        $feeRate = config('agent_protocol.fees.standard_rate', 0.025);
        $minFee = config('agent_protocol.fees.minimum_fee', 0.50);
        $maxFee = config('agent_protocol.fees.maximum_fee', 100.00);
        $exemptionThreshold = config('agent_protocol.fees.exemption_threshold', 1.00);

        // Exempt small transactions
        if ($amount < $exemptionThreshold) {
            return 0.0;
        }

        $calculatedFee = round($amount * $feeRate, 2);

        return max($minFee, min($maxFee, $calculatedFee));
    }
}
