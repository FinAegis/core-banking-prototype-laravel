<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Account;
use App\Models\FraudCase;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;

class BusinessTeamManagementTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $businessOwner;
    protected User $otherBusinessOwner;
    protected Team $businessTeam;
    protected Team $otherBusinessTeam;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the banking roles migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_06_29_235152_add_banking_roles_and_permissions.php']);
        
        // Create business owner and team
        $this->businessOwner = User::factory()->create();
        $this->businessTeam = Team::factory()->create([
            'user_id' => $this->businessOwner->id,
            'name' => 'Test Business',
            'is_business_organization' => true,
            'max_users' => 5,
            'allowed_roles' => ['compliance_officer', 'accountant'],
        ]);
        $this->businessOwner->current_team_id = $this->businessTeam->id;
        $this->businessOwner->save();
        $this->businessOwner->assignRole('customer_business');
        
        // Create another business for isolation testing
        $this->otherBusinessOwner = User::factory()->create();
        $this->otherBusinessTeam = Team::factory()->create([
            'user_id' => $this->otherBusinessOwner->id,
            'name' => 'Other Business',
            'is_business_organization' => true,
        ]);
        $this->otherBusinessOwner->current_team_id = $this->otherBusinessTeam->id;
        $this->otherBusinessOwner->save();
        $this->otherBusinessOwner->assignRole('customer_business');
    }
    
    #[Test]
    public function business_owner_can_view_team_members_page()
    {
        $response = $this->actingAs($this->businessOwner)
            ->get(route('teams.members.index', $this->businessTeam));
        
        $response->assertStatus(200);
        $response->assertSee('Team Members');
        $response->assertSee('Test Business');
    }
    
    #[Test]
    public function non_business_team_cannot_access_members_page()
    {
        $personalUser = User::factory()->create();
        $personalTeam = Team::factory()->create([
            'user_id' => $personalUser->id,
            'personal_team' => true,
            'is_business_organization' => false,
        ]);
        
        $response = $this->actingAs($personalUser)
            ->get(route('teams.members.index', $personalTeam));
        
        $response->assertStatus(403);
    }
    
    #[Test]
    public function business_owner_can_add_team_member()
    {
        $response = $this->actingAs($this->businessOwner)
            ->post(route('teams.members.store', $this->businessTeam), [
                'name' => 'New Team Member',
                'email' => 'newmember@example.com',
                'password' => 'password123',
                'role' => 'compliance_officer',
            ]);
        
        $response->assertRedirect(route('teams.members.index', $this->businessTeam));
        
        $this->assertDatabaseHas('users', [
            'email' => 'newmember@example.com',
        ]);
        
        $newUser = User::where('email', 'newmember@example.com')->first();
        $this->assertTrue($this->businessTeam->users->contains($newUser));
        $this->assertTrue($newUser->hasRole('compliance_officer'));
        
        $teamRole = $this->businessTeam->getUserTeamRole($newUser);
        $this->assertEquals('compliance_officer', $teamRole->role);
    }
    
    #[Test]
    public function cannot_add_member_beyond_team_limit()
    {
        // Add members up to the limit (minus 1 for the owner who is already a member)
        $currentCount = $this->businessTeam->users()->count();
        for ($i = $currentCount; $i < $this->businessTeam->max_users; $i++) {
            $user = User::factory()->create();
            $this->businessTeam->users()->attach($user);
        }
        
        $response = $this->actingAs($this->businessOwner)
            ->post(route('teams.members.store', $this->businessTeam), [
                'name' => 'Excess Member',
                'email' => 'excess@example.com',
                'password' => 'password123',
                'role' => 'accountant',
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Your team has reached the maximum number of users.');
        
        $this->assertDatabaseMissing('users', [
            'email' => 'excess@example.com',
        ]);
    }
    
    #[Test]
    public function can_only_assign_allowed_roles()
    {
        $response = $this->actingAs($this->businessOwner)
            ->post(route('teams.members.store', $this->businessTeam), [
                'name' => 'New Member',
                'email' => 'member@example.com',
                'password' => 'password123',
                'role' => 'super_admin', // Not in allowed_roles
            ]);
        
        $response->assertSessionHasErrors(['role']);
    }
    
    #[Test]
    public function business_owner_can_update_member_role()
    {
        $member = User::factory()->create();
        $this->businessTeam->users()->attach($member);
        $this->businessTeam->assignUserRole($member, 'accountant');
        $member->assignRole('accountant');
        
        $response = $this->actingAs($this->businessOwner)
            ->put(route('teams.members.update', [$this->businessTeam, $member]), [
                'role' => 'compliance_officer',
            ]);
        
        $response->assertRedirect(route('teams.members.index', $this->businessTeam));
        
        $member->refresh();
        $this->assertTrue($member->hasRole('compliance_officer'));
        $this->assertFalse($member->hasRole('accountant'));
        
        $teamRole = $this->businessTeam->getUserTeamRole($member);
        $this->assertEquals('compliance_officer', $teamRole->role);
    }
    
    #[Test]
    public function cannot_update_team_owner_role()
    {
        $response = $this->actingAs($this->businessOwner)
            ->put(route('teams.members.update', [$this->businessTeam, $this->businessOwner]), [
                'role' => 'accountant',
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot edit the team owner\'s role.');
    }
    
    #[Test]
    public function business_owner_can_remove_team_member()
    {
        $member = User::factory()->create();
        $this->businessTeam->users()->attach($member);
        $member->current_team_id = $this->businessTeam->id;
        $member->save();
        
        $response = $this->actingAs($this->businessOwner)
            ->delete(route('teams.members.destroy', [$this->businessTeam, $member]));
        
        $response->assertRedirect(route('teams.members.index', $this->businessTeam));
        
        $this->assertFalse($this->businessTeam->fresh()->users->contains($member));
        $this->assertNull($member->fresh()->current_team_id);
    }
    
    #[Test]
    public function cannot_remove_team_owner()
    {
        $response = $this->actingAs($this->businessOwner)
            ->delete(route('teams.members.destroy', [$this->businessTeam, $this->businessOwner]));
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot remove the team owner.');
    }
    
    #[Test]
    public function cannot_access_other_teams_members()
    {
        $response = $this->actingAs($this->businessOwner)
            ->get(route('teams.members.index', $this->otherBusinessTeam));
        
        $response->assertStatus(403);
    }
    
    #[Test]
    public function data_isolation_for_accounts()
    {
        // Create accounts for both teams
        $account1 = Account::factory()->create([
            'user_uuid' => $this->businessOwner->uuid,
            'team_id' => $this->businessTeam->id,
        ]);
        
        $account2 = Account::factory()->create([
            'user_uuid' => $this->otherBusinessOwner->uuid,
            'team_id' => $this->otherBusinessTeam->id,
        ]);
        
        // Acting as business owner 1
        $this->actingAs($this->businessOwner);
        $visibleAccounts = Account::all();
        
        $this->assertTrue($visibleAccounts->contains($account1));
        $this->assertFalse($visibleAccounts->contains($account2));
    }
    
    #[Test]
    public function compliance_officer_sees_only_team_fraud_alerts()
    {
        // Create compliance officer in team 1
        $complianceOfficer = User::factory()->create();
        $this->businessTeam->users()->attach($complianceOfficer);
        $complianceOfficer->current_team_id = $this->businessTeam->id;
        $complianceOfficer->save();
        $complianceOfficer->assignRole('compliance_officer');
        
        // Create fraud cases for both teams
        $fraudCase1 = FraudCase::factory()->create([
            'team_id' => $this->businessTeam->id,
        ]);
        
        $fraudCase2 = FraudCase::factory()->create([
            'team_id' => $this->otherBusinessTeam->id,
        ]);
        
        // Test through controller
        $response = $this->actingAs($complianceOfficer)
            ->get(route('fraud.alerts.index'));
        
        $response->assertStatus(200);
        $response->assertSee($fraudCase1->case_number);
        $response->assertDontSee($fraudCase2->case_number);
    }
    
    #[Test]
    public function super_admin_can_see_all_data()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        
        // Create accounts for both teams
        $account1 = Account::factory()->create([
            'user_uuid' => $this->businessOwner->uuid,
            'team_id' => $this->businessTeam->id,
        ]);
        
        $account2 = Account::factory()->create([
            'user_uuid' => $this->otherBusinessOwner->uuid,
            'team_id' => $this->otherBusinessTeam->id,
        ]);
        
        // Acting as super admin (without global scope)
        $this->actingAs($superAdmin);
        $visibleAccounts = Account::allTeams()->get();
        
        $this->assertTrue($visibleAccounts->contains($account1));
        $this->assertTrue($visibleAccounts->contains($account2));
    }
    
    #[Test]
    public function team_member_automatically_associated_with_team()
    {
        $member = User::factory()->create();
        $this->businessTeam->users()->attach($member);
        $member->current_team_id = $this->businessTeam->id;
        $member->save();
        $member->assignRole('accountant');
        
        // Create an account as the team member
        $this->actingAs($member);
        $account = Account::create([
            'uuid' => \Str::uuid(),
            'user_uuid' => $member->uuid,
            'name' => 'Test Account',
        ]);
        
        // Account should automatically have the team_id set
        $this->assertEquals($this->businessTeam->id, $account->team_id);
    }
}