<?php

namespace App\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Events\GdprDataDeleted;
use App\Domain\Compliance\Events\GdprDataExported;
use App\Domain\Compliance\Events\GdprRequestReceived;
use App\Domain\Compliance\Events\KycDocumentUploaded;
use App\Domain\Compliance\Events\KycSubmissionReceived;
use App\Domain\Compliance\Events\KycVerificationCompleted;
use App\Domain\Compliance\Events\KycVerificationRejected;
use App\Domain\Compliance\Events\RegulatoryReportGenerated;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class ComplianceAggregate extends AggregateRoot
{
    private string $userUuid;

    private string $kycStatus = 'pending';

    private array $kycDocuments = [];

    private ?string $kycLevel = null;

    private array $gdprConsents = [];

    public function submitKyc(string $userUuid, array $documents): self
    {
        $this->recordThat(new KycSubmissionReceived($userUuid, $documents));

        foreach ($documents as $document) {
            $this->recordThat(new KycDocumentUploaded($userUuid, $document));
        }

        return $this;
    }

    public function approveKyc(string $userUuid, string $level): self
    {
        $this->recordThat(new KycVerificationCompleted($userUuid, $level));

        return $this;
    }

    public function rejectKyc(string $userUuid, string $reason): self
    {
        $this->recordThat(new KycVerificationRejected($userUuid, $reason));

        return $this;
    }

    public function requestGdprExport(string $userUuid, array $options): self
    {
        $this->recordThat(new GdprRequestReceived($userUuid, 'export', $options));

        return $this;
    }

    public function completeGdprExport(string $userUuid, string $filePath): self
    {
        $this->recordThat(new GdprDataExported($userUuid, $filePath));

        return $this;
    }

    public function requestGdprDeletion(string $userUuid, string $reason): self
    {
        $this->recordThat(new GdprRequestReceived($userUuid, 'deletion', ['reason' => $reason]));

        return $this;
    }

    public function completeGdprDeletion(string $userUuid): self
    {
        $this->recordThat(new GdprDataDeleted($userUuid));

        return $this;
    }

    public function generateRegulatoryReport(string $reportType, array $data): self
    {
        $this->recordThat(new RegulatoryReportGenerated($reportType, $data));

        return $this;
    }

    public function getKycStatus(): string
    {
        return $this->kycStatus;
    }

    public function getKycLevel(): ?string
    {
        return $this->kycLevel;
    }

    public function getKycDocuments(): array
    {
        return $this->kycDocuments;
    }

    public function getUserUuid(): string
    {
        return $this->userUuid;
    }

    public function getGdprConsents(): array
    {
        return $this->gdprConsents;
    }

    protected function applyKycSubmissionReceived(KycSubmissionReceived $event): void
    {
        $this->userUuid = $event->userUuid;
        $this->kycStatus = 'pending';
    }

    protected function applyKycDocumentUploaded(KycDocumentUploaded $event): void
    {
        $this->kycDocuments[] = $event->document;
    }

    protected function applyKycVerificationCompleted(KycVerificationCompleted $event): void
    {
        $this->kycStatus = 'approved';
        $this->kycLevel = $event->level;
    }

    protected function applyKycVerificationRejected(KycVerificationRejected $event): void
    {
        $this->kycStatus = 'rejected';
    }

    protected function applyGdprRequestReceived(GdprRequestReceived $event): void
    {
        // Request received, processing started
    }

    protected function applyGdprDataExported(GdprDataExported $event): void
    {
        // Export completed
    }

    protected function applyGdprDataDeleted(GdprDataDeleted $event): void
    {
        // Deletion completed
    }

    protected function applyRegulatoryReportGenerated(RegulatoryReportGenerated $event): void
    {
        // Report generated
    }
}
