<?php

declare(strict_types=1);

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Filament\Admin\Resources\VoteResource;
use App\Filament\Admin\Resources\VoteResource\Pages\ListVotes;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('Vote Resource', function () {
    it('can render vote index page', function () {
        $votes = Vote::factory()->count(5)->create();

        Livewire::test(ListVotes::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($votes);
    });

    it('displays vote information correctly', function () {
        $poll = Poll::factory()->yesNo()->create(['title' => 'Test Poll']);
        $user = User::factory()->create(['name' => 'John Doe']);
        $vote = Vote::factory()->forPoll($poll)->forUser($user)->yesVote()->create([
            'voting_power' => 10,
        ]);

        Livewire::test(ListVotes::class)
            ->assertTableColumnStateSet('poll.title', 'Test Poll', $vote)
            ->assertTableColumnStateSet('user.name', 'John Doe', $vote)
            ->assertTableColumnStateSet('voting_power', '10', $vote);
    });

    it('can filter votes by poll', function () {
        $poll1 = Poll::factory()->create();
        $poll2 = Poll::factory()->create();
        
        $vote1 = Vote::factory()->forPoll($poll1)->create();
        $vote2 = Vote::factory()->forPoll($poll2)->create();

        Livewire::test(ListVotes::class)
            ->filterTable('poll_id', $poll1->id)
            ->assertCanSeeTableRecords([$vote1])
            ->assertCanNotSeeTableRecords([$vote2]);
    });

    it('can filter votes by user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $vote1 = Vote::factory()->forUser($user1)->create();
        $vote2 = Vote::factory()->forUser($user2)->create();

        Livewire::test(ListVotes::class)
            ->filterTable('user_uuid', $user1->uuid)
            ->assertCanSeeTableRecords([$vote1])
            ->assertCanNotSeeTableRecords([$vote2]);
    });

    it('can filter votes by high voting power', function () {
        $highPowerVote = Vote::factory()->withHighVotingPower()->create();
        $lowPowerVote = Vote::factory()->withLowVotingPower()->create();

        Livewire::test(ListVotes::class)
            ->filterTable('high_voting_power')
            ->assertCanSeeTableRecords([$highPowerVote])
            ->assertCanNotSeeTableRecords([$lowPowerVote]);
    });

    it('can filter recent votes', function () {
        $recentVote = Vote::factory()->recentVote()->create();
        $oldVote = Vote::factory()->oldVote()->create();

        Livewire::test(ListVotes::class)
            ->filterTable('recent_votes')
            ->assertCanSeeTableRecords([$recentVote])
            ->assertCanNotSeeTableRecords([$oldVote]);
    });

    it('can verify vote signature', function () {
        $vote = Vote::factory()->create();

        Livewire::test(ListVotes::class)
            ->callTableAction('verify_signature', $vote)
            ->assertSuccessful()
            ->assertNotified();
    });

    it('can bulk verify vote signatures', function () {
        $votes = Vote::factory()->count(3)->create();

        Livewire::test(ListVotes::class)
            ->callTableBulkAction('verify_signatures', $votes)
            ->assertSuccessful()
            ->assertNotified();
    });

    it('displays vote validity status', function () {
        $validVote = Vote::factory()->create([
            'selected_options' => ['yes'],
            'voting_power' => 5,
        ]);

        $invalidVote = Vote::factory()->create([
            'selected_options' => [],
            'voting_power' => 0,
        ]);

        Livewire::test(ListVotes::class)
            ->assertTableColumnStateSet('is_valid', true, $validVote)
            ->assertTableColumnStateSet('is_valid', false, $invalidVote);
    });

    it('displays voting power weight correctly', function () {
        $poll = Poll::factory()->create();
        
        // Create votes with different voting powers
        $vote1 = Vote::factory()->forPoll($poll)->create(['voting_power' => 30]);
        $vote2 = Vote::factory()->forPoll($poll)->create(['voting_power' => 70]);

        Livewire::test(ListVotes::class)
            ->assertTableColumnStateSet('voting_power_weight', '30.0%', $vote1)
            ->assertTableColumnStateSet('voting_power_weight', '70.0%', $vote2);
    });

    it('can search votes by poll title', function () {
        $poll1 = Poll::factory()->create(['title' => 'Dark Mode Poll']);
        $poll2 = Poll::factory()->create(['title' => 'Light Theme Poll']);
        
        $vote1 = Vote::factory()->forPoll($poll1)->create();
        $vote2 = Vote::factory()->forPoll($poll2)->create();

        Livewire::test(ListVotes::class)
            ->searchTable('Dark Mode')
            ->assertCanSeeTableRecords([$vote1])
            ->assertCanNotSeeTableRecords([$vote2]);
    });

    it('can search votes by user name', function () {
        $user1 = User::factory()->create(['name' => 'Alice Smith']);
        $user2 = User::factory()->create(['name' => 'Bob Jones']);
        
        $vote1 = Vote::factory()->forUser($user1)->create();
        $vote2 = Vote::factory()->forUser($user2)->create();

        Livewire::test(ListVotes::class)
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$vote1])
            ->assertCanNotSeeTableRecords([$vote2]);
    });

    it('sorts votes by vote date descending by default', function () {
        $oldVote = Vote::factory()->create(['voted_at' => now()->subDays(2)]);
        $newVote = Vote::factory()->create(['voted_at' => now()->subDay()]);

        Livewire::test(ListVotes::class)
            ->assertTableRecordsOrder([$newVote, $oldVote]);
    });

    it('displays poll status badge with correct colors', function () {
        $activePoll = Poll::factory()->active()->create();
        $draftPoll = Poll::factory()->draft()->create();
        
        $activeVote = Vote::factory()->forPoll($activePoll)->create();
        $draftVote = Vote::factory()->forPoll($draftPoll)->create();

        Livewire::test(ListVotes::class)
            ->assertTableColumnStateSet('poll.status', 'active', $activeVote)
            ->assertTableColumnStateSet('poll.status', 'draft', $draftVote);
    });

    it('prevents vote creation through admin panel', function () {
        expect(VoteResource::canCreate())->toBeFalse();
    });

    it('shows selected options as badges', function () {
        $vote = Vote::factory()->create([
            'selected_options' => ['option1', 'option2', 'option3'],
        ]);

        $component = Livewire::test(ListVotes::class);
        
        // Verify the vote is visible and selected options are displayed
        $component->assertCanSeeTableRecords([$vote]);
        
        // The selected options should be shown as comma-separated values
        expect($vote->getSelectedOptionsAsString())->toBe('option1, option2, option3');
    });
});