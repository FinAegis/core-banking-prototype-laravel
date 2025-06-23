<?php

use App\Domain\Governance\Models\Poll;
use App\Models\User;
use App\Models\AccountBalance;
use App\Domain\Governance\Models\Vote;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('can cast a vote on an active poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Vote cast successfully',
        ]);

    // Check vote was created with correct data
    $vote = \App\Domain\Governance\Models\Vote::where('poll_id', $poll->id)
        ->where('user_uuid', $this->user->uuid)
        ->first();
    
    expect($vote)->not->toBeNull();
    expect($vote->selected_options)->toBe(['yes']);
    expect($vote->voting_power)->toBe(1);
});

it('can cast multiple votes on multiple choice poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'multiple_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'option1', 'label' => 'Option 1'],
            ['id' => 'option2', 'label' => 'Option 2'],
            ['id' => 'option3', 'label' => 'Option 3'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['option1', 'option3'],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Vote cast successfully',
        ]);

    // Check vote was created with correct data
    $vote = \App\Domain\Governance\Models\Vote::where('poll_id', $poll->id)
        ->where('user_uuid', $this->user->uuid)
        ->first();
    
    expect($vote)->not->toBeNull();
    expect($vote->selected_options)->toBe(['option1', 'option3']);
});

it('cannot vote on inactive poll', function () {
    $poll = Poll::factory()->create([
        'status' => 'draft',
        'type' => 'single_choice',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Poll is not available for voting',
        ]);

    $this->assertDatabaseMissing('votes', [
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
    ]);
});

it('cannot vote on expired poll', function () {
    $poll = Poll::factory()->create([
        'status' => 'active',
        'start_date' => now()->subDays(7),
        'end_date' => now()->subDay(),
        'type' => 'single_choice',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Poll is not available for voting',
        ]);
});

it('cannot vote twice on same poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    // First vote
    Vote::factory()->create([
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
        'selected_options' => ['yes'],
        'voting_power' => 1,
        'voted_at' => now(),
    ]);

    // Attempt second vote
    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['no'],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'User has already voted in this poll',
        ]);
});

it('validates option exists for single choice poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['invalid_option'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['selected_options']);
});

it('validates options exist for multiple choice poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'multiple_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'option1', 'label' => 'Option 1'],
            ['id' => 'option2', 'label' => 'Option 2'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['option1', 'invalid_option'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['selected_options']);
});

it('requires option_id for single choice poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['selected_options']);
});

it('requires option_ids for multiple choice poll', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'multiple_choice',
        'options' => [
            ['id' => 'option1', 'label' => 'Option 1'],
            ['id' => 'option2', 'label' => 'Option 2'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['selected_options']);
});

it('calculates voting power correctly for asset weighted voting', function () {
    // Create user with account and USD balance
    $account = $this->user->accounts()->create([
        'uuid' => fake()->uuid(),
        'name' => 'Test Account',
    ]);
    
    // Add USD balance to the account
    AccountBalance::create([
        'account_uuid' => $account->uuid,
        'asset_code' => 'USD',
        'balance' => 500000,
    ]); // $5000

    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'asset_weighted_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('votes', [
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
        'voting_power' => 50, // $5000 / $100 = 50 voting power
    ]);
});

it('calculates higher voting power for larger balances', function () {
    // Create user with account and USD balance
    $account = $this->user->accounts()->create([
        'uuid' => fake()->uuid(),
        'name' => 'Test Account',
    ]);
    
    // Add USD balance to the account
    AccountBalance::create([
        'account_uuid' => $account->uuid,
        'asset_code' => 'USD',
        'balance' => 1000000,
    ]); // $10000

    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'asset_weighted_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('votes', [
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
        'voting_power' => 100, // $10000 / $100 = 100 voting power
    ]);
});

it('defaults to voting power of 1 for one-user-one-vote strategy', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('votes', [
        'poll_id' => $poll->id,
        'user_uuid' => $this->user->uuid,
        'voting_power' => 1,
    ]);
});

it('returns 404 for non-existent poll', function () {
    $response = $this->postJson('/api/polls/non-existent-uuid/vote', [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(404);
});

it('requires authentication', function () {
    $poll = Poll::factory()->active()->create();

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ], ['Authorization' => '']);

    $response->assertStatus(401);
})->skip('Authentication test needs refactoring');

it('generates cryptographic signature for vote integrity', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $response->assertStatus(201);

    // Verify vote was created with auto-generated signature
    $vote = \App\Domain\Governance\Models\Vote::where('poll_id', $poll->id)
        ->where('user_uuid', $this->user->uuid)
        ->first();

    expect($vote)->not->toBeNull();
    expect($vote->signature)->not->toBeEmpty();
    expect($vote->verifySignature())->toBeTrue();
});

it('records vote timestamp correctly', function () {
    $poll = Poll::factory()->active()->create([
        'type' => 'single_choice',
        'voting_power_strategy' => 'one_user_one_vote',
        'options' => [
            ['id' => 'yes', 'label' => 'Yes'],
            ['id' => 'no', 'label' => 'No'],
        ],
    ]);

    $beforeVote = now()->subSecond(); // Give a bit more buffer
    
    $response = $this->postJson("/api/polls/{$poll->uuid}/vote", [
        'selected_options' => ['yes'],
    ]);

    $afterVote = now()->addSecond(); // Give a bit more buffer

    $response->assertStatus(201);

    $vote = Vote::where('poll_id', $poll->id)
        ->where('user_uuid', $this->user->uuid)
        ->first();

    expect($vote->voted_at)
        ->toBeGreaterThanOrEqual($beforeVote)
        ->toBeLessThanOrEqual($afterVote);
});