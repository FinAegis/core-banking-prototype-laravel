<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class AuditControllerTest extends ControllerTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_get_audit_logs_returns_empty_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/audit/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['total'],
            ])
            ->assertJson([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
    }

    #[Test]
    public function test_get_audit_logs_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/logs');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_export_audit_logs_returns_export_info(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/audit/logs/export');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'export_id',
                    'status',
                ],
            ])
            ->assertJsonPath('data.status', 'processing');
    }

    #[Test]
    public function test_export_audit_logs_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/logs/export');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_audit_events_returns_empty_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/audit/events');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_get_audit_events_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/events');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_event_details_returns_event_id(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $eventId = 'event-123';
        $response = $this->getJson("/api/audit/events/{$eventId}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => ['id' => $eventId],
            ]);
    }

    #[Test]
    public function test_get_event_details_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/events/event-123');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_audit_reports_returns_empty_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/audit/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['total'],
            ])
            ->assertJson([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
    }

    #[Test]
    public function test_get_audit_reports_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/reports');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_generate_audit_report_creates_report(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/audit/reports/generate', [
            'type'   => 'monthly',
            'period' => '2024-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['report_id'],
            ])
            ->assertJsonPath('message', 'Audit report generation initiated');
    }

    #[Test]
    public function test_generate_audit_report_requires_authentication(): void
    {
        $response = $this->postJson('/api/audit/reports/generate', [
            'type' => 'monthly',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_entity_audit_trail_returns_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $entityType = 'account';
        $entityId = 'acc-123';

        $response = $this->getJson("/api/audit/trail/{$entityType}/{$entityId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'entity_type',
                    'entity_id',
                ],
            ])
            ->assertJson([
                'data' => [],
                'meta' => [
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                ],
            ]);
    }

    #[Test]
    public function test_get_entity_audit_trail_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/trail/account/acc-123');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_user_activity_returns_data(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $userId = 'user-456';

        $response = $this->getJson("/api/audit/users/{$userId}/activity");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['user_id'],
            ])
            ->assertJson([
                'data' => [],
                'meta' => ['user_id' => $userId],
            ]);
    }

    #[Test]
    public function test_get_user_activity_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/users/user-456/activity');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_search_audit_logs_returns_empty_results(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/audit/search?query=test&event_type=login');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['total'],
            ])
            ->assertJson([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
    }

    #[Test]
    public function test_search_audit_logs_requires_authentication(): void
    {
        $response = $this->getJson('/api/audit/search?query=test');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_archive_audit_logs_returns_success(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/audit/archive', [
            'before_date' => '2023-01-01',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Audit logs archived',
                'data'    => [],
            ]);
    }

    #[Test]
    public function test_archive_audit_logs_requires_authentication(): void
    {
        $response = $this->postJson('/api/audit/archive', [
            'before_date' => '2023-01-01',
        ]);

        $response->assertStatus(401);
    }
}
