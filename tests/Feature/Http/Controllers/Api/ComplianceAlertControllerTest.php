<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceAlertControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_compliance_alerts(): void
    {
        ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-001',
            'risk_score'  => 85.0,
        ]);

        ComplianceAlert::factory()->create([
            'type'        => 'suspicious_pattern',
            'severity'    => 'medium',
            'status'      => 'investigating',
            'description' => 'Suspicious pattern detected',
            'entity_type' => 'account',
            'entity_id'   => 'ACC-001',
            'risk_score'  => 65.0,
        ]);

        $response = $this->getJson('/api/compliance/alerts');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'severity',
                        'status',
                        'description',
                        'risk_score',
                    ],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_can_filter_alerts_by_status(): void
    {
        ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'risk_score'  => 85.0,
        ]);

        ComplianceAlert::factory()->create([
            'type'        => 'suspicious_pattern',
            'severity'    => 'medium',
            'status'      => 'resolved',
            'description' => 'Pattern resolved',
            'risk_score'  => 45.0,
        ]);

        $response = $this->getJson('/api/compliance/alerts?status=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open');
    }

    public function test_can_create_compliance_alert(): void
    {
        $response = $this->postJson('/api/compliance/alerts', [
            'type'        => 'fraud_detection',
            'severity'    => 'critical',
            'description' => 'Potential fraud detected',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-123',
            'metadata'    => [
                'amount'  => 50000,
                'pattern' => 'rapid_transfers',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Alert created successfully')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'severity',
                    'status',
                    'description',
                ],
            ]);

        $this->assertDatabaseHas('compliance_alerts', [
            'type'        => 'fraud_detection',
            'severity'    => 'critical',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-123',
        ]);
    }

    public function test_can_show_alert_details(): void
    {
        $alert = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-001',
            'risk_score'  => 85.0,
            'metadata'    => ['amount' => 25000],
        ]);

        $response = $this->getJson("/api/compliance/alerts/{$alert->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $alert->id)
            ->assertJsonPath('data.type', 'large_transaction')
            ->assertJsonPath('data.risk_score', '85.00');
    }

    public function test_can_update_alert_status(): void
    {
        $alert = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'risk_score'  => 85.0,
        ]);

        $response = $this->putJson("/api/compliance/alerts/{$alert->id}/status", [
            'status' => 'investigating',
            'notes'  => 'Started investigation',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Alert status updated successfully');

        $this->assertDatabaseHas('compliance_alerts', [
            'id'     => $alert->id,
            'status' => 'investigating',
        ]);
    }

    public function test_can_assign_alert(): void
    {
        $alert = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'risk_score'  => 85.0,
        ]);

        $assignee = User::factory()->create();

        $response = $this->putJson("/api/compliance/alerts/{$alert->id}/assign", [
            'user_id' => $assignee->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Alert assigned successfully');

        $this->assertDatabaseHas('compliance_alerts', [
            'id'          => $alert->id,
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_can_add_note_to_alert(): void
    {
        $alert = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'risk_score'  => 85.0,
        ]);

        $response = $this->postJson("/api/compliance/alerts/{$alert->id}/notes", [
            'note' => 'Contacted customer for verification',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Investigation note added successfully');

        $alert->refresh();
        $this->assertCount(1, $alert->notes);
        $this->assertEquals('Contacted customer for verification', $alert->notes[0]['note']);
    }

    public function test_can_link_alerts(): void
    {
        $alert1 = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction 1',
            'risk_score'  => 85.0,
        ]);

        $alert2 = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction 2',
            'risk_score'  => 90.0,
        ]);

        $response = $this->postJson('/api/compliance/alerts/link', [
            'alert_ids'         => [$alert1->alert_id, $alert2->alert_id],
            'relationship_type' => 'related',
            'notes'             => 'Linked due to similar pattern',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Alerts linked successfully');
    }

    public function test_can_create_case_from_alerts(): void
    {
        $alert1 = ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction detected',
            'risk_score'  => 85.0,
        ]);

        $alert2 = ComplianceAlert::factory()->create([
            'type'        => 'suspicious_pattern',
            'severity'    => 'critical',
            'status'      => 'open',
            'description' => 'Suspicious pattern detected',
            'risk_score'  => 95.0,
        ]);

        $response = $this->postJson('/api/compliance/alerts/create-case', [
            'alert_ids'   => [$alert1->alert_id, $alert2->alert_id],
            'title'       => 'High Risk Activity Investigation',
            'description' => 'Multiple high-risk alerts detected',
            'type'        => 'investigation',
            'priority'    => 'critical',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Case created successfully')
            ->assertJsonStructure([
                'data' => [
                    'case' => [
                        'id',
                        'case_id',
                        'title',
                        'alert_count',
                        'total_risk_score',
                    ],
                    'linked_alerts',
                ],
            ]);

        $this->assertDatabaseHas('compliance_cases', [
            'title'       => 'High Risk Activity Investigation',
            'type'        => 'investigation',
            'priority'    => 'critical',
            'alert_count' => 2,
        ]);
    }

    public function test_can_get_alert_statistics(): void
    {
        // Create various alerts
        ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large transaction',
            'risk_score'  => 85.0,
        ]);

        ComplianceAlert::factory()->create([
            'type'        => 'fraud_detection',
            'severity'    => 'critical',
            'status'      => 'open',
            'description' => 'Fraud detected',
            'risk_score'  => 95.0,
        ]);

        ComplianceAlert::factory()->create([
            'type'        => 'suspicious_pattern',
            'severity'    => 'medium',
            'status'      => 'resolved',
            'description' => 'Pattern resolved',
            'risk_score'  => 45.0,
        ]);

        $response = $this->getJson('/api/compliance/alerts/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_alerts',
                    'by_status',
                    'by_severity',
                    'by_type',
                    'average_risk_score',
                    'high_risk_count',
                ],
            ]);
    }

    public function test_can_get_alert_trends(): void
    {
        $response = $this->getJson('/api/compliance/alerts/trends?period=7d');

        // Debug output
        if ($response->status() !== 200) {
            dump('Status: ' . $response->status());
            dump('Body: ' . $response->content());
        }

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'trends' => [
                        '*' => [
                            'date',
                            'count',
                            'severity_breakdown',
                            'average_risk_score',
                        ],
                    ],
                    'comparison',
                ],
            ]);
    }

    public function test_can_search_alerts(): void
    {
        ComplianceAlert::factory()->create([
            'type'        => 'large_transaction',
            'severity'    => 'high',
            'status'      => 'open',
            'description' => 'Large wire transfer to offshore account',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-001',
            'risk_score'  => 85.0,
        ]);

        ComplianceAlert::factory()->create([
            'type'        => 'fraud_detection',
            'severity'    => 'critical',
            'status'      => 'open',
            'description' => 'Credit card fraud detected',
            'entity_type' => 'transaction',
            'entity_id'   => 'TXN-002',
            'risk_score'  => 95.0,
        ]);

        $response = $this->postJson('/api/compliance/alerts/search', [
            'query'          => 'offshore',
            'entity_type'    => 'transaction',
            'min_risk_score' => 80,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Large wire transfer to offshore account');
    }
}
