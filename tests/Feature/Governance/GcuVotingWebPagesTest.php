<?php

declare(strict_types=1);

use App\Domain\Governance\Models\GcuVotingProposal;
use App\Models\User;

// GET /gcu/voting/create was registered after GET /gcu/voting/{proposal},
// so "create" was read as a proposal id and the page answered 404.

it('shows the create proposal form to an administrator', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/gcu/voting/create')
        ->assertOk()
        ->assertViewIs('gcu.voting.create')
        ->assertSee('Create Demo Voting Proposal');
});

it('refuses the create proposal form to other users and sends guests to log in', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/gcu/voting/create')
        ->assertForbidden();

    auth()->logout();

    $this->get('/gcu/voting/create')->assertRedirect(route('login'));
});

it('still shows a proposal by its id', function (): void {
    $proposal = GcuVotingProposal::create([
        'title'                => 'Rebalance the demo basket',
        'description'          => 'Shift weight to EUR.',
        'proposed_composition' => ['USD' => 30, 'EUR' => 40, 'GBP' => 30],
        'current_composition'  => ['USD' => 40, 'EUR' => 30, 'GBP' => 30],
        'rationale'            => 'Test',
        'status'               => 'active',
        'voting_starts_at'     => now()->subDay(),
        'voting_ends_at'       => now()->addDay(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get("/gcu/voting/{$proposal->id}")
        ->assertOk()
        ->assertSee('Rebalance the demo basket');
});
