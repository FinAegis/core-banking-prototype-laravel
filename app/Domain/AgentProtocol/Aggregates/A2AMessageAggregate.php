<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\MessageAcknowledged;
use App\Domain\AgentProtocol\Events\MessageDelivered;
use App\Domain\AgentProtocol\Events\MessageExpired;
use App\Domain\AgentProtocol\Events\MessageFailed;
use App\Domain\AgentProtocol\Events\MessageQueued;
use App\Domain\AgentProtocol\Events\MessageRetried;
use App\Domain\AgentProtocol\Events\MessageSent;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class A2AMessageAggregate extends AggregateRoot
{
    private const STATUS_CREATED = 'created';

    private const STATUS_QUEUED = 'queued';

    private const STATUS_SENT = 'sent';

    private const STATUS_DELIVERED = 'delivered';

    private const STATUS_ACKNOWLEDGED = 'acknowledged';

    private const STATUS_FAILED = 'failed';

    private const STATUS_EXPIRED = 'expired';

    private const TYPE_DIRECT = 'direct';

    private const TYPE_BROADCAST = 'broadcast';

    private const TYPE_PROTOCOL = 'protocol';

    private const TYPE_TRANSACTION = 'transaction';

    private const TYPE_NOTIFICATION = 'notification';

    private const PRIORITY_LOW = 0;

    private const PRIORITY_NORMAL = 50;

    private const PRIORITY_HIGH = 75;

    private const PRIORITY_CRITICAL = 100;

    private string $messageId = '';

    private string $fromAgentId = '';

    private string $toAgentId = '';

    private string $messageType = self::TYPE_DIRECT;

    private string $status = self::STATUS_CREATED;

    private array $payload = [];

    private array $headers = [];

    private int $priority = self::PRIORITY_NORMAL;

    private ?string $correlationId = null;

    private ?string $replyTo = null;

    private int $retryCount = 0;

    private int $maxRetries = 3;

    private ?string $protocolVersion = null;

    private ?Carbon $expiresAt = null;

    private ?Carbon $deliveredAt = null;

    private ?Carbon $acknowledgedAt = null;

    private array $routingPath = [];

    private array $metadata = [];

    private bool $requiresAcknowledgment = true;

    private ?string $failureReason = null;

    private array $retryHistory = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function send(
        string $messageId,
        string $fromAgentId,
        string $toAgentId,
        array $payload,
        string $messageType = self::TYPE_DIRECT,
        int $priority = self::PRIORITY_NORMAL,
        ?string $correlationId = null,
        ?string $replyTo = null,
        array $headers = [],
        array $metadata = []
    ): self {
        if (empty($fromAgentId) || empty($toAgentId)) {
            throw new InvalidArgumentException('From and To agent IDs are required');
        }

        if (
            ! in_array($messageType, [
            self::TYPE_DIRECT,
            self::TYPE_BROADCAST,
            self::TYPE_PROTOCOL,
            self::TYPE_TRANSACTION,
            self::TYPE_NOTIFICATION,
            ], true)
        ) {
            throw new InvalidArgumentException("Invalid message type: {$messageType}");
        }

        if ($priority < 0 || $priority > 100) {
            throw new InvalidArgumentException('Priority must be between 0 and 100');
        }

        $aggregate = static::retrieve($messageId);
        $aggregate->recordThat(new MessageSent(
            messageId: $messageId,
            fromAgentId: $fromAgentId,
            toAgentId: $toAgentId,
            messageType: $messageType,
            payload: $payload,
            headers: $headers,
            priority: $priority,
            correlationId: $correlationId,
            replyTo: $replyTo,
            sentAt: now()->toIso8601String(),
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function queue(
        string $queueName = 'default',
        ?int $delay = null,
        ?Carbon $processAfter = null
    ): self {
        if ($this->status !== self::STATUS_CREATED) {
            throw new InvalidArgumentException("Cannot queue message in status: {$this->status}");
        }

        $this->recordThat(new MessageQueued(
            messageId: $this->messageId,
            queueName: $queueName,
            priority: $this->priority,
            delay: $delay,
            processAfter: $processAfter?->toIso8601String(),
            queuedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function deliver(
        string $deliveryMethod = 'webhook',
        array $deliveryDetails = []
    ): self {
        if (! in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENT], true)) {
            throw new InvalidArgumentException("Cannot deliver message in status: {$this->status}");
        }

        if ($this->expiresAt && $this->expiresAt->isPast()) {
            throw new InvalidArgumentException('Cannot deliver expired message');
        }

        $this->recordThat(new MessageDelivered(
            messageId: $this->messageId,
            toAgentId: $this->toAgentId,
            deliveryMethod: $deliveryMethod,
            deliveryDetails: $deliveryDetails,
            deliveredAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function acknowledge(
        string $acknowledgedBy,
        ?string $acknowledgmentId = null,
        array $acknowledgmentData = []
    ): self {
        if ($this->status !== self::STATUS_DELIVERED) {
            throw new InvalidArgumentException("Cannot acknowledge message in status: {$this->status}");
        }

        if (! $this->requiresAcknowledgment) {
            throw new InvalidArgumentException('Message does not require acknowledgment');
        }

        $this->recordThat(new MessageAcknowledged(
            messageId: $this->messageId,
            acknowledgedBy: $acknowledgedBy,
            acknowledgmentId: $acknowledgmentId,
            acknowledgmentData: $acknowledgmentData,
            acknowledgedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function retry(
        string $reason,
        ?int $nextRetryDelay = null,
        array $retryDetails = []
    ): self {
        if (in_array($this->status, [self::STATUS_ACKNOWLEDGED, self::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException("Cannot retry message in status: {$this->status}");
        }

        if ($this->retryCount >= $this->maxRetries) {
            throw new InvalidArgumentException("Maximum retry count ({$this->maxRetries}) reached");
        }

        $nextRetryDelay ??= $this->calculateBackoffDelay();

        $this->recordThat(new MessageRetried(
            messageId: $this->messageId,
            retryCount: $this->retryCount + 1,
            reason: $reason,
            nextRetryDelay: $nextRetryDelay,
            retryDetails: $retryDetails,
            retriedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function fail(
        string $reason,
        array $errorDetails = [],
        bool $permanent = false
    ): self {
        if (in_array($this->status, [self::STATUS_ACKNOWLEDGED, self::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException("Cannot fail message in status: {$this->status}");
        }

        $this->recordThat(new MessageFailed(
            messageId: $this->messageId,
            reason: $reason,
            errorDetails: $errorDetails,
            permanent: $permanent,
            canRetry: ! $permanent && $this->retryCount < $this->maxRetries,
            failedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function expire(string $reason = 'TTL exceeded'): self
    {
        if (in_array($this->status, [self::STATUS_ACKNOWLEDGED, self::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException("Cannot expire message in status: {$this->status}");
        }

        $this->recordThat(new MessageExpired(
            messageId: $this->messageId,
            reason: $reason,
            expiredAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function setExpiration(Carbon $expiresAt): self
    {
        if ($expiresAt->isPast()) {
            throw new InvalidArgumentException('Expiration date must be in the future');
        }

        $this->expiresAt = $expiresAt;
        $this->metadata['expiresAt'] = $expiresAt->toIso8601String();

        return $this;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('Max retries must be non-negative');
        }

        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function setProtocolVersion(string $version): self
    {
        $this->protocolVersion = $version;

        return $this;
    }

    public function setRequiresAcknowledgment(bool $requires): self
    {
        $this->requiresAcknowledgment = $requires;

        return $this;
    }

    public function addRoutingNode(string $nodeId, array $nodeData = []): self
    {
        $this->routingPath[] = [
            'nodeId'    => $nodeId,
            'timestamp' => now()->toIso8601String(),
            'data'      => $nodeData,
        ];

        return $this;
    }

    protected function applyMessageSent(MessageSent $event): void
    {
        $this->messageId = $event->messageId;
        $this->fromAgentId = $event->fromAgentId;
        $this->toAgentId = $event->toAgentId;
        $this->messageType = $event->messageType;
        $this->payload = $event->payload;
        $this->headers = $event->headers;
        $this->priority = $event->priority;
        $this->correlationId = $event->correlationId;
        $this->replyTo = $event->replyTo;
        $this->metadata = $event->metadata;
        $this->status = self::STATUS_CREATED;
        $this->metadata['sentAt'] = $event->sentAt;
    }

    protected function applyMessageQueued(MessageQueued $event): void
    {
        $this->status = self::STATUS_QUEUED;
        $this->metadata['queueName'] = $event->queueName;
        $this->metadata['queuedAt'] = $event->queuedAt;
        if ($event->processAfter) {
            $this->metadata['processAfter'] = $event->processAfter;
        }
    }

    protected function applyMessageDelivered(MessageDelivered $event): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = Carbon::parse($event->deliveredAt);
        $this->metadata['deliveryMethod'] = $event->deliveryMethod;
        $this->metadata['deliveryDetails'] = $event->deliveryDetails;
    }

    protected function applyMessageAcknowledged(MessageAcknowledged $event): void
    {
        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->acknowledgedAt = Carbon::parse($event->acknowledgedAt);
        $this->metadata['acknowledgedBy'] = $event->acknowledgedBy;
        if ($event->acknowledgmentId) {
            $this->metadata['acknowledgmentId'] = $event->acknowledgmentId;
        }
        $this->metadata['acknowledgmentData'] = $event->acknowledgmentData;
    }

    protected function applyMessageRetried(MessageRetried $event): void
    {
        $this->retryCount = $event->retryCount;
        $this->status = self::STATUS_QUEUED;
        $this->retryHistory[] = [
            'attempt'   => $event->retryCount,
            'reason'    => $event->reason,
            'retriedAt' => $event->retriedAt,
            'nextDelay' => $event->nextRetryDelay,
            'details'   => $event->retryDetails,
        ];
    }

    protected function applyMessageFailed(MessageFailed $event): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $event->reason;
        $this->metadata['failure'] = [
            'reason'    => $event->reason,
            'details'   => $event->errorDetails,
            'permanent' => $event->permanent,
            'canRetry'  => $event->canRetry,
            'failedAt'  => $event->failedAt,
        ];
    }

    protected function applyMessageExpired(MessageExpired $event): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->metadata['expiration'] = [
            'reason'    => $event->reason,
            'expiredAt' => $event->expiredAt,
        ];
    }

    private function calculateBackoffDelay(): int
    {
        return min(300, pow(2, $this->retryCount) * 10);
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getFromAgentId(): string
    {
        return $this->fromAgentId;
    }

    public function getToAgentId(): string
    {
        return $this->toAgentId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public static function getPriorityLevels(): array
    {
        return [
            'low'      => self::PRIORITY_LOW,
            'normal'   => self::PRIORITY_NORMAL,
            'high'     => self::PRIORITY_HIGH,
            'critical' => self::PRIORITY_CRITICAL,
        ];
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function getDeliveredAt(): ?Carbon
    {
        return $this->deliveredAt;
    }

    public function getAcknowledgedAt(): ?Carbon
    {
        return $this->acknowledgedAt;
    }

    public function getRoutingPath(): array
    {
        return $this->routingPath;
    }

    public function getRetryHistory(): array
    {
        return $this->retryHistory;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function requiresAcknowledgment(): bool
    {
        return $this->requiresAcknowledgment;
    }

    public function canRetry(): bool
    {
        return $this->retryCount < $this->maxRetries
            && ! in_array($this->status, [self::STATUS_ACKNOWLEDGED, self::STATUS_EXPIRED], true);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expiresAt && $this->expiresAt->isPast());
    }

    public function getProtocolVersion(): ?string
    {
        return $this->protocolVersion;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }
}
