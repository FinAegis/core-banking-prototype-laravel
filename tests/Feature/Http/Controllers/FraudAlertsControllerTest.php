<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\FraudCase;
use App\Models\Account;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FraudAlertsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $staffUser;
    protected User $superAdmin;
    protected Account $customerAccount;
    protected FraudCase $customerFraudCase;
    protected FraudCase $otherFraudCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        Permission::create(['name' => 'view_fraud_alerts']);
        Permission::create(['name' => 'manage_fraud_cases']);
        Permission::create(['name' => 'export_fraud_data']);
        
        // Create roles
        $customerRole = Role::create(['name' => 'customer_private']);
        $staffRole = Role::create(['name' => 'staff']);
        $staffRole->givePermissionTo(['view_fraud_alerts', 'manage_fraud_cases', 'export_fraud_data']);
        
        $superAdminRole = Role::create(['name' => 'super_admin']);
        $superAdminRole->givePermissionTo(['view_fraud_alerts', 'manage_fraud_cases', 'export_fraud_data']);
        
        // Create users
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer_private');
        
        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');
        
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
        
        // Create account and fraud cases
        $this->customerAccount = Account::factory()->create([
            'user_uuid' => $this->customer->uuid,
        ]);
        
        $this->customerFraudCase = FraudCase::factory()->create([
            'subject_type' => Account::class,
            'subject_id' => $this->customerAccount->id,
            'status' => 'pending',
            'type' => 'unauthorized_transaction',
            'severity' => 'high',
            'risk_score' => 85,
        ]);
        
        $otherAccount = Account::factory()->create();
        $this->otherFraudCase = FraudCase::factory()->create([
            'subject_type' => Account::class,
            'subject_id' => $otherAccount->id,
        ]);
    }

    /** @test */
    public function customer_can_view_own_fraud_alerts()
    {
        $response = $this->actingAs($this->customer)
            ->get(route('fraud.alerts.index'));

        $response->assertStatus(200);
        $response->assertViewIs('fraud.alerts.index');
        $response->assertViewHas('fraudCases');
        
        $fraudCases = $response->viewData('fraudCases');
        $this->assertEquals(1, $fraudCases->total());
        $this->assertEquals($this->customerFraudCase->id, $fraudCases->first()->id);
    }

    /** @test */
    public function customer_cannot_view_others_fraud_alerts()
    {
        $response = $this->actingAs($this->customer)
            ->get(route('fraud.alerts.index'));

        $fraudCases = $response->viewData('fraudCases');
        $this->assertFalse($fraudCases->contains('id', $this->otherFraudCase->id));
    }

    /** @test */
    public function staff_can_view_all_fraud_alerts()
    {
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index'));

        $response->assertStatus(200);
        $fraudCases = $response->viewData('fraudCases');
        $this->assertGreaterThanOrEqual(2, $fraudCases->total());
    }

    /** @test */
    public function it_filters_fraud_cases_by_status()
    {
        FraudCase::factory()->create(['status' => 'investigating']);
        FraudCase::factory()->create(['status' => 'resolved']);
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index', ['status' => 'pending']));

        $fraudCases = $response->viewData('fraudCases');
        foreach ($fraudCases as $case) {
            $this->assertEquals('pending', $case->status);
        }
    }

    /** @test */
    public function it_filters_fraud_cases_by_type()
    {
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index', ['type' => 'unauthorized_transaction']));

        $fraudCases = $response->viewData('fraudCases');
        foreach ($fraudCases as $case) {
            $this->assertEquals('unauthorized_transaction', $case->type);
        }
    }

    /** @test */
    public function it_filters_by_risk_score()
    {
        FraudCase::factory()->create(['risk_score' => 20]);
        FraudCase::factory()->create(['risk_score' => 50]);
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index', ['risk_score_min' => 60]));

        $fraudCases = $response->viewData('fraudCases');
        foreach ($fraudCases as $case) {
            $this->assertGreaterThanOrEqual(60, $case->risk_score);
        }
    }

    /** @test */
    public function it_searches_fraud_cases()
    {
        $searchableCase = FraudCase::factory()->create([
            'case_number' => 'FRAUD-12345',
            'description' => 'Suspicious activity detected',
        ]);
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index', ['search' => 'FRAUD-12345']));

        $fraudCases = $response->viewData('fraudCases');
        $this->assertTrue($fraudCases->contains('id', $searchableCase->id));
    }

    /** @test */
    public function it_shows_statistics()
    {
        FraudCase::factory()->count(5)->create(['status' => 'pending']);
        FraudCase::factory()->count(3)->create(['status' => 'confirmed']);
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index'));

        $response->assertViewHas('stats');
        $stats = $response->viewData('stats');
        
        $this->assertArrayHasKey('total_cases', $stats);
        $this->assertArrayHasKey('pending_cases', $stats);
        $this->assertArrayHasKey('confirmed_cases', $stats);
    }

    /** @test */
    public function customer_can_view_own_fraud_case_details()
    {
        $response = $this->actingAs($this->customer)
            ->get(route('fraud.alerts.show', $this->customerFraudCase));

        $response->assertStatus(200);
        $response->assertViewIs('fraud.alerts.show');
        $response->assertViewHas('fraudCase', $this->customerFraudCase);
    }

    /** @test */
    public function customer_cannot_view_others_fraud_case_details()
    {
        $response = $this->actingAs($this->customer)
            ->get(route('fraud.alerts.show', $this->otherFraudCase));

        $response->assertStatus(403);
    }

    /** @test */
    public function staff_can_update_fraud_case_status()
    {
        $response = $this->actingAs($this->staffUser)
            ->put(route('fraud.alerts.update-status', $this->customerFraudCase), [
                'status' => 'investigating',
                'notes' => 'Looking into this case',
            ]);

        $response->assertRedirect(route('fraud.alerts.show', $this->customerFraudCase));
        $response->assertSessionHas('success');
        
        $this->customerFraudCase->refresh();
        $this->assertEquals('investigating', $this->customerFraudCase->status);
        $this->assertEquals('Looking into this case', $this->customerFraudCase->investigator_notes);
        $this->assertEquals($this->staffUser->id, $this->customerFraudCase->investigated_by);
    }

    /** @test */
    public function customer_cannot_update_fraud_case_status()
    {
        $response = $this->actingAs($this->customer)
            ->put(route('fraud.alerts.update-status', $this->customerFraudCase), [
                'status' => 'resolved',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_status_update()
    {
        $response = $this->actingAs($this->staffUser)
            ->put(route('fraud.alerts.update-status', $this->customerFraudCase), [
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function staff_can_export_fraud_cases()
    {
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
        $response->assertHeader('Content-Disposition');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('Case Number', $content);
        $this->assertStringContainsString('Type', $content);
        $this->assertStringContainsString('Status', $content);
    }

    /** @test */
    public function customer_cannot_export_fraud_cases()
    {
        $response = $this->actingAs($this->customer)
            ->get(route('fraud.alerts.export'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_includes_trend_data()
    {
        // Create fraud cases over multiple days
        $dates = [
            now()->subDays(5),
            now()->subDays(3),
            now()->subDays(1),
            now(),
        ];
        
        foreach ($dates as $date) {
            FraudCase::factory()->create(['detected_at' => $date]);
        }
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index'));

        $response->assertViewHas('trendData');
        $trendData = $response->viewData('trendData');
        
        $this->assertIsArray($trendData);
        $this->assertCount(30, $trendData); // 30 days of data
        $this->assertArrayHasKey('date', $trendData[0]);
        $this->assertArrayHasKey('count', $trendData[0]);
    }

    /** @test */
    public function it_includes_risk_distribution()
    {
        FraudCase::factory()->create(['risk_score' => 10]); // low
        FraudCase::factory()->create(['risk_score' => 45]); // medium
        FraudCase::factory()->create(['risk_score' => 70]); // high
        FraudCase::factory()->create(['risk_score' => 90]); // critical
        
        $response = $this->actingAs($this->staffUser)
            ->get(route('fraud.alerts.index'));

        $response->assertViewHas('riskDistribution');
        $riskDistribution = $response->viewData('riskDistribution');
        
        $this->assertArrayHasKey('low', $riskDistribution);
        $this->assertArrayHasKey('medium', $riskDistribution);
        $this->assertArrayHasKey('high', $riskDistribution);
        $this->assertArrayHasKey('critical', $riskDistribution);
    }
}