<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CgoInvestmentResource;
use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\FilamentTestCase;

class CgoInvestmentResourceTest extends FilamentTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup if needed
    }

    public function test_can_list_cgo_investments()
    {
        $investments = CgoInvestment::factory()->count(5)->create();

        Livewire::test(CgoInvestmentResource\Pages\ListCgoInvestments::class)
            ->assertCanSeeTableRecords($investments);
    }

    public function test_can_view_cgo_investment()
    {
        $investment = CgoInvestment::factory()->create();

        Livewire::test(CgoInvestmentResource\Pages\ViewCgoInvestment::class, [
            'record' => $investment->getRouteKey(),
        ])
            ->assertFormSet([
                'user_id' => $investment->user_id,
                'amount' => $investment->amount,
                'tier' => $investment->tier,
                'status' => $investment->status,
                'payment_method' => $investment->payment_method,
            ]);
    }

    public function test_can_edit_cgo_investment()
    {
        $investment = CgoInvestment::factory()->create([
            'status' => 'pending',
        ]);

        Livewire::test(CgoInvestmentResource\Pages\EditCgoInvestment::class, [
            'record' => $investment->getRouteKey(),
        ])
            ->fillForm([
                'status' => 'confirmed',
                'payment_status' => 'completed',
                'payment_completed_at' => now(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cgo_investments', [
            'id' => $investment->id,
            'status' => 'confirmed',
            'payment_status' => 'completed',
        ]);
    }

    public function test_can_filter_investments_by_status()
    {
        CgoInvestment::factory()->create(['status' => 'pending']);
        CgoInvestment::factory()->create(['status' => 'confirmed']);
        CgoInvestment::factory()->create(['status' => 'cancelled']);

        Livewire::test(CgoInvestmentResource\Pages\ListCgoInvestments::class)
            ->assertCanSeeTableRecords(CgoInvestment::all())
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords(CgoInvestment::where('status', 'pending')->get())
            ->assertCanNotSeeTableRecords(CgoInvestment::where('status', '!=', 'pending')->get());
    }

    public function test_can_filter_investments_by_payment_method()
    {
        CgoInvestment::factory()->create(['payment_method' => 'stripe']);
        CgoInvestment::factory()->create(['payment_method' => 'bank_transfer']);
        CgoInvestment::factory()->create(['payment_method' => 'crypto']);

        Livewire::test(CgoInvestmentResource\Pages\ListCgoInvestments::class)
            ->assertCanSeeTableRecords(CgoInvestment::all())
            ->filterTable('payment_method', 'crypto')
            ->assertCanSeeTableRecords(CgoInvestment::where('payment_method', 'crypto')->get())
            ->assertCanNotSeeTableRecords(CgoInvestment::where('payment_method', '!=', 'crypto')->get());
    }

    public function test_can_filter_investments_by_tier()
    {
        CgoInvestment::factory()->create(['tier' => 'bronze']);
        CgoInvestment::factory()->create(['tier' => 'silver']);
        CgoInvestment::factory()->create(['tier' => 'gold']);

        Livewire::test(CgoInvestmentResource\Pages\ListCgoInvestments::class)
            ->assertCanSeeTableRecords(CgoInvestment::all())
            ->filterTable('tier', 'gold')
            ->assertCanSeeTableRecords(CgoInvestment::where('tier', 'gold')->get())
            ->assertCanNotSeeTableRecords(CgoInvestment::where('tier', '!=', 'gold')->get());
    }

    public function test_verify_payment_action_dispatches_job()
    {
        Queue::fake();

        $investment = CgoInvestment::factory()->create([
            'payment_status' => 'pending',
            'payment_method' => 'stripe',
        ]);

        Livewire::test(CgoInvestmentResource\Pages\ListCgoInvestments::class)
            ->callTableAction('verify_payment', $investment);

        Queue::assertPushed(\App\Jobs\VerifyCgoPayment::class, function ($job) use ($investment) {
            return $job->investment->id === $investment->id;
        });
    }

    public function test_navigation_badge_shows_pending_count()
    {
        CgoInvestment::factory()->count(3)->create(['status' => 'pending']);
        CgoInvestment::factory()->count(2)->create(['status' => 'confirmed']);

        $this->assertEquals('3', CgoInvestmentResource::getNavigationBadge());
        $this->assertEquals('warning', CgoInvestmentResource::getNavigationBadgeColor());
    }

    public function test_stats_widget_shows_correct_values()
    {
        $round = CgoPricingRound::factory()->create([
            'is_active' => true,
            'shares_sold' => 5000,
            'max_shares_available' => 10000,
        ]);

        CgoInvestment::factory()->count(3)->create([
            'status' => 'confirmed',
            'amount' => 10000,
            'round_id' => $round->id,
        ]);
        
        CgoInvestment::factory()->count(2)->create([
            'status' => 'pending',
            'amount' => 5000,
            'round_id' => $round->id,
        ]);

        Livewire::test(CgoInvestmentResource\Widgets\CgoInvestmentStats::class)
            ->assertSee('$30K') // Total raised
            ->assertSee('3') // Active investors
            ->assertSee('2') // Pending investments
            ->assertSee('Round ' . $round->round_number)
            ->assertSee('50.0%'); // Progress percentage
    }
}