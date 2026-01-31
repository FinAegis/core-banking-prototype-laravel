<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class FraudDetectionControllerTest extends ControllerTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_dashboard_returns_fraud_detection_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ])
            ->assertJson([
                'message' => 'Fraud detection dashboard endpoint',
                'data'    => [],
            ]);
    }

    #[Test]
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/dashboard');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_alerts_returns_paginated_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                ],
            ])
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }

    #[Test]
    public function test_get_alerts_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/alerts');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_alert_details_returns_specific_alert(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $alertId = 'alert-123';
        $response = $this->getJson("/api/fraud/alerts/{$alertId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $alertId,
                ],
            ]);
    }

    #[Test]
    public function test_get_alert_details_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/alerts/alert-123');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_acknowledge_alert_updates_alert_status(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $alertId = 'alert-123';
        $response = $this->postJson("/api/fraud/alerts/{$alertId}/acknowledge");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Alert acknowledged',
                'data'    => [
                    'id' => $alertId,
                ],
            ]);
    }

    #[Test]
    public function test_acknowledge_alert_requires_authentication(): void
    {
        $response = $this->postJson('/api/fraud/alerts/alert-123/acknowledge');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_investigate_alert_starts_investigation(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $alertId = 'alert-123';
        $response = $this->postJson("/api/fraud/alerts/{$alertId}/investigate");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Alert investigation started',
                'data'    => [
                    'id' => $alertId,
                ],
            ]);
    }

    #[Test]
    public function test_investigate_alert_requires_authentication(): void
    {
        $response = $this->postJson('/api/fraud/alerts/alert-123/investigate');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_statistics_returns_fraud_metrics(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ])
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_get_statistics_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/statistics');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_patterns_returns_fraud_patterns(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/patterns');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ])
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_get_patterns_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/patterns');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_cases_returns_paginated_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/cases');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                ],
            ])
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }

    #[Test]
    public function test_get_cases_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/cases');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_case_details_returns_specific_case(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $caseId = 'case-456';
        $response = $this->getJson("/api/fraud/cases/{$caseId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $caseId,
                ],
            ]);
    }

    #[Test]
    public function test_get_case_details_requires_authentication(): void
    {
        $response = $this->getJson('/api/fraud/cases/case-456');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_update_case_modifies_case_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $caseId = 'case-456';
        $response = $this->putJson("/api/fraud/cases/{$caseId}", [
            'status'     => 'closed',
            'resolution' => 'false_positive',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Case updated',
                'data'    => [
                    'id' => $caseId,
                ],
            ]);
    }

    #[Test]
    public function test_update_case_requires_authentication(): void
    {
        $response = $this->putJson('/api/fraud/cases/case-456', [
            'status' => 'closed',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_dashboard_with_filters(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/dashboard?period=7d&severity=high');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fraud detection dashboard endpoint',
                'data'    => [],
            ]);
    }

    #[Test]
    public function test_alerts_with_pagination(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/alerts?page=2&per_page=20');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                ],
            ]);
    }

    #[Test]
    public function test_cases_with_status_filter(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/cases?status=open&assignee=me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                ],
            ]);
    }

    #[Test]
    public function test_statistics_with_date_range(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/statistics?start_date=2024-01-01&end_date=2024-01-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    #[Test]
    public function test_patterns_with_type_filter(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/fraud/patterns?type=transaction&risk_level=high');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);
    }

    #[Test]
    public function test_acknowledge_alert_with_notes(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $alertId = 'alert-789';
        $response = $this->postJson("/api/fraud/alerts/{$alertId}/acknowledge", [
            'notes'           => 'Reviewed and confirmed as false positive',
            'acknowledged_by' => $this->user->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Alert acknowledged',
                'data'    => [
                    'id' => $alertId,
                ],
            ]);
    }

    #[Test]
    public function test_investigate_alert_with_assignment(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $alertId = 'alert-999';
        $response = $this->postJson("/api/fraud/alerts/{$alertId}/investigate", [
            'assigned_to'   => $this->user->id,
            'priority'      => 'high',
            'initial_notes' => 'Suspicious pattern detected',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Alert investigation started',
                'data'    => [
                    'id' => $alertId,
                ],
            ]);
    }

    #[Test]
    public function test_update_case_with_full_details(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $caseId = 'case-111';
        $response = $this->putJson("/api/fraud/cases/{$caseId}", [
            'status'        => 'resolved',
            'resolution'    => 'fraud_confirmed',
            'actions_taken' => [
                'account_frozen'       => true,
                'authorities_notified' => true,
                'funds_recovered'      => 5000.00,
            ],
            'final_notes' => 'Fraudulent activity confirmed and resolved',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Case updated',
                'data'    => [
                    'id' => $caseId,
                ],
            ]);
    }
}
