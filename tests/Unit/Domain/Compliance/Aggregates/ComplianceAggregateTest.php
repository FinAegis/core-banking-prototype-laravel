<?php

namespace Tests\Unit\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Aggregates\ComplianceAggregate;
use App\Domain\Compliance\Events\GdprDataDeleted;
use App\Domain\Compliance\Events\GdprDataExported;
use App\Domain\Compliance\Events\GdprRequestReceived;
use App\Domain\Compliance\Events\KycDocumentUploaded;
use App\Domain\Compliance\Events\KycSubmissionReceived;
use App\Domain\Compliance\Events\KycVerificationCompleted;
use App\Domain\Compliance\Events\KycVerificationRejected;
use App\Domain\Compliance\Events\RegulatoryReportGenerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ComplianceAggregateTest extends DomainTestCase
{
    use RefreshDatabase;

    #[Test]
    public function submit_kyc_records_submission_and_document_events(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-123';

        $documents = [
            ['type' => 'passport', 'filename' => 'passport.jpg'],
            ['type' => 'utility_bill', 'filename' => 'utility.pdf'],
        ];

        $aggregate->submitKyc($userUuid, $documents);

        // Should record one submission event
        $aggregate->assertRecorded(function ($event) use ($userUuid, $documents) {
            return $event instanceof KycSubmissionReceived
                && $event->userUuid === $userUuid
                && $event->documents === $documents;
        });

        // Should record document events for each document
        foreach ($documents as $document) {
            $aggregate->assertRecorded(function ($event) use ($userUuid, $document) {
                return $event instanceof KycDocumentUploaded
                    && $event->userUuid === $userUuid
                    && $event->document === $document;
            });
        }

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'KYC submission and document events were recorded');
    }

    #[Test]
    public function approve_kyc_records_verification_completed(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-456';
        $level = 'enhanced';

        $aggregate->approveKyc($userUuid, $level);

        $aggregate->assertRecorded(function ($event) use ($userUuid, $level) {
            return $event instanceof KycVerificationCompleted
                && $event->userUuid === $userUuid
                && $event->level === $level;
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'KYC approval event was recorded');
    }

    #[Test]
    public function reject_kyc_records_verification_rejected(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-789';
        $reason = 'Invalid documents provided';

        $aggregate->rejectKyc($userUuid, $reason);

        $aggregate->assertRecorded(function ($event) use ($userUuid, $reason) {
            return $event instanceof KycVerificationRejected
                && $event->userUuid === $userUuid
                && $event->reason === $reason;
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'KYC rejection event was recorded');
    }

    #[Test]
    public function request_gdpr_export_records_request_event(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-gdpr-1';
        $options = ['include_transactions' => true, 'format' => 'json'];

        $aggregate->requestGdprExport($userUuid, $options);

        $aggregate->assertRecorded([
            new GdprRequestReceived($userUuid, 'export', $options),
        ]);
    }

    #[Test]
    public function complete_gdpr_export_records_export_event(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-gdpr-2';
        $filePath = '/exports/user-gdpr-2-20240115.zip';

        $aggregate->completeGdprExport($userUuid, $filePath);

        $aggregate->assertRecorded([
            new GdprDataExported($userUuid, $filePath),
        ]);
    }

    #[Test]
    public function request_gdpr_deletion_records_request_event(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-delete-1';
        $reason = 'User requested account deletion';

        $aggregate->requestGdprDeletion($userUuid, $reason);

        $aggregate->assertRecorded(function ($event) use ($userUuid, $reason) {
            return $event instanceof GdprRequestReceived
                && $event->userUuid === $userUuid
                && $event->requestType === 'deletion'
                && $event->options === ['reason' => $reason];
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'GDPR deletion request event was recorded');
    }

    #[Test]
    public function complete_gdpr_deletion_records_deletion_event(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-delete-2';

        $aggregate->completeGdprDeletion($userUuid);

        $aggregate->assertRecorded(function ($event) use ($userUuid) {
            return $event instanceof GdprDataDeleted
                && $event->userUuid === $userUuid;
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'GDPR deletion completion event was recorded');
    }

    #[Test]
    public function generate_regulatory_report_records_report_event(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $reportType = 'SAR';
        $data = [
            'period'               => '2024-Q1',
            'total_transactions'   => 15000,
            'flagged_transactions' => 23,
        ];

        $aggregate->generateRegulatoryReport($reportType, $data);

        $aggregate->assertRecorded(function ($event) use ($reportType, $data) {
            return $event instanceof RegulatoryReportGenerated
                && $event->reportType === $reportType
                && $event->data === $data;
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'Regulatory report event was recorded');
    }

    #[Test]
    public function applies_kyc_submission_received_event(): void
    {
        $aggregate = new ComplianceAggregate();
        $userUuid = 'user-apply-1';
        $documents = [['type' => 'passport']];

        $event = new KycSubmissionReceived($userUuid, $documents);

        // Use reflection to call protected method
        $reflection = new ReflectionClass($aggregate);
        $method = $reflection->getMethod('applyKycSubmissionReceived');
        $method->setAccessible(true);
        $method->invoke($aggregate, $event);

        // Use reflection to check private properties
        $statusProperty = $reflection->getProperty('kycStatus');
        $statusProperty->setAccessible(true);

        $this->assertEquals('pending', $statusProperty->getValue($aggregate));
    }

    #[Test]
    public function applies_kyc_verification_completed_event(): void
    {
        $aggregate = new ComplianceAggregate();
        $userUuid = 'user-verify-1';
        $level = 'full';

        $event = new KycVerificationCompleted($userUuid, $level);

        // Use reflection to call protected method
        $reflection = new ReflectionClass($aggregate);
        $method = $reflection->getMethod('applyKycVerificationCompleted');
        $method->setAccessible(true);
        $method->invoke($aggregate, $event);

        $statusProperty = $reflection->getProperty('kycStatus');
        $statusProperty->setAccessible(true);
        $this->assertEquals('approved', $statusProperty->getValue($aggregate));

        $levelProperty = $reflection->getProperty('kycLevel');
        $levelProperty->setAccessible(true);
        $this->assertEquals('full', $levelProperty->getValue($aggregate));
    }

    #[Test]
    public function applies_kyc_verification_rejected_event(): void
    {
        $aggregate = new ComplianceAggregate();
        $userUuid = 'user-reject-1';
        $reason = 'Documents expired';

        $event = new KycVerificationRejected($userUuid, $reason);

        // Use reflection to call protected method
        $reflection = new ReflectionClass($aggregate);
        $method = $reflection->getMethod('applyKycVerificationRejected');
        $method->setAccessible(true);
        $method->invoke($aggregate, $event);
        $statusProperty = $reflection->getProperty('kycStatus');
        $statusProperty->setAccessible(true);

        $this->assertEquals('rejected', $statusProperty->getValue($aggregate));
    }

    #[Test]
    public function full_kyc_workflow(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-workflow-1';

        // Submit KYC
        $documents = [
            ['type' => 'passport', 'filename' => 'passport.jpg'],
            ['type' => 'driver_license', 'filename' => 'license.jpg'],
        ];
        $aggregate->submitKyc($userUuid, $documents);

        // Approve KYC
        $aggregate->approveKyc($userUuid, 'enhanced');

        // Verify events were recorded
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof KycSubmissionReceived;
        });
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof KycDocumentUploaded;
        });
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof KycVerificationCompleted;
        });

        // Add explicit assertion for PHPUnit
        $this->assertTrue(true, 'Full KYC workflow events were recorded');
    }

    #[Test]
    public function full_gdpr_workflow(): void
    {
        $aggregate = ComplianceAggregate::fake();
        $userUuid = 'user-gdpr-workflow';

        // Request export
        $aggregate->requestGdprExport($userUuid, ['format' => 'json']);

        // Complete export
        $aggregate->completeGdprExport($userUuid, '/exports/data.json');

        // Request deletion
        $aggregate->requestGdprDeletion($userUuid, 'User request');

        // Complete deletion
        $aggregate->completeGdprDeletion($userUuid);

        // Verify events were recorded
        $this->assertCount(4, $aggregate->aggregateRoot()->getRecordedEvents());

        // Check specific event types were recorded
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof GdprRequestReceived;
        });
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof GdprDataExported;
        });
        $aggregate->assertRecorded(function ($event) {
            return $event instanceof GdprDataDeleted;
        });
    }
}
