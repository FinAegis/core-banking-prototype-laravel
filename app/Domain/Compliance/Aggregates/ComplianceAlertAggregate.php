<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Events\AlertAssigned;
use App\Domain\Compliance\Events\AlertCreated;
use App\Domain\Compliance\Events\AlertEscalatedToCase;
use App\Domain\Compliance\Events\AlertLinked;
use App\Domain\Compliance\Events\AlertNoteAdded;
use App\Domain\Compliance\Events\AlertResolved;
use App\Domain\Compliance\Events\AlertStatusChanged;
use App\Domain\Compliance\Repositories\ComplianceEventRepository;
use App\Domain\Compliance\Repositories\ComplianceSnapshotRepository;
use App\Domain\Compliance\ValueObjects\AlertSeverity;
use App\Domain\Compliance\ValueObjects\AlertStatus;
use Illuminate\Support\Str;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class ComplianceAlertAggregate extends AggregateRoot
{
    private string $id;
    private string $type;
    private AlertSeverity $severity;
    private AlertStatus $status;
    private string $entityType;
    private string $entityId;
    private string $description;
    private array $details;
    private ?string $assignedTo = null;
    private array $notes = [];
    private array $linkedAlerts = [];
    private ?string $resolution = null;
    private ?string $caseId = null;

    public static function create(
        string $type,
        string $severity,
        string $entityType,
        string $entityId,
        string $description,
        array $details,
        ?string $userId = null
    ): self {
        $alertId = (string) Str::uuid();
        $alert = self::retrieve($alertId);
        
        $alert->recordThat(new AlertCreated(
            $alertId,
            $type,
            $severity,
            $entityType,
            $entityId,
            $description,
            $details,
            $userId
        ));
        
        return $alert;
    }

    public function assign(string $assignedTo, ?string $assignedBy = null, ?string $notes = null): self
    {
        if ($this->status->isClosed()) {
            throw new \DomainException('Cannot assign a closed alert');
        }

        $this->recordThat(new AlertAssigned($this->id, $assignedTo, $assignedBy, $notes));
        
        return $this;
    }

    public function changeStatus(string $newStatus, ?string $reason = null, ?string $userId = null): self
    {
        $oldStatus = $this->status->value();
        
        if ($oldStatus === $newStatus) {
            return $this;
        }

        $this->recordThat(new AlertStatusChanged($this->id, $oldStatus, $newStatus, $reason, $userId));
        
        return $this;
    }

    public function addNote(string $note, string $addedBy, array $attachments = []): self
    {
        $this->recordThat(new AlertNoteAdded($this->id, $note, $addedBy, $attachments));
        
        return $this;
    }

    public function resolve(string $resolution, string $resolvedBy, ?string $notes = null): self
    {
        if ($this->status->isClosed()) {
            throw new \DomainException('Alert is already closed');
        }

        $this->recordThat(new AlertResolved($this->id, $resolution, $resolvedBy, $notes));
        
        return $this;
    }

    public function linkAlerts(array $alertIds, string $linkType, ?string $userId = null): self
    {
        if (empty($alertIds)) {
            throw new \DomainException('At least one alert ID must be provided');
        }

        $this->recordThat(new AlertLinked($this->id, $alertIds, $linkType, $userId));
        
        return $this;
    }

    public function escalateToCase(string $caseId, string $escalatedBy, string $reason): self
    {
        if ($this->caseId !== null) {
            throw new \DomainException('Alert is already associated with a case');
        }

        $this->recordThat(new AlertEscalatedToCase($this->id, $caseId, $escalatedBy, $reason));
        
        return $this;
    }

    // Event handlers
    protected function applyAlertCreated(AlertCreated $event): void
    {
        $this->id = $event->alertId;
        $this->type = $event->type;
        $this->severity = new AlertSeverity($event->severity);
        $this->status = new AlertStatus('open');
        $this->entityType = $event->entityType;
        $this->entityId = $event->entityId;
        $this->description = $event->description;
        $this->details = $event->details;
    }

    protected function applyAlertAssigned(AlertAssigned $event): void
    {
        $this->assignedTo = $event->assignedTo;
    }

    protected function applyAlertStatusChanged(AlertStatusChanged $event): void
    {
        $this->status = new AlertStatus($event->newStatus);
    }

    protected function applyAlertNoteAdded(AlertNoteAdded $event): void
    {
        $this->notes[] = [
            'note' => $event->note,
            'added_by' => $event->addedBy,
            'attachments' => $event->attachments,
            'added_at' => $event->occurredAt ?? now(),
        ];
    }

    protected function applyAlertResolved(AlertResolved $event): void
    {
        $this->status = new AlertStatus('closed');
        $this->resolution = $event->resolution;
    }

    protected function applyAlertLinked(AlertLinked $event): void
    {
        $this->linkedAlerts = array_unique(array_merge($this->linkedAlerts, $event->linkedAlertIds));
    }

    protected function applyAlertEscalatedToCase(AlertEscalatedToCase $event): void
    {
        $this->caseId = $event->caseId;
        $this->status = new AlertStatus('escalated');
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status->value();
    }

    public function getSeverity(): string
    {
        return $this->severity->value();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getAssignedTo(): ?string
    {
        return $this->assignedTo;
    }

    public function getNotes(): array
    {
        return $this->notes;
    }

    public function getLinkedAlerts(): array
    {
        return $this->linkedAlerts;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function getCaseId(): ?string
    {
        return $this->caseId;
    }

    // Override Spatie methods to use our custom repositories
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app()->make(ComplianceEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app()->make(ComplianceSnapshotRepository::class);
    }
}
