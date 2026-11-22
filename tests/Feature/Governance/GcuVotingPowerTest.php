<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Governance\Models\GcuVotingProposal;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Accounts hold assets in account_balances; the accounts table has neither a
// currency nor a type column. The vote endpoint looked for an account with
// currency = GCU and type = personal (MySQL rejects that, SQLite finds none)
// and then used the account's USD balance as voting power.

beforeEach(function (): void {
    // account_balances.asset_code references assets.code.
    Asset::query()->firstOrCreate(
        ['code' => 'GCU'],
        ['name' => 'Global Currency Unit', 'type' => 'custom', 'precision' => 2, 'is_active' => true]
    );
});

function gcuVotingProposal(): GcuVotingProposal
{
    return GcuVotingProposal::create([
        'title'                => 'Rebalance GCU',
        'description'          => 'Shift weight to EUR.',
        'proposed_composition' => ['USD' => 30, 'EUR' => 40, 'GBP' => 30],
        'current_composition'  => ['USD' => 40, 'EUR' => 30, 'GBP' => 30],
        'rationale'            => 'Test',
        'status'               => 'active',
        'voting_starts_at'     => now()->subDay(),
        'voting_ends_at'       => now()->addDay(),
    ]);
}

it('uses the GCU holdings across the user accounts as voting power', function (): void {
    $user = User::factory()->create();
    $first = Account::factory()->create(['user_uuid' => $user->uuid]);
    $second = Account::factory()->create(['user_uuid' => $user->uuid]);
    AccountBalance::create(['account_uuid' => $first->uuid, 'asset_code' => 'GCU', 'balance' => 1500]);
    AccountBalance::create(['account_uuid' => $second->uuid, 'asset_code' => 'GCU', 'balance' => 500]);
    AccountBalance::create(['account_uuid' => $first->uuid, 'asset_code' => 'USD', 'balance' => 99999]);
    $proposal = gcuVotingProposal();

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson("/api/v2/gcu/voting/proposals/{$proposal->id}/vote", ['vote' => 'for'])
        ->assertOk()
        ->assertJsonPath('data.voting_power', 2000);
});

it('refuses a vote from a user without GCU holdings', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    AccountBalance::create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 5000]);
    $proposal = gcuVotingProposal();

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson("/api/v2/gcu/voting/proposals/{$proposal->id}/vote", ['vote' => 'for'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'You need GCU holdings to vote');
});
