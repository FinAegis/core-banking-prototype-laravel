<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class CustodianIntegrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'bank_admin']);
        Role::create(['name' => 'operations_manager']);
        
        // Create users
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('super_admin');
        
        $this->regularUser = User::factory()->create();
    }

    public function test_unauthorized_user_cannot_access_custodian_integration()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('custodian-integration.index'));
            
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_custodian_integration_index()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('custodian-integration.index'));
            
        $response->assertStatus(200)
            ->assertViewIs('custodian-integration.index')
            ->assertViewHas(['custodians', 'statistics', 'recentActivities']);
    }

    public function test_authorized_user_can_view_custodian_details()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('custodian-integration.show', ['custodianCode' => 'ANCHORAGE']));
            
        $response->assertStatus(200)
            ->assertViewIs('custodian-integration.show')
            ->assertViewHas(['custodian', 'accountBalances', 'transactionHistory', 'systemStatus']);
    }

    public function test_returns_404_for_invalid_custodian()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('custodian-integration.show', ['custodianCode' => 'INVALID']));
            
        $response->assertStatus(404);
    }

    public function test_can_test_custodian_connection()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('custodian-integration.test-connection', ['custodianCode' => 'ANCHORAGE']));
            
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    public function test_can_synchronize_custodian_data()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('custodian-integration.synchronize', ['custodianCode' => 'ANCHORAGE']));
            
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }
}