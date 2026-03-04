<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SponsorshipControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_sponsorship_status_requires_auth(): void
    {
        $this->getJson('/api/v1/sponsorship/status')
            ->assertStatus(401);
    }

    public function test_sponsorship_status_returns_not_eligible_when_disabled(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        config(['relayer.sponsorship.enabled' => false]);

        $this->getJson('/api/v1/sponsorship/status')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.remaining_free_tx', 0);
    }

    public function test_sponsorship_status_returns_eligible_when_granted(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 2,
            'free_tx_until'      => now()->addDays(30),
        ]);

        $this->getJson('/api/v1/sponsorship/status')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.remaining_free_tx', 3)
            ->assertJsonPath('data.total_sponsored', 2);
    }

    public function test_sponsorship_status_shows_expired(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 2,
            'free_tx_until'      => now()->subDay(),
        ]);

        $this->getJson('/api/v1/sponsorship/status')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.remaining_free_tx', 0);
    }
}
