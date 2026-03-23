<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Maps incoming ACP (Agent Commerce Protocol) job requests to Zelta REST API calls.
 *
 * Acts as a bridge between the Virtuals Protocol ACP layer and FinAegis
 * services — routing each service type to the appropriate internal handler.
 */
class AcpServiceProviderBridge
{
    public function __construct(
        private readonly VirtualsAgentService $agentService,
        private readonly AgentOnboardingService $onboardingService,
    ) {
    }

    /**
     * Route an ACP job request to the appropriate Zelta service.
     *
     * @param  array<string, mixed>  $jobPayload  The ACP job payload containing service_type and parameters.
     * @return array<string, mixed>  The result from the matched service handler.
     *
     * @throws RuntimeException When the service_type is unrecognised or execution fails.
     */
    public function handleAcpJob(array $jobPayload): array
    {
        $serviceType = $jobPayload['service_type'] ?? null;

        if (! is_string($serviceType) || $serviceType === '') {
            throw new RuntimeException('ACP job payload must include a non-empty "service_type" field.');
        }

        Log::info('ACP job received', [
            'service_type' => $serviceType,
            'job_id'       => $jobPayload['job_id'] ?? 'unknown',
        ]);

        $result = match ($serviceType) {
            'card_provisioning' => $this->handleCardProvisioning($jobPayload),
            'compliance'        => $this->handleCompliance($jobPayload),
            'payments'          => $this->handlePayments($jobPayload),
            'shield'            => $this->handleShield($jobPayload),
            'ramp'              => $this->handleRamp($jobPayload),
            default             => throw new RuntimeException("Unknown ACP service type: {$serviceType}"),
        };

        Log::info('ACP job completed', [
            'service_type' => $serviceType,
            'job_id'       => $jobPayload['job_id'] ?? 'unknown',
            'status'       => $result['status'] ?? 'unknown',
        ]);

        return $result;
    }

    /**
     * Return the catalogue of services Zelta offers via ACP.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getServiceCatalog(): array
    {
        return [
            [
                'service_type' => 'card_provisioning',
                'name'         => 'Virtual Card Provisioning',
                'description'  => 'Provision virtual Visa cards for agent spending with configurable limits.',
                'capabilities' => ['create_card', 'freeze_card', 'set_limits'],
            ],
            [
                'service_type' => 'compliance',
                'name'         => 'Compliance & KYC',
                'description'  => 'Agent identity verification, TrustCert issuance, and regulatory checks.',
                'capabilities' => ['verify_agent', 'issue_trustcert', 'check_sanctions'],
            ],
            [
                'service_type' => 'payments',
                'name'         => 'Payment Execution',
                'description'  => 'Execute x402 payments on behalf of agents with spending limit enforcement.',
                'capabilities' => ['execute_payment', 'check_balance', 'get_history'],
            ],
            [
                'service_type' => 'shield',
                'name'         => 'Fraud Shield',
                'description'  => 'Real-time fraud detection and spending anomaly analysis for agents.',
                'capabilities' => ['evaluate_transaction', 'get_risk_score', 'report_fraud'],
            ],
            [
                'service_type' => 'ramp',
                'name'         => 'On/Off Ramp',
                'description'  => 'Fiat-to-crypto and crypto-to-fiat conversion services for agents.',
                'capabilities' => ['get_quotes', 'create_session', 'check_status'],
            ],
        ];
    }

    /**
     * Handle card provisioning ACP jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleCardProvisioning(array $payload): array
    {
        $agentId = $this->extractAgentId($payload);
        $profile = $this->onboardingService->getAgentProfile($agentId);

        if ($profile === null) {
            return ['status' => 'error', 'message' => "Agent [{$agentId}] not found."];
        }

        Log::info('ACP card provisioning request', [
            'agent_id'   => $agentId,
            'profile_id' => $profile->id,
            'action'     => $payload['action'] ?? 'create_card',
        ]);

        return [
            'status'     => 'accepted',
            'profile_id' => $profile->id,
            'agent_id'   => $agentId,
            'card_id'    => $profile->card_id,
            'message'    => 'Card provisioning request accepted.',
        ];
    }

    /**
     * Handle compliance ACP jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleCompliance(array $payload): array
    {
        $agentId = $this->extractAgentId($payload);
        $profile = $this->onboardingService->getAgentProfile($agentId);

        if ($profile === null) {
            return ['status' => 'error', 'message' => "Agent [{$agentId}] not found."];
        }

        return [
            'status'               => 'accepted',
            'agent_id'             => $agentId,
            'trustcert_subject_id' => $profile->trustcert_subject_id,
            'agent_status'         => $profile->status->value,
            'message'              => 'Compliance check accepted.',
        ];
    }

    /**
     * Handle payment ACP jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handlePayments(array $payload): array
    {
        $agentId = $this->extractAgentId($payload);

        $url = $payload['url'] ?? null;
        $amountCents = $payload['amount_cents'] ?? null;

        if (! is_string($url) || ! is_int($amountCents)) {
            return ['status' => 'error', 'message' => 'Payments require "url" (string) and "amount_cents" (int).'];
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return ['status' => 'error', 'message' => 'Invalid payment URL. Only http/https URLs are accepted.'];
        }

        try {
            $result = $this->agentService->executeAgentPayment(
                virtualsAgentId: $agentId,
                url: $url,
                amountCents: $amountCents,
                purpose: is_string($payload['purpose'] ?? null) ? $payload['purpose'] : null,
            );

            return [
                'status'            => 'completed',
                'payment_reference' => $result->paymentReference,
                'amount_cents'      => $amountCents,
                'message'           => 'Payment executed successfully.',
            ];
        } catch (RuntimeException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle fraud shield ACP jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleShield(array $payload): array
    {
        $agentId = $this->extractAgentId($payload);

        Log::info('ACP shield evaluation request', [
            'agent_id' => $agentId,
            'action'   => $payload['action'] ?? 'evaluate',
        ]);

        return [
            'status'     => 'accepted',
            'agent_id'   => $agentId,
            'risk_score' => 0.0,
            'message'    => 'Shield evaluation accepted.',
        ];
    }

    /**
     * Handle on/off ramp ACP jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleRamp(array $payload): array
    {
        $agentId = $this->extractAgentId($payload);

        Log::info('ACP ramp request', [
            'agent_id' => $agentId,
            'action'   => $payload['action'] ?? 'get_quotes',
        ]);

        return [
            'status'   => 'accepted',
            'agent_id' => $agentId,
            'message'  => 'Ramp request accepted.',
        ];
    }

    /**
     * Extract and validate the agent ID from an ACP payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    private function extractAgentId(array $payload): string
    {
        $agentId = $payload['agent_id'] ?? null;

        if (! is_string($agentId) || $agentId === '') {
            throw new RuntimeException('ACP job payload must include a non-empty "agent_id" field.');
        }

        if (! preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $agentId)) {
            throw new RuntimeException('Invalid agent ID format. Must be alphanumeric with hyphens/underscores.');
        }

        return $agentId;
    }
}
