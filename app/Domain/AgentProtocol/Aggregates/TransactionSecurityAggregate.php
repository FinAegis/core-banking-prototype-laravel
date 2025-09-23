<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\TransactionEncrypted;
use App\Domain\AgentProtocol\Events\TransactionFraudChecked;
use App\Domain\AgentProtocol\Events\TransactionSecurityInitialized;
use App\Domain\AgentProtocol\Events\TransactionSigned;
use App\Domain\AgentProtocol\Events\TransactionVerified;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class TransactionSecurityAggregate extends AggregateRoot
{
    protected string $securityId = '';

    protected string $transactionId = '';

    protected string $agentId = '';

    protected array $signatures = [];

    protected array $encryptionKeys = [];

    protected array $verificationHistory = [];

    protected array $fraudChecks = [];

    protected string $securityLevel = 'standard'; // standard, enhanced, maximum

    protected string $status = 'pending'; // pending, signed, encrypted, verified, suspicious, failed

    protected array $metadata = [];

    protected ?Carbon $createdAt = null;

    protected ?Carbon $lastVerifiedAt = null;

    protected const SECURITY_LEVELS = [
        'standard' => ['signature' => true, 'encryption' => false, 'fraud_check' => true],
        'enhanced' => ['signature' => true, 'encryption' => true, 'fraud_check' => true],
        'maximum'  => ['signature' => true, 'encryption' => true, 'fraud_check' => true, 'multi_sig' => true],
    ];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function initializeSecurity(
        string $securityId,
        string $transactionId,
        string $agentId,
        string $securityLevel = 'standard',
        array $metadata = []
    ): self {
        if (! array_key_exists($securityLevel, self::SECURITY_LEVELS)) {
            throw new InvalidArgumentException("Invalid security level: {$securityLevel}");
        }

        $aggregate = static::retrieve($securityId);

        $aggregate->recordThat(new TransactionSecurityInitialized(
            securityId: $securityId,
            transactionId: $transactionId,
            agentId: $agentId,
            securityLevel: $securityLevel,
            requirements: self::SECURITY_LEVELS[$securityLevel],
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function signTransaction(
        string $signatureData,
        string $signatureMethod,
        string $publicKey,
        array $metadata = []
    ): self {
        if ($this->status === 'failed') {
            throw new InvalidArgumentException('Cannot sign a failed transaction');
        }

        $this->recordThat(new TransactionSigned(
            securityId: $this->securityId,
            transactionId: $this->transactionId,
            agentId: $this->agentId,
            signatureData: $signatureData,
            signatureMethod: $signatureMethod,
            publicKey: $publicKey,
            timestamp: Carbon::now(),
            metadata: $metadata
        ));

        return $this;
    }

    public function encryptTransaction(
        string $encryptedData,
        string $encryptionMethod,
        string $keyId,
        array $metadata = []
    ): self {
        if (! in_array($this->securityLevel, ['enhanced', 'maximum'])) {
            throw new InvalidArgumentException('Encryption not required for security level: ' . $this->securityLevel);
        }

        $this->recordThat(new TransactionEncrypted(
            securityId: $this->securityId,
            transactionId: $this->transactionId,
            agentId: $this->agentId,
            encryptedData: $encryptedData,
            encryptionMethod: $encryptionMethod,
            keyId: $keyId,
            timestamp: Carbon::now(),
            metadata: $metadata
        ));

        return $this;
    }

    public function verifyTransaction(
        bool $signatureValid,
        bool $encryptionValid,
        array $verificationDetails,
        array $metadata = []
    ): self {
        $isValid = $signatureValid;

        if (in_array($this->securityLevel, ['enhanced', 'maximum'])) {
            $isValid = $isValid && $encryptionValid;
        }

        $this->recordThat(new TransactionVerified(
            securityId: $this->securityId,
            transactionId: $this->transactionId,
            agentId: $this->agentId,
            isValid: $isValid,
            signatureValid: $signatureValid,
            encryptionValid: $encryptionValid,
            verificationDetails: $verificationDetails,
            timestamp: Carbon::now(),
            metadata: $metadata
        ));

        return $this;
    }

    public function checkForFraud(
        float $riskScore,
        array $riskFactors,
        string $decision,
        array $metadata = []
    ): self {
        if ($riskScore < 0 || $riskScore > 100) {
            throw new InvalidArgumentException('Risk score must be between 0 and 100');
        }

        if (! in_array($decision, ['approve', 'reject', 'review'])) {
            throw new InvalidArgumentException('Invalid fraud check decision');
        }

        $this->recordThat(new TransactionFraudChecked(
            securityId: $this->securityId,
            transactionId: $this->transactionId,
            agentId: $this->agentId,
            riskScore: $riskScore,
            riskFactors: $riskFactors,
            decision: $decision,
            timestamp: Carbon::now(),
            metadata: $metadata
        ));

        return $this;
    }

    // Event application methods
    protected function applyTransactionSecurityInitialized(TransactionSecurityInitialized $event): void
    {
        $this->securityId = $event->securityId;
        $this->transactionId = $event->transactionId;
        $this->agentId = $event->agentId;
        $this->securityLevel = $event->securityLevel;
        $this->metadata = $event->metadata;
        $this->createdAt = Carbon::now();
        $this->status = 'pending';
    }

    protected function applyTransactionSigned(TransactionSigned $event): void
    {
        $this->signatures[] = [
            'data'      => $event->signatureData,
            'method'    => $event->signatureMethod,
            'publicKey' => $event->publicKey,
            'timestamp' => $event->timestamp->toIso8601String(),
        ];

        if ($this->status === 'pending') {
            $this->status = 'signed';
        }
    }

    protected function applyTransactionEncrypted(TransactionEncrypted $event): void
    {
        $this->encryptionKeys[] = [
            'keyId'     => $event->keyId,
            'method'    => $event->encryptionMethod,
            'timestamp' => $event->timestamp->toIso8601String(),
        ];

        $this->status = 'encrypted';
    }

    protected function applyTransactionVerified(TransactionVerified $event): void
    {
        $this->verificationHistory[] = [
            'isValid'         => $event->isValid,
            'signatureValid'  => $event->signatureValid,
            'encryptionValid' => $event->encryptionValid,
            'details'         => $event->verificationDetails,
            'timestamp'       => $event->timestamp->toIso8601String(),
        ];

        $this->lastVerifiedAt = $event->timestamp;

        if ($event->isValid) {
            $this->status = 'verified';
        } else {
            $this->status = 'failed';
        }
    }

    protected function applyTransactionFraudChecked(TransactionFraudChecked $event): void
    {
        $this->fraudChecks[] = [
            'riskScore'   => $event->riskScore,
            'riskFactors' => $event->riskFactors,
            'decision'    => $event->decision,
            'timestamp'   => $event->timestamp->toIso8601String(),
        ];

        if ($event->decision === 'reject') {
            $this->status = 'suspicious';
        }
    }

    // Getters
    public function getSecurityId(): string
    {
        return $this->securityId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getSecurityLevel(): string
    {
        return $this->securityLevel;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSignatures(): array
    {
        return $this->signatures;
    }

    public function getFraudChecks(): array
    {
        return $this->fraudChecks;
    }

    public function getLatestRiskScore(): ?float
    {
        if (empty($this->fraudChecks)) {
            return null;
        }

        return $this->fraudChecks[count($this->fraudChecks) - 1]['riskScore'];
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function requiresEncryption(): bool
    {
        return in_array($this->securityLevel, ['enhanced', 'maximum']);
    }

    public function requiresMultiSignature(): bool
    {
        return $this->securityLevel === 'maximum';
    }
}
