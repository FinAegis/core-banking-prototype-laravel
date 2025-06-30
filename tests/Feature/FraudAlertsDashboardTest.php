<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Account;
use App\Models\FraudCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;

class FraudAlertsDashboardTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the banking roles migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_06_29_235152_add_banking_roles_and_permissions.php']);
    }
    
    #[Test]
    public function customers_can_only_see_their_own_fraud_alerts()
    {
        // Create personal team for customer
        $customer = User::factory()->create();
        $personalTeam = Team::factory()->create([
            'user_id' => $customer->id,
            'personal_team' => true,
        ]);
        $customer->current_team_id = $personalTeam->id;
        $customer->save();
        $customer->assignRole('customer_private');
        
        $customerAccount = Account::factory()->create([
            'user_uuid' => $customer->uuid,
            'team_id' => $personalTeam->id
        ]);
        $otherAccount = Account::factory()->create();
        
        // Create fraud cases
        $customerFraud = FraudCase::factory()->create([
            'subject_account_uuid' => $customerAccount->uuid,
            'team_id' => $personalTeam->id
        ]);
        
        $otherFraud = FraudCase::factory()->create([
            'subject_account_uuid' => $otherAccount->uuid,
        ]);
        
        $response = $this->actingAs($customer)->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee($customerFraud->case_number);
        $response->assertDontSee($otherFraud->case_number);
    }
    
    #[Test]
    public function compliance_officers_can_see_all_fraud_alerts_in_their_team()
    {
        $team = Team::factory()->create(['is_business_organization' => true]);
        
        $complianceOfficer = User::factory()->create();
        $complianceOfficer->current_team_id = $team->id;
        $complianceOfficer->save();
        $complianceOfficer->assignRole('compliance_officer');
        
        // Create fraud cases
        $teamFraud = FraudCase::factory()->create(['team_id' => $team->id]);
        $otherTeamFraud = FraudCase::factory()->create(['team_id' => Team::factory()->create()->id]);
        
        $response = $this->actingAs($complianceOfficer)->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee($teamFraud->case_number);
        $response->assertDontSee($otherTeamFraud->case_number);
    }
    
    #[Test]
    public function fraud_alerts_can_be_filtered_by_status()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $pendingCase = FraudCase::factory()->create(['status' => 'pending', 'team_id' => $team->id]);
        $confirmedCase = FraudCase::factory()->create(['status' => 'confirmed', 'team_id' => $team->id]);
        
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['status' => 'pending']));
        
        $response->assertStatus(200);
        $response->assertSee($pendingCase->case_number);
        $response->assertDontSee($confirmedCase->case_number);
    }
    
    #[Test]
    public function fraud_alerts_can_be_filtered_by_risk_score()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $highRiskCase = FraudCase::factory()->create(['risk_score' => 85, 'team_id' => $team->id]);
        $lowRiskCase = FraudCase::factory()->create(['risk_score' => 20, 'team_id' => $team->id]);
        
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['risk_score_min' => 80]));
        
        $response->assertStatus(200);
        $response->assertSee($highRiskCase->case_number);
        $response->assertDontSee($lowRiskCase->case_number);
    }
    
    #[Test]
    public function fraud_alerts_can_be_searched()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $case1 = FraudCase::factory()->create([
            'description' => 'Suspicious wire transfer',
            'team_id' => $team->id
        ]);
        
        $case2 = FraudCase::factory()->create([
            'description' => 'Normal transaction',
            'team_id' => $team->id
        ]);
        
        // Search by case number
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['search' => substr($case1->case_number, -5)]));
        
        $response->assertStatus(200);
        $response->assertSee($case1->case_number);
        $response->assertDontSee($case2->case_number);
        
        // Search by description
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['search' => 'wire transfer']));
        
        $response->assertStatus(200);
        $response->assertSee($case1->case_number);
        $response->assertDontSee($case2->case_number);
    }
    
    #[Test]
    public function fraud_alerts_show_analytics_charts()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        // Create some fraud cases
        FraudCase::factory()->count(5)->create(['team_id' => $team->id]);
        
        $response = $this->actingAs($user)->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Fraud Detection Trend');
        $response->assertSee('Risk Score Distribution');
        $response->assertSee('Fraud Type Distribution');
        $response->assertSee('trendChart');
        $response->assertSee('riskChart');
        $response->assertSee('typeChart');
    }
    
    #[Test]
    public function authorized_users_can_export_fraud_data()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('compliance_officer');
        
        FraudCase::factory()->count(3)->create(['team_id' => $team->id]);
        
        $response = $this->actingAs($user)->get(route('fraud.alerts.export'));
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="fraud-cases-' . date('Y-m-d') . '.csv"');
    }
    
    #[Test]
    public function unauthorized_users_cannot_export_fraud_data()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('customer_private');
        
        $response = $this->actingAs($user)->get(route('fraud.alerts.export'));
        
        $response->assertStatus(403);
    }
    
    #[Test]
    public function fraud_case_details_can_be_viewed()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $fraudCase = FraudCase::factory()->create([
            'type' => 'money_laundering',
            'status' => 'investigating',
            'risk_score' => 75,
            'amount' => 50000,
            'team_id' => $team->id
        ]);
        
        $response = $this->actingAs($user)->get(route('fraud.alerts.show', $fraudCase));
        
        $response->assertStatus(200);
        $response->assertSee($fraudCase->case_number);
        $response->assertSee('Money laundering');
        $response->assertSee('Under Investigation');
        $response->assertSee('75');
    }
    
    #[Test]
    public function fraud_case_status_can_be_updated()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $fraudCase = FraudCase::factory()->create(['status' => 'pending', 'team_id' => $team->id]);
        
        $response = $this->actingAs($user)
            ->patch(route('fraud.alerts.update-status', $fraudCase), [
                'status' => 'investigating',
                'notes' => 'Starting investigation'
            ]);
        
        $response->assertRedirect(route('fraud.alerts.show', $fraudCase));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('fraud_cases', [
            'id' => $fraudCase->id,
            'status' => 'investigating',
        ]);
    }
    
    #[Test]
    public function fraud_alerts_are_paginated()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        // Create 25 fraud cases
        FraudCase::factory()->count(25)->create(['team_id' => $team->id]);
        
        $response = $this->actingAs($user)->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee('Showing 1 to 20 of 25 results');
        $response->assertSee('Next');
    }
    
    #[Test]
    public function fraud_alerts_can_be_sorted()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('risk_manager');
        
        $case1 = FraudCase::factory()->create(['risk_score' => 90, 'team_id' => $team->id]);
        $case2 = FraudCase::factory()->create(['risk_score' => 30, 'team_id' => $team->id]);
        
        // Sort by risk score descending
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['sort' => 'risk_score', 'direction' => 'desc']));
        
        $response->assertStatus(200);
        $response->assertSeeInOrder([$case1->case_number, $case2->case_number]);
        
        // Sort by risk score ascending
        $response = $this->actingAs($user)
            ->get(route('fraud.alerts.index', ['sort' => 'risk_score', 'direction' => 'asc']));
        
        $response->assertStatus(200);
        $response->assertSeeInOrder([$case2->case_number, $case1->case_number]);
    }
    
    #[Test]
    public function super_admin_can_see_all_teams_fraud_alerts()
    {
        $superAdmin = User::factory()->create();
        $adminTeam = Team::factory()->create(['user_id' => $superAdmin->id]);
        $superAdmin->current_team_id = $adminTeam->id;
        $superAdmin->save();
        $superAdmin->assignRole('super_admin');
        
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $fraud1 = FraudCase::factory()->create(['team_id' => $team1->id]);
        $fraud2 = FraudCase::factory()->create(['team_id' => $team2->id]);
        
        $response = $this->actingAs($superAdmin)->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee($fraud1->case_number);
        $response->assertSee($fraud2->case_number);
    }
}