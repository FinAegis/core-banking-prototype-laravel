<?php

declare(strict_types=1);

namespace Tests\Domain\Relayer\Services;

use App\Domain\Relayer\Services\SponsorshipService;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SponsorshipServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SponsorshipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SponsorshipService();
        $this->user = User::factory()->create();
    }

    public function test_is_not_eligible_when_disabled(): void
    {
        config(['relayer.sponsorship.enabled' => false]);

        $this->user->update(['sponsored_tx_limit' => 5]);

        $this->assertFalse($this->service->isEligible($this->user));
    }

    public function test_is_not_eligible_with_zero_limit(): void
    {
        config(['relayer.sponsorship.enabled' => true]);

        $this->assertFalse($this->service->isEligible($this->user));
    }

    public function test_is_eligible_with_remaining_tx(): void
    {
        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 2,
            'free_tx_until'      => now()->addDays(10),
        ]);

        $this->assertTrue($this->service->isEligible($this->user));
    }

    public function test_is_not_eligible_when_limit_reached(): void
    {
        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 5,
            'free_tx_until'      => now()->addDays(10),
        ]);

        $this->assertFalse($this->service->isEligible($this->user));
    }

    public function test_is_not_eligible_when_expired(): void
    {
        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 0,
            'free_tx_until'      => now()->subDay(),
        ]);

        $this->assertFalse($this->service->isEligible($this->user));
    }

    public function test_consume_sponsored_tx_increments_used(): void
    {
        config(['relayer.sponsorship.enabled' => true]);

        $this->user->update([
            'sponsored_tx_limit' => 5,
            'sponsored_tx_used'  => 0,
            'free_tx_until'      => now()->addDays(10),
        ]);

        $this->assertTrue($this->service->consumeSponsoredTx($this->user));

        $this->user->refresh();
        $this->assertEquals(1, $this->user->sponsored_tx_used);
    }

    public function test_consume_sponsored_tx_fails_when_not_eligible(): void
    {
        config(['relayer.sponsorship.enabled' => false]);

        $this->assertFalse($this->service->consumeSponsoredTx($this->user));
    }

    public function test_get_remaining_free_tx(): void
    {
        $this->user->update([
            'sponsored_tx_limit' => 10,
            'sponsored_tx_used'  => 3,
            'free_tx_until'      => now()->addDays(10),
        ]);

        $this->assertEquals(7, $this->service->getRemainingFreeTx($this->user));
    }

    public function test_get_remaining_free_tx_zero_when_expired(): void
    {
        $this->user->update([
            'sponsored_tx_limit' => 10,
            'sponsored_tx_used'  => 3,
            'free_tx_until'      => now()->subDay(),
        ]);

        $this->assertEquals(0, $this->service->getRemainingFreeTx($this->user));
    }

    public function test_grant_sponsorship(): void
    {
        config(['relayer.sponsorship.default_free_period_days' => 30]);

        $this->service->grantSponsorship($this->user, 10);

        $this->user->refresh();
        $this->assertEquals(10, $this->user->sponsored_tx_limit);
        $this->assertNotNull($this->user->free_tx_until);
        $this->assertTrue($this->user->free_tx_until->isFuture());
    }

    public function test_grant_sponsorship_stacks(): void
    {
        config(['relayer.sponsorship.default_free_period_days' => 30]);

        $this->user->update(['sponsored_tx_limit' => 5]);

        $this->service->grantSponsorship($this->user, 3);

        $this->user->refresh();
        $this->assertEquals(8, $this->user->sponsored_tx_limit);
    }
}
