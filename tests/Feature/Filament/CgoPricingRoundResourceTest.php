<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CgoPricingRoundResource;
use App\Models\CgoPricingRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\FilamentTestCase;

class CgoPricingRoundResourceTest extends FilamentTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup if needed
    }

    public function test_can_list_pricing_rounds()
    {
        $rounds = CgoPricingRound::factory()->count(3)->create();

        Livewire::test(CgoPricingRoundResource\Pages\ListCgoPricingRounds::class)
            ->assertCanSeeTableRecords($rounds);
    }

    public function test_can_create_pricing_round()
    {
        $roundData = [
            'round_number' => 1,
            'share_price' => 10.5000,
            'max_shares_available' => 100000,
            'is_active' => true,
            'started_at' => now(),
        ];

        Livewire::test(CgoPricingRoundResource\Pages\CreateCgoPricingRound::class)
            ->fillForm($roundData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cgo_pricing_rounds', [
            'round_number' => 1,
            'share_price' => 10.5000,
            'max_shares_available' => 100000,
            'is_active' => true,
            'shares_sold' => 0,
            'total_raised' => 0,
        ]);
    }

    public function test_creating_active_round_deactivates_others()
    {
        $existingRound = CgoPricingRound::factory()->create([
            'is_active' => true,
        ]);

        $newRoundData = [
            'round_number' => 2,
            'share_price' => 11.5500,
            'max_shares_available' => 150000,
            'is_active' => true,
            'started_at' => now(),
        ];

        Livewire::test(CgoPricingRoundResource\Pages\CreateCgoPricingRound::class)
            ->fillForm($newRoundData)
            ->call('create')
            ->assertHasNoFormErrors();

        $existingRound->refresh();
        $this->assertFalse($existingRound->is_active);
        
        $this->assertDatabaseHas('cgo_pricing_rounds', [
            'round_number' => 2,
            'is_active' => true,
        ]);
    }

    public function test_can_edit_pricing_round()
    {
        $round = CgoPricingRound::factory()->create([
            'share_price' => 10.0000,
            'is_active' => false,
        ]);

        Livewire::test(CgoPricingRoundResource\Pages\EditCgoPricingRound::class, [
            'record' => $round->getRouteKey(),
        ])
            ->fillForm([
                'share_price' => 12.5000,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cgo_pricing_rounds', [
            'id' => $round->id,
            'share_price' => 12.5000,
            'is_active' => true,
        ]);
    }

    public function test_can_activate_round()
    {
        $inactiveRound = CgoPricingRound::factory()->create(['is_active' => false]);
        $activeRound = CgoPricingRound::factory()->create(['is_active' => true]);

        Livewire::test(CgoPricingRoundResource\Pages\ListCgoPricingRounds::class)
            ->callTableAction('activate', $inactiveRound);

        $inactiveRound->refresh();
        $activeRound->refresh();

        $this->assertTrue($inactiveRound->is_active);
        $this->assertFalse($activeRound->is_active);
    }

    public function test_can_close_active_round()
    {
        $activeRound = CgoPricingRound::factory()->create([
            'is_active' => true,
            'ended_at' => null,
        ]);

        Livewire::test(CgoPricingRoundResource\Pages\ListCgoPricingRounds::class)
            ->callTableAction('close', $activeRound);

        $activeRound->refresh();

        $this->assertFalse($activeRound->is_active);
        $this->assertNotNull($activeRound->ended_at);
    }

    public function test_can_filter_by_active_status()
    {
        CgoPricingRound::factory()->create(['is_active' => true]);
        CgoPricingRound::factory()->create(['is_active' => false]);

        Livewire::test(CgoPricingRoundResource\Pages\ListCgoPricingRounds::class)
            ->assertCanSeeTableRecords(CgoPricingRound::all())
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords(CgoPricingRound::where('is_active', true)->get())
            ->assertCanNotSeeTableRecords(CgoPricingRound::where('is_active', false)->get());
    }

    public function test_round_number_must_be_unique()
    {
        CgoPricingRound::factory()->create(['round_number' => 1]);

        Livewire::test(CgoPricingRoundResource\Pages\CreateCgoPricingRound::class)
            ->fillForm([
                'round_number' => 1,
                'share_price' => 10.0000,
                'max_shares_available' => 100000,
                'started_at' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['round_number']);
    }
}