<?php

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Models\User;
use App\Models\Account;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Set GCU configuration
    config([
        'baskets.primary_code' => 'GCU',
    ]);
    
    // Create GCU asset if it doesn't exist
    \App\Domain\Asset\Models\Asset::firstOrCreate(
        ['code' => 'GCU'],
        [
            'name' => 'Global Currency Unit',
            'type' => 'custom',
            'precision' => 2,
            'is_active' => true,
            'is_basket' => true,
        ]
    );
    
    $this->user = User::factory()->create();
    $this->account = Account::factory()->forUser($this->user)->create();
    
    // Give user some GCU balance for voting power
    $this->account->addBalance('GCU', 100000); // 1000 GCU = 1000 voting power
});

it('can get active polls with user context', function () {
    Sanctum::actingAs($this->user);
    
    // Create an active GCU poll
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'type' => PollType::WEIGHTED_CHOICE,
        'end_date' => now()->addDays(7),
        'voting_power_strategy' => \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class,
        'metadata' => [
            'template' => 'monthly_basket',
            'basket_code' => 'GCU',
        ],
        'options' => [[
            'id' => 'basket_weights',
            'label' => 'GCU Basket Weights',
            'type' => 'allocation',
            'currencies' => [
                ['code' => 'USD', 'name' => 'US Dollar', 'min' => 20, 'max' => 50, 'default' => 40],
                ['code' => 'EUR', 'name' => 'Euro', 'min' => 20, 'max' => 40, 'default' => 30],
            ],
        ]],
    ]);
    
    $response = $this->getJson('/api/voting/polls');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'description',
                    'type',
                    'status',
                    'options',
                    'user_context' => [
                        'has_voted',
                        'voting_power',
                        'can_vote',
                    ],
                    'metadata' => [
                        'is_gcu_poll',
                    ],
                    'time_remaining',
                ],
            ],
            'meta' => [
                'basket_name',
                'basket_code',
                'basket_symbol',
            ],
        ])
        ->assertJsonPath('data.0.user_context.voting_power', 1000)
        ->assertJsonPath('data.0.user_context.can_vote', true)
        ->assertJsonPath('meta.basket_code', 'GCU');
});

it('can get upcoming polls', function () {
    Sanctum::actingAs($this->user);
    
    Poll::factory()->create([
        'status' => PollStatus::DRAFT,
        'start_date' => now()->addDays(5),
        'end_date' => now()->addDays(12),
    ]);
    
    $response = $this->getJson('/api/voting/polls/upcoming');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can get voting history', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create();
    Vote::factory()->create([
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
    ]);
    
    $response = $this->getJson('/api/voting/polls/history');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'user_context' => [
                        'has_voted',
                        'vote',
                    ],
                ],
            ],
            'meta' => [
                'total_votes',
                'member_since',
            ],
        ])
        ->assertJsonPath('meta.total_votes', 1);
});

it('can submit basket vote with valid allocations', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'type' => PollType::WEIGHTED_CHOICE,
        'voting_power_strategy' => \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class,
        'metadata' => [
            'template' => 'monthly_basket',
            'basket_code' => 'GCU',
        ],
    ]);
    
    $allocations = [
        'USD' => 40,
        'EUR' => 30,
        'GBP' => 15,
        'CHF' => 10,
        'JPY' => 3,
        'XAU' => 2,
    ];
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => $allocations,
    ]);
    
    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'vote_id',
            'voting_power_used',
        ])
        ->assertJsonPath('voting_power_used', 1000);
    
    // Verify vote was created
    $this->assertDatabaseHas('votes', [
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
        'voting_power' => 1000,
    ]);
});

it('validates allocations sum to 100', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'metadata' => ['template' => 'monthly_basket'],
    ]);
    
    $allocations = [
        'USD' => 40,
        'EUR' => 30,
        'GBP' => 15,
        // Missing allocations - doesn't sum to 100
    ];
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => $allocations,
    ]);
    
    $response->assertUnprocessable()
        ->assertJsonPath('error', 'Allocations must sum to 100%')
        ->assertJsonPath('current_sum', 85);
});

it('prevents double voting', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'metadata' => ['template' => 'monthly_basket'],
    ]);
    
    // Create existing vote
    Vote::factory()->create([
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
    ]);
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => ['USD' => 100],
    ]);
    
    $response->assertForbidden()
        ->assertJsonPath('error', 'You have already voted in this poll');
});

it('requires voting power to vote', function () {
    Sanctum::actingAs($this->user);
    
    // Remove GCU balance
    $this->account->subtractBalance('GCU', 100000);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'metadata' => ['template' => 'monthly_basket'],
        'voting_power_strategy' => \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class,
    ]);
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => ['USD' => 100],
    ]);
    
    $response->assertForbidden()
        ->assertJsonPath('error', 'You have no voting power for this poll');
});

it('can get voting dashboard data', function () {
    Sanctum::actingAs($this->user);
    
    // Clean up any existing polls to ensure test isolation
    Poll::query()->delete();
    
    // Create some test data
    Poll::factory()->count(2)->create([
        'status' => PollStatus::ACTIVE,
        'end_date' => now()->addDays(7), // Ensure polls are not expired
    ]);
    Vote::factory()->count(3)->create(['user_uuid' => $this->user->uuid]);
    
    $response = $this->getJson('/api/voting/dashboard');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'stats' => [
                    'active_polls',
                    'votes_cast',
                    'gcu_balance',
                    'voting_power',
                ],
                'next_poll',
                'basket_info' => [
                    'name',
                    'code',
                    'symbol',
                ],
            ],
        ])
        ->assertJsonPath('data.stats.active_polls', 2)
        ->assertJsonPath('data.stats.votes_cast', 3)
        ->assertJsonPath('data.stats.gcu_balance', 100000)
        ->assertJsonPath('data.basket_info.code', 'GCU');
});

it('only accepts basket voting through basket endpoint', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'type' => PollType::SINGLE_CHOICE,
        'metadata' => ['template' => 'add_currency'],
    ]);
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => ['USD' => 100],
    ]);
    
    $response->assertBadRequest()
        ->assertJsonPath('error', 'This endpoint is for basket voting only');
});

it('includes metadata in vote record', function () {
    Sanctum::actingAs($this->user);
    
    $poll = Poll::factory()->create([
        'status' => PollStatus::ACTIVE,
        'metadata' => ['template' => 'monthly_basket'],
    ]);
    
    $response = $this->postJson("/api/voting/polls/{$poll->uuid}/vote", [
        'allocations' => ['USD' => 100],
    ]);
    
    $response->assertCreated();
    
    $vote = Vote::where('user_uuid', $this->user->uuid)->first();
    expect($vote->metadata)->toHaveKey('voted_via', 'user_voting_api');
    expect($vote->metadata)->toHaveKey('ip_address');
    expect($vote->metadata)->toHaveKey('user_agent');
});

it('requires authentication for all endpoints', function () {
    $endpoints = [
        ['GET', '/api/voting/polls'],
        ['GET', '/api/voting/polls/upcoming'],
        ['GET', '/api/voting/polls/history'],
        ['POST', '/api/voting/polls/test-uuid/vote'],
        ['GET', '/api/voting/dashboard'],
    ];
    
    foreach ($endpoints as [$method, $url]) {
        $response = $this->json($method, $url);
        $response->assertUnauthorized();
    }
});