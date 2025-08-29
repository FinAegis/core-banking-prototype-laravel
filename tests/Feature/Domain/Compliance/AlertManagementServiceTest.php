<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Compliance;

use App\Domain\Compliance\Events\AlertEscalated;
use App\Domain\Compliance\Events\AlertResolved;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Domain\Compliance\Services\AlertManagementService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AlertManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AlertManagementService();
        Event::fake();
    }

    public function test_creates_compliance_alert_successfully(): void
    {
        // Arrange
        $data = [
            'type'             => 'transaction',
            'severity'         => 'high',
            'title'            => 'Suspicious Transaction Detected',
            'description'      => 'Large transaction to high-risk country',
            'risk_score'       => 75,  // Changed from 85 to avoid auto-escalation
            'confidence_score' => 0.9,
            'evidence'         => [
                'amount'      => 50000,
                'destination' => 'high_risk_country',
            ],
        ];

        // Act
        $alert = $this->service->createAlert($data);

        // Assert
        $this->assertInstanceOf(ComplianceAlert::class, $alert);
        $this->assertEquals('transaction', $alert->type);
        $this->assertEquals('high', $alert->severity);
        $this->assertEquals('open', $alert->status);
        $this->assertEquals(75, $alert->risk_score);
        $this->assertNotNull($alert->alert_id);
        $this->assertStringStartsWith('TXN-', $alert->alert_id);
    }

    public function test_escalates_critical_alerts_automatically(): void
    {
        // Arrange
        $data = [
            'type'        => 'pattern',
            'severity'    => 'critical',
            'title'       => 'Money Laundering Pattern Detected',
            'description' => 'Complex layering pattern identified',
            'risk_score'  => 95,
        ];

        // Act
        $alert = $this->service->createAlert($data);

        // Assert
        $this->assertEquals(ComplianceAlert::STATUS_ESCALATED, $alert->status);
        $this->assertNotNull($alert->escalated_at);
        $this->assertNotNull($alert->escalation_reason);

        Event::assertDispatched(AlertEscalated::class, function ($event) use ($alert) {
            return $event->alert->id === $alert->id;
        });
    }

    public function test_finds_and_links_similar_alerts(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Create existing alerts
        $existingAlerts = ComplianceAlert::factory()->count(3)->create([
            'type'       => 'velocity',
            'user_id'    => $user->id,
            'status'     => ComplianceAlert::STATUS_OPEN,
            'created_at' => now()->subHours(2),
        ]);

        $data = [
            'type'        => 'velocity',
            'severity'    => 'medium',
            'title'       => 'High Velocity Transactions',
            'description' => 'Multiple rapid transactions detected',
            'user_id'     => $user->id,
            'risk_score'  => 65,
        ];

        // Act
        $alert = $this->service->createAlert($data);

        // Assert
        $this->assertEquals(ComplianceAlert::STATUS_ESCALATED, $alert->status);
        $this->assertStringContainsString('Multiple similar alerts detected', $alert->escalation_reason);
    }

    public function test_updates_alert_status_with_history(): void
    {
        // Arrange
        $alert = ComplianceAlert::factory()->create([
            'status' => ComplianceAlert::STATUS_OPEN,
        ]);
        $user = User::factory()->create();

        // Act
        $updatedAlert = $this->service->updateAlertStatus(
            $alert,
            ComplianceAlert::STATUS_IN_REVIEW,
            $user,
            'Starting investigation'
        );

        // Assert
        $this->assertEquals(ComplianceAlert::STATUS_IN_REVIEW, $updatedAlert->status);
        $this->assertNotNull($updatedAlert->status_changed_at);
        $this->assertEquals($user->id, $updatedAlert->status_changed_by);
        $this->assertNotEmpty($updatedAlert->history);
        $this->assertEquals('status_change', $updatedAlert->history[0]['action']);
    }

    public function test_assigns_alert_to_user(): void
    {
        // Arrange
        $alert = ComplianceAlert::factory()->create();
        $assignee = User::factory()->create();
        $assignedBy = User::factory()->create();

        // Act
        $assignedAlert = $this->service->assignAlert($alert, $assignee, $assignedBy);

        // Assert
        $this->assertEquals($assignee->id, $assignedAlert->assigned_to);
        $this->assertNotNull($assignedAlert->assigned_at);
        $this->assertEquals($assignedBy->id, $assignedAlert->assigned_by);
        $this->assertNotEmpty($assignedAlert->history);
    }

    public function test_adds_investigation_note(): void
    {
        // Arrange
        $alert = ComplianceAlert::factory()->create();
        $user = User::factory()->create();
        $note = 'Initial review shows potential false positive';

        // Act
        $updatedAlert = $this->service->addInvestigationNote($alert, $note, $user);

        // Assert
        $this->assertNotEmpty($updatedAlert->investigation_notes);
        $this->assertEquals($note, $updatedAlert->investigation_notes[0]['note']);
        $this->assertEquals($user->id, $updatedAlert->investigation_notes[0]['user_id']);
    }

    public function test_creates_case_from_multiple_alerts(): void
    {
        // Arrange
        $alerts = ComplianceAlert::factory()->count(3)->create([
            'type'       => 'pattern',
            'severity'   => 'high',
            'risk_score' => 80,
        ]);

        $caseData = [
            'title'       => 'Investigation: Multiple Pattern Alerts',
            'description' => 'Investigating related suspicious patterns',
            'type'        => 'investigation',
            'created_by'  => User::factory()->create()->id,
        ];

        // Act
        $case = $this->service->createCaseFromAlerts(
            $alerts->pluck('id')->toArray(),
            $caseData
        );

        // Assert
        $this->assertInstanceOf(ComplianceCase::class, $case);
        $this->assertEquals(3, $case->alert_count);
        $this->assertEquals(240, $case->total_risk_score); // 80 * 3
        $this->assertEquals('high', $case->priority);

        // Verify alerts are linked to case
        $alerts->each(function ($alert) use ($case) {
            $alert->refresh();
            $this->assertEquals($case->id, $alert->case_id);
            $this->assertEquals(ComplianceAlert::STATUS_IN_REVIEW, $alert->status);
        });
    }

    public function test_marks_alert_as_resolved(): void
    {
        // Arrange
        $alert = ComplianceAlert::factory()->create([
            'status' => ComplianceAlert::STATUS_IN_REVIEW,
        ]);
        $user = User::factory()->create();
        $notes = 'Verified as legitimate business transaction';

        // Act
        $resolvedAlert = $this->service->updateAlertStatus(
            $alert,
            ComplianceAlert::STATUS_RESOLVED,
            $user,
            $notes
        );

        // Assert
        $this->assertEquals(ComplianceAlert::STATUS_RESOLVED, $resolvedAlert->status);
        $this->assertNotNull($resolvedAlert->resolved_at);
        $this->assertEquals($user->id, $resolvedAlert->resolved_by);
        $this->assertEquals($notes, $resolvedAlert->resolution_notes);
        $this->assertNotNull($resolvedAlert->resolution_time_hours);

        Event::assertDispatched(AlertResolved::class);
    }

    public function test_marks_alert_as_false_positive(): void
    {
        // Arrange
        $rule = \App\Domain\Compliance\Models\TransactionMonitoringRule::factory()->create([
            'true_positives'  => 10,
            'false_positives' => 2,
        ]);

        $alert = ComplianceAlert::factory()->create([
            'rule_id' => $rule->id,
            'status'  => ComplianceAlert::STATUS_IN_REVIEW,
        ]);

        $user = User::factory()->create();
        $notes = 'Regular business pattern misidentified';

        // Act
        $falsePositiveAlert = $this->service->updateAlertStatus(
            $alert,
            ComplianceAlert::STATUS_FALSE_POSITIVE,
            $user,
            $notes
        );

        // Assert
        $this->assertEquals(ComplianceAlert::STATUS_FALSE_POSITIVE, $falsePositiveAlert->status);
        $this->assertEquals($notes, $falsePositiveAlert->false_positive_notes);

        // Verify rule effectiveness updated
        $rule->refresh();
        $this->assertEquals(3, $rule->false_positives);
    }

    public function test_searches_alerts_with_criteria(): void
    {
        // Arrange
        ComplianceAlert::factory()->count(5)->create([
            'type'     => 'transaction',
            'severity' => 'high',
            'status'   => ComplianceAlert::STATUS_OPEN,
        ]);

        ComplianceAlert::factory()->count(3)->create([
            'type'     => 'pattern',
            'severity' => 'medium',
            'status'   => ComplianceAlert::STATUS_RESOLVED,
        ]);

        // Act
        $results = $this->service->searchAlerts([
            'type'     => 'transaction',
            'severity' => 'high',
            'status'   => ComplianceAlert::STATUS_OPEN,
        ]);

        // Assert
        $this->assertCount(5, $results);
        $results->each(function ($alert) {
            $this->assertEquals('transaction', $alert->type);
            $this->assertEquals('high', $alert->severity);
            $this->assertEquals(ComplianceAlert::STATUS_OPEN, $alert->status);
        });
    }

    public function test_calculates_alert_statistics(): void
    {
        // Arrange
        ComplianceAlert::factory()->count(10)->create([
            'status'   => ComplianceAlert::STATUS_OPEN,
            'severity' => 'high',
        ]);

        ComplianceAlert::factory()->count(5)->create([
            'status'                => ComplianceAlert::STATUS_RESOLVED,
            'severity'              => 'medium',
            'resolution_time_hours' => 24,
        ]);

        ComplianceAlert::factory()->count(2)->create([
            'status'   => ComplianceAlert::STATUS_FALSE_POSITIVE,
            'severity' => 'low',
        ]);

        // Act
        $stats = $this->service->getAlertStatistics();

        // Assert
        $this->assertEquals(17, $stats['total_alerts']);
        $this->assertEquals(10, $stats['by_status'][ComplianceAlert::STATUS_OPEN]);
        $this->assertEquals(5, $stats['by_status'][ComplianceAlert::STATUS_RESOLVED]);
        $this->assertEquals(2, $stats['by_status'][ComplianceAlert::STATUS_FALSE_POSITIVE]);
        $this->assertGreaterThan(0, $stats['false_positive_rate']);
    }

    public function test_gets_alert_trends(): void
    {
        // Arrange
        $dates = [
            now()->subDays(6),
            now()->subDays(5),
            now()->subDays(3),
            now()->subDays(1),
            now(),
        ];

        foreach ($dates as $date) {
            ComplianceAlert::factory()->count(rand(1, 3))->create([
                'severity'   => 'high',
                'created_at' => $date,
            ]);
        }

        // Act
        $trends = $this->service->getAlertTrends('7d');

        // Assert
        $this->assertIsArray($trends);
        $this->assertNotEmpty($trends);
        foreach ($trends as $date => $severities) {
            $this->assertArrayHasKey('high', $severities);
        }
    }
}
