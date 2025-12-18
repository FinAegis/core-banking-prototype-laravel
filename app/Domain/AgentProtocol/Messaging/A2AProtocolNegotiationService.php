<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use App\Domain\AgentProtocol\Enums\MessageType;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * A2A Protocol Negotiation Service.
 *
 * Handles protocol version negotiation between agents to ensure compatibility
 * before establishing communication channels.
 */
class A2AProtocolNegotiationService
{
    private const CACHE_PREFIX = 'a2a_protocol:';

    private const AGREEMENT_TTL = 86400; // 24 hours

    /**
     * Supported protocol versions in order of preference (newest first).
     *
     * @var array<string>
     */
    private const SUPPORTED_VERSIONS = ['1.1', '1.0'];

    /**
     * Protocol capabilities indexed by version.
     *
     * @var array<string, array<string, mixed>>
     */
    private const VERSION_CAPABILITIES = [
        '1.0' => [
            'messaging'   => true,
            'payments'    => true,
            'escrow'      => true,
            'encryption'  => ['aes-256-gcm'],
            'signatures'  => ['ed25519'],
            'compression' => false,
            'streaming'   => false,
        ],
        '1.1' => [
            'messaging'        => true,
            'payments'         => true,
            'escrow'           => true,
            'encryption'       => ['aes-256-gcm', 'chacha20-poly1305'],
            'signatures'       => ['ed25519', 'secp256k1'],
            'compression'      => true,
            'streaming'        => true,
            'batch_operations' => true,
        ],
    ];

    public function __construct(
        private readonly AgentMessageBusService $messageBus,
        private readonly AgentRegistryService $registryService,
        private readonly CacheRepository $cache
    ) {
    }

    /**
     * Initiate protocol negotiation with another agent.
     */
    public function initiateNegotiation(
        string $initiatorDid,
        string $targetDid,
        ?array $preferredCapabilities = null
    ): NegotiationResult {
        // Check for existing agreement
        $existingAgreement = $this->getExistingAgreement($initiatorDid, $targetDid);
        if ($existingAgreement !== null) {
            return NegotiationResult::fromExisting($existingAgreement);
        }

        // Build negotiation proposal
        $proposal = $this->buildProposal($initiatorDid, $preferredCapabilities);

        // Send negotiation request
        $envelope = A2AMessageEnvelope::create(
            senderDid: $initiatorDid,
            recipientDid: $targetDid,
            messageType: MessageType::PROTOCOL_NEGOTIATION,
            payload: [
                'proposal'          => $proposal,
                'supportedVersions' => self::SUPPORTED_VERSIONS,
                'initiatedAt'       => now()->toIso8601String(),
            ],
            priority: MessagePriority::HIGH
        );

        // Send and wait for response
        $response = $this->messageBus->sendAndWait($envelope, 30);

        if ($response === null) {
            return NegotiationResult::timeout($initiatorDid, $targetDid);
        }

        // Process response
        return $this->processNegotiationResponse($initiatorDid, $targetDid, $response);
    }

    /**
     * Handle incoming negotiation request.
     */
    public function handleNegotiationRequest(A2AMessageEnvelope $request): A2AMessageEnvelope
    {
        $proposal = $request->payload['proposal'] ?? [];
        $theirVersions = $request->payload['supportedVersions'] ?? ['1.0'];

        // Find compatible version
        $agreedVersion = $this->findCompatibleVersion($theirVersions);

        if ($agreedVersion === null) {
            return $request->createResponse(
                payload: [
                    'accepted'          => false,
                    'reason'            => 'No compatible protocol version found',
                    'supportedVersions' => self::SUPPORTED_VERSIONS,
                ],
                responseType: MessageType::PROTOCOL_AGREEMENT
            );
        }

        // Build agreement based on common capabilities
        $agreement = $this->buildAgreement(
            $request->senderDid,
            $request->recipientDid,
            $agreedVersion,
            $proposal
        );

        // Store agreement
        $this->storeAgreement($agreement);

        return $request->createResponse(
            payload: [
                'accepted'  => true,
                'agreement' => $agreement,
            ],
            responseType: MessageType::PROTOCOL_AGREEMENT
        );
    }

    /**
     * Get negotiated protocol for communication between two agents.
     */
    public function getAgreedProtocol(string $agent1Did, string $agent2Did): ?ProtocolAgreement
    {
        return $this->getExistingAgreement($agent1Did, $agent2Did);
    }

    /**
     * Check if two agents have a valid protocol agreement.
     */
    public function hasValidAgreement(string $agent1Did, string $agent2Did): bool
    {
        $agreement = $this->getExistingAgreement($agent1Did, $agent2Did);

        return $agreement !== null && ! $agreement->isExpired();
    }

    /**
     * Revoke protocol agreement between agents.
     */
    public function revokeAgreement(string $agent1Did, string $agent2Did): bool
    {
        $cacheKey = $this->getAgreementCacheKey($agent1Did, $agent2Did);
        $this->cache->forget($cacheKey);

        Log::info('Protocol agreement revoked', [
            'agent1' => $agent1Did,
            'agent2' => $agent2Did,
        ]);

        return true;
    }

    /**
     * Get supported protocol versions.
     *
     * @return array<string>
     */
    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    /**
     * Get capabilities for a specific protocol version.
     *
     * @return array<string, mixed>|null
     */
    public function getVersionCapabilities(string $version): ?array
    {
        return self::VERSION_CAPABILITIES[$version] ?? null;
    }

    /**
     * Refresh an existing agreement.
     */
    public function refreshAgreement(string $agent1Did, string $agent2Did): NegotiationResult
    {
        // Revoke existing agreement
        $this->revokeAgreement($agent1Did, $agent2Did);

        // Re-negotiate
        return $this->initiateNegotiation($agent1Did, $agent2Did);
    }

    private function buildProposal(string $agentDid, ?array $preferredCapabilities): array
    {
        $agent = $this->registryService->getAgent($agentDid);
        $agentCapabilities = $agent !== null ? $agent->capabilities : [];

        $proposal = [
            'agentDid'              => $agentDid,
            'preferredVersion'      => self::SUPPORTED_VERSIONS[0],
            'supportedVersions'     => self::SUPPORTED_VERSIONS,
            'capabilities'          => $agentCapabilities,
            'requestedCapabilities' => $preferredCapabilities ?? [
                'messaging',
                'payments',
                'escrow',
            ],
            'encryption' => [
                'preferred' => 'aes-256-gcm',
                'supported' => ['aes-256-gcm', 'chacha20-poly1305'],
            ],
            'signatures' => [
                'preferred' => 'ed25519',
                'supported' => ['ed25519', 'secp256k1'],
            ],
        ];

        return $proposal;
    }

    private function findCompatibleVersion(array $theirVersions): ?string
    {
        foreach (self::SUPPORTED_VERSIONS as $ourVersion) {
            if (in_array($ourVersion, $theirVersions, true)) {
                return $ourVersion;
            }
        }

        return null;
    }

    private function buildAgreement(
        string $initiatorDid,
        string $responderDid,
        string $version,
        array $proposal
    ): ProtocolAgreement {
        $versionCapabilities = self::VERSION_CAPABILITIES[$version] ?? [];

        // Determine common encryption method
        $proposedEncryption = $proposal['encryption']['supported'] ?? ['aes-256-gcm'];
        $supportedEncryption = $versionCapabilities['encryption'] ?? ['aes-256-gcm'];
        $agreedEncryption = array_values(array_intersect($proposedEncryption, $supportedEncryption))[0] ?? 'aes-256-gcm';

        // Determine common signature method
        $proposedSignatures = $proposal['signatures']['supported'] ?? ['ed25519'];
        $supportedSignatures = $versionCapabilities['signatures'] ?? ['ed25519'];
        $agreedSignature = array_values(array_intersect($proposedSignatures, $supportedSignatures))[0] ?? 'ed25519';

        return new ProtocolAgreement(
            agreementId: bin2hex(random_bytes(16)),
            version: $version,
            initiatorDid: $initiatorDid,
            responderDid: $responderDid,
            encryptionMethod: $agreedEncryption,
            signatureMethod: $agreedSignature,
            capabilities: array_keys(array_filter($versionCapabilities, fn ($v) => $v === true)),
            agreedAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+' . self::AGREEMENT_TTL . ' seconds')
        );
    }

    private function processNegotiationResponse(
        string $initiatorDid,
        string $targetDid,
        A2AMessageEnvelope $response
    ): NegotiationResult {
        $payload = $response->payload;

        if (! ($payload['accepted'] ?? false)) {
            return NegotiationResult::rejected(
                $initiatorDid,
                $targetDid,
                $payload['reason'] ?? 'Unknown reason'
            );
        }

        $agreementData = $payload['agreement'] ?? null;
        if ($agreementData === null) {
            return NegotiationResult::rejected(
                $initiatorDid,
                $targetDid,
                'No agreement in response'
            );
        }

        $agreement = ProtocolAgreement::fromArray($agreementData);

        // Store agreement on our side
        $this->storeAgreement($agreement);

        return NegotiationResult::success($agreement);
    }

    private function getExistingAgreement(string $agent1Did, string $agent2Did): ?ProtocolAgreement
    {
        $cacheKey = $this->getAgreementCacheKey($agent1Did, $agent2Did);
        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            return null;
        }

        $agreement = ProtocolAgreement::fromArray($data);

        // Check if expired
        if ($agreement->isExpired()) {
            $this->cache->forget($cacheKey);

            return null;
        }

        return $agreement;
    }

    private function storeAgreement(ProtocolAgreement $agreement): void
    {
        $cacheKey = $this->getAgreementCacheKey($agreement->initiatorDid, $agreement->responderDid);
        $this->cache->put($cacheKey, $agreement->toArray(), self::AGREEMENT_TTL);

        Log::info('Protocol agreement stored', [
            'agreementId' => $agreement->agreementId,
            'version'     => $agreement->version,
            'initiator'   => $agreement->initiatorDid,
            'responder'   => $agreement->responderDid,
        ]);
    }

    private function getAgreementCacheKey(string $agent1Did, string $agent2Did): string
    {
        // Use sorted DIDs to ensure same key regardless of order
        $dids = [$agent1Did, $agent2Did];
        sort($dids);

        return self::CACHE_PREFIX . 'agreement:' . md5(implode(':', $dids));
    }
}
