<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceCaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_compliance_cases(): void
    {
        ComplianceCase::factory()->create([
            'case_id'          => 'CASE-2025-001',
            'title'            => 'High Risk Investigation',
            'description'      => 'Investigation into high-risk activity',
            'type'             => 'investigation',
            'priority'         => 'high',
            'status'           => 'open',
            'alert_count'      => 3,
            'total_risk_score' => 85.5,
        ]);

        ComplianceCase::factory()->create([
            'case_id'          => 'CASE-2025-002',
            'title'            => 'SAR Filing',
            'description'      => 'Suspicious activity report',
            'type'             => 'sar',
            'priority'         => 'critical',
            'status'           => 'in_progress',
            'alert_count'      => 5,
            'total_risk_score' => 95.0,
        ]);

        $response = $this->getJson('/api/compliance/cases');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'case_id',
                        'title',
                        'type',
                        'priority',
                        'status',
                        'alert_count',
                        'total_risk_score',
                    ],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_can_filter_cases_by_status(): void
    {
        ComplianceCase::factory()->create([
            'case_id'     => 'CASE-2025-001',
            'title'       => 'Open Investigation',
            'type'        => 'investigation',
            'priority'    => 'high',
            'status'      => 'open',
            'alert_count' => 2,
        ]);

        ComplianceCase::factory()->create([
            'case_id'     => 'CASE-2025-002',
            'title'       => 'Closed Case',
            'type'        => 'investigation',
            'priority'    => 'low',
            'status'      => 'closed',
            'alert_count' => 1,
        ]);

        $response = $this->getJson('/api/compliance/cases?status=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_can_create_compliance_case(): void
    {
        $response = $this->postJson('/api/compliance/cases', [
            'title'       => 'Money Laundering Investigation',
            'description' => 'Potential money laundering activity detected',
            'type'        => 'aml',
            'priority'    => 'critical',
            'entities'    => [
                ['type' => 'account', 'id' => 'ACC-001'],
                ['type' => 'account', 'id' => 'ACC-002'],
            ],
            'initial_findings' => 'Multiple large transfers between accounts',
            'due_date'         => now()->addDays(7)->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Case created successfully')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'case_id',
                    'title',
                    'type',
                    'priority',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('compliance_cases', [
            'title'    => 'Money Laundering Investigation',
            'type'     => 'aml',
            'priority' => 'critical',
            'status'   => 'open',
        ]);
    }

    public function test_can_show_case_details(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'          => 'CASE-2025-003',
            'title'            => 'Fraud Investigation',
            'description'      => 'Investigating fraudulent activity',
            'type'             => 'fraud',
            'priority'         => 'high',
            'status'           => 'in_progress',
            'alert_count'      => 4,
            'total_risk_score' => 88.0,
            'entities'         => [
                ['type' => 'account', 'id' => 'ACC-001'],
            ],
            'findings' => ['Pattern of unusual transactions detected'],
        ]);

        $response = $this->getJson("/api/compliance/cases/{$case->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.case_id', 'CASE-2025-003')
            ->assertJsonPath('data.type', 'fraud')
            ->assertJsonPath('data.total_risk_score', '88.00');
    }

    public function test_can_update_compliance_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'     => 'CASE-2025-004',
            'title'       => 'Initial Investigation',
            'description' => 'Initial description',
            'type'        => 'investigation',
            'priority'    => 'medium',
            'status'      => 'open',
        ]);

        $response = $this->putJson("/api/compliance/cases/{$case->id}", [
            'title'                 => 'Updated Investigation',
            'priority'              => 'high',
            'status'                => 'in_progress',
            'investigation_summary' => 'Additional evidence found',
            'findings'              => ['Finding 1', 'Finding 2'],
            'recommendations'       => ['Recommendation 1'],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Case updated successfully');

        $this->assertDatabaseHas('compliance_cases', [
            'id'       => $case->id,
            'title'    => 'Updated Investigation',
            'priority' => 'high',
            'status'   => 'in_progress',
        ]);
    }

    public function test_can_assign_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-005',
            'title'    => 'Unassigned Case',
            'type'     => 'investigation',
            'priority' => 'high',
            'status'   => 'open',
        ]);

        $assignee = User::factory()->create();

        $response = $this->putJson("/api/compliance/cases/{$case->id}/assign", [
            'user_id' => $assignee->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Case assigned successfully');

        $this->assertDatabaseHas('compliance_cases', [
            'id'          => $case->id,
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_can_add_evidence_to_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-006',
            'title'    => 'Evidence Test Case',
            'type'     => 'investigation',
            'priority' => 'high',
            'status'   => 'in_progress',
        ]);

        $response = $this->postJson("/api/compliance/cases/{$case->id}/evidence", [
            'type'        => 'document',
            'title'       => 'Bank Statement',
            'description' => 'Customer bank statement showing transactions',
            'source'      => 'customer_provided',
            'metadata'    => [
                'date_range' => '2025-01-01 to 2025-01-31',
                'account'    => 'ACC-001',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Evidence added successfully');

        $case->refresh();
        $this->assertCount(1, $case->evidence);
        $this->assertEquals('document', $case->evidence[0]['type']);
    }

    public function test_can_add_note_to_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-007',
            'title'    => 'Note Test Case',
            'type'     => 'investigation',
            'priority' => 'medium',
            'status'   => 'in_progress',
        ]);

        $response = $this->postJson("/api/compliance/cases/{$case->id}/notes", [
            'note' => 'Contacted customer for additional information',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Note added successfully');

        $case->refresh();
        $this->assertCount(1, $case->notes);
        $this->assertEquals('Contacted customer for additional information', $case->notes[0]['note']);
    }

    public function test_can_escalate_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'          => 'CASE-2025-008',
            'title'            => 'Escalation Test Case',
            'type'             => 'investigation',
            'priority'         => 'medium',
            'status'           => 'in_progress',
            'escalation_level' => 0,
        ]);

        $escalateTo = User::factory()->create();

        $response = $this->postJson("/api/compliance/cases/{$case->id}/escalate", [
            'reason'       => 'Requires senior management review',
            'escalate_to'  => $escalateTo->id,
            'new_priority' => 'critical',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Case escalated successfully');

        $this->assertDatabaseHas('compliance_cases', [
            'id'               => $case->id,
            'status'           => 'escalated',
            'priority'         => 'critical',
            'escalation_level' => 1,
        ]);
    }

    public function test_can_get_case_timeline(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'    => 'CASE-2025-009',
            'title'      => 'Timeline Test Case',
            'type'       => 'investigation',
            'priority'   => 'high',
            'status'     => 'in_progress',
            'created_at' => now()->subDays(5),
            'history'    => [
                [
                    'type'      => 'created',
                    'timestamp' => now()->subDays(5)->toIso8601String(),
                    'user_id'   => $this->user->id,
                    'user_name' => $this->user->name,
                    'details'   => 'Case created',
                ],
                [
                    'type'      => 'status_change',
                    'timestamp' => now()->subDays(3)->toIso8601String(),
                    'user_id'   => $this->user->id,
                    'user_name' => $this->user->name,
                    'details'   => 'Status changed from open to in_progress',
                ],
                [
                    'type'      => 'note',
                    'timestamp' => now()->subDay()->toIso8601String(),
                    'user_id'   => $this->user->id,
                    'user_name' => $this->user->name,
                    'content'   => 'Investigation note added',
                ],
            ],
        ]);

        // Link some alerts to the case
        $alert1 = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'linked',
            'title'       => 'Large Transaction Alert',
            'description' => 'Alert 1',
            'case_id'     => $case->id,
            'created_at'  => now()->subDays(4),
        ]);

        $alert2 = ComplianceAlert::factory()->create([
            'type'        => 'suspicious_pattern',
            'severity'    => 'medium',
            'status'      => 'linked',
            'title'       => 'Suspicious Pattern Alert',
            'description' => 'Alert 2',
            'case_id'     => $case->id,
            'created_at'  => now()->subDays(2),
        ]);

        $response = $this->getJson("/api/compliance/cases/{$case->id}/timeline");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'timestamp',
                        'type',
                        'description',
                    ],
                ],
            ]);
    }

    public function test_can_soft_delete_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-010',
            'title'    => 'Delete Test Case',
            'type'     => 'investigation',
            'priority' => 'low',
            'status'   => 'closed',
        ]);

        $response = $this->deleteJson("/api/compliance/cases/{$case->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Case deleted successfully');

        $this->assertSoftDeleted('compliance_cases', [
            'id' => $case->id,
        ]);
    }

    public function test_cannot_delete_active_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-011',
            'title'    => 'Active Case',
            'type'     => 'investigation',
            'priority' => 'high',
            'status'   => 'in_progress',
        ]);

        $response = $this->deleteJson("/api/compliance/cases/{$case->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete active case');

        $this->assertDatabaseHas('compliance_cases', [
            'id'         => $case->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_resolve_case(): void
    {
        $case = ComplianceCase::factory()->create([
            'case_id'  => 'CASE-2025-012',
            'title'    => 'Resolution Test Case',
            'type'     => 'investigation',
            'priority' => 'high',
            'status'   => 'in_progress',
        ]);

        $response = $this->putJson("/api/compliance/cases/{$case->id}", [
            'status'         => 'resolved',
            'closure_reason' => 'false_positive',
            'closure_notes'  => 'Investigation found no compliance violations',
            'actions_taken'  => [
                'Reviewed all transactions',
                'Verified customer identity',
                'Cleared all alerts',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Case updated successfully');

        $this->assertDatabaseHas('compliance_cases', [
            'id'             => $case->id,
            'status'         => 'resolved',
            'closure_reason' => 'false_positive',
        ]);
    }
}
