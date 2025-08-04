<?php

namespace Tests\Feature\Lending;

use App\Domain\Account\Models\Account;
use App\Domain\Lending\Enums\EmploymentStatus;
use App\Domain\Lending\Enums\LoanPurpose;
use App\Domain\Lending\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class LendingControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with personal team
        $this->user = User::factory()->withPersonalTeam()->create();

        // Create account for the user
        $this->account = Account::create([
            'uuid'      => (string) Str::uuid(),
            'user_uuid' => $this->user->uuid,
            'name'      => 'Test Account',
            'balance'   => 5000000, // 50000.00 in cents
        ]);
    }

    #[Test]
    public function test_can_access_lending_dashboard(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('lending.index'));

        $response->assertStatus(200);
        $response->assertViewIs('lending.index');
    }

    #[Test]
    public function test_can_access_loan_application_form(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('lending.apply'));

        $response->assertStatus(200);
        $response->assertViewIs('lending.apply');
    }

    #[Test]
    public function test_can_submit_loan_application(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('lending.apply.submit'), [
                'account_id'          => $this->account->uuid,
                'loan_product'        => 'personal_loan',
                'amount'              => '10000',
                'term_months'         => '12',
                'purpose'             => LoanPurpose::PERSONAL->value,
                'purpose_description' => 'Personal expenses',
                'collateral_type'     => 'none',
                'employment_status'   => EmploymentStatus::EMPLOYED->value,
                'annual_income'       => '60000',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function test_cannot_submit_invalid_loan_application(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('lending.apply.submit'), [
                'account_id'        => 'invalid-uuid', // Invalid UUID
                'loan_product'      => '', // Missing loan product
                'amount'            => '-1000', // Invalid negative amount
                'term_months'       => '999', // Too long term
                'purpose'           => 'invalid_purpose', // Invalid purpose
                'collateral_type'   => 'invalid_type', // Invalid collateral type
                'employment_status' => 'invalid_status', // Invalid employment status
                'annual_income'     => '-1000', // Negative income
            ]);

        $response->assertSessionHasErrors(['account_id', 'loan_product', 'amount', 'term_months', 'purpose', 'collateral_type', 'employment_status', 'annual_income']);
    }

    #[Test]
    public function test_guest_cannot_access_lending(): void
    {
        $response = $this->get(route('lending.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('lending.apply'));
        $response->assertRedirect(route('login'));
    }
}
