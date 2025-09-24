<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use Spatie\LaravelData\Data;

class KycVerificationRequest extends Data
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $agentDid,
        public readonly string $agentName,
        public readonly KycVerificationLevel $verificationLevel,
        public readonly array $documents,
        public readonly string $countryCode,
        public readonly bool $enableBiometric = false,
        public readonly ?string $businessName = null,
        public readonly array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            agentId: $data['agent_id'],
            agentDid: $data['agent_did'],
            agentName: $data['agent_name'],
            verificationLevel: KycVerificationLevel::from($data['verification_level'] ?? 'basic'),
            documents: $data['documents'] ?? [],
            countryCode: $data['country_code'] ?? 'US',
            enableBiometric: $data['enable_biometric'] ?? false,
            businessName: $data['business_name'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'agent_id'           => $this->agentId,
            'agent_did'          => $this->agentDid,
            'agent_name'         => $this->agentName,
            'verification_level' => $this->verificationLevel->value,
            'documents'          => $this->documents,
            'country_code'       => $this->countryCode,
            'enable_biometric'   => $this->enableBiometric,
            'business_name'      => $this->businessName,
            'metadata'           => $this->metadata,
        ];
    }

    public function isBusinessVerification(): bool
    {
        return $this->verificationLevel === KycVerificationLevel::FULL && ! empty($this->businessName);
    }

    public function requiresBiometric(): bool
    {
        return $this->enableBiometric && isset($this->documents['selfie']);
    }

    public function hasAllRequiredDocuments(): bool
    {
        $requiredDocuments = $this->verificationLevel->getRequiredDocuments();

        foreach ($requiredDocuments as $document) {
            if (! isset($this->documents[$document])) {
                return false;
            }
        }

        return true;
    }
}
