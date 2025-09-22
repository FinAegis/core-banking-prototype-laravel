<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
// use App\Domain\AgentProtocol\DataObjects\EscrowRequest; // Not implemented yet
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the Escrow Workflow functionality.
 *
 * Note: These tests are currently skipped as the EscrowAggregate
 * and related classes are not fully implemented yet.
 */
class EscrowWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_escrow_with_conditions()
    {
        $this->markTestSkipped('EscrowRequest data object not yet implemented');
    }

    /** @test */
    public function it_can_fund_escrow()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_can_release_funds_when_conditions_met()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_can_handle_disputes()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_can_resolve_dispute_with_refund()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_can_handle_timeout_expiry()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_prevents_release_without_meeting_conditions()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }

    /** @test */
    public function it_calculates_escrow_duration()
    {
        $this->markTestSkipped('Escrow aggregate methods not fully implemented');
    }
}
