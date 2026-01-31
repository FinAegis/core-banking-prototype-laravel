<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ComplianceControllerTest extends ControllerTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_dashboard_returns_compliance_metrics(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'overall_compliance_score',
                    'kyc_completion_rate',
                    'pending_reviews',
                    'active_violations',
                    'last_audit_date',
                    'next_audit_date',
                ],
            ])
            ->assertJson([
                'status' => 'success',
            ]);
    }

    #[Test]
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/dashboard');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_violations_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/violations');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_violations_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/violations');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_violation_details_returns_null(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/violations/123');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => null,
            ]);
    }

    #[Test]
    public function test_get_violation_details_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/violations/123');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_resolve_violation_returns_success(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/compliance/violations/123/resolve');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Violation resolved successfully',
            ]);
    }

    #[Test]
    public function test_resolve_violation_requires_authentication(): void
    {
        $response = $this->postJson('/api/compliance/violations/123/resolve');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_compliance_rules_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/rules');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_compliance_rules_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/rules');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_rules_by_jurisdiction_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/rules/EU');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_rules_by_jurisdiction_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/rules/EU');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_compliance_checks_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/checks');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_compliance_checks_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/checks');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_run_compliance_check_returns_success(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/compliance/checks/run', [
            'check_type' => 'aml',
            'scope'      => 'all_accounts',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Compliance check initiated',
            ]);
    }

    #[Test]
    public function test_run_compliance_check_requires_authentication(): void
    {
        $response = $this->postJson('/api/compliance/checks/run');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_certifications_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/certifications');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_certifications_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/certifications');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_renew_certification_returns_success(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson('/api/compliance/certifications/renew', [
            'certification_id' => 'cert-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Certification renewal initiated',
            ]);
    }

    #[Test]
    public function test_renew_certification_requires_authentication(): void
    {
        $response = $this->postJson('/api/compliance/certifications/renew');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_get_policies_returns_empty_list(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson('/api/compliance/policies');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [],
            ]);
    }

    #[Test]
    public function test_get_policies_requires_authentication(): void
    {
        $response = $this->getJson('/api/compliance/policies');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_update_policy_returns_success(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->putJson('/api/compliance/policies/123', [
            'name'    => 'Updated Policy',
            'content' => 'Updated policy content',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Policy updated successfully',
            ]);
    }

    #[Test]
    public function test_update_policy_requires_authentication(): void
    {
        $response = $this->putJson('/api/compliance/policies/123');

        $response->assertStatus(401);
    }
}
