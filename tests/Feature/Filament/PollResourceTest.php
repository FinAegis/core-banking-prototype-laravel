<?php

declare(strict_types=1);

use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Filament\Admin\Resources\PollResource;
use App\Filament\Admin\Resources\PollResource\Pages\CreatePoll;
use App\Filament\Admin\Resources\PollResource\Pages\EditPoll;
use App\Filament\Admin\Resources\PollResource\Pages\ListPolls;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('Poll Resource', function () {
    it('can render poll index page', function () {
        $polls = Poll::factory()->count(5)->create();

        Livewire::test(ListPolls::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($polls);
    });

    it('can render poll creation form', function () {
        Livewire::test(CreatePoll::class)
            ->assertSuccessful()
            ->assertFormExists();
    });

    it('can create a poll', function () {
        $pollData = [
            'title' => 'Should we add Dark Mode?',
            'description' => 'Adding dark mode theme support',
            'type' => PollType::YES_NO->value,
            'options' => [
                ['id' => 'yes', 'label' => 'Yes', 'description' => 'Add dark mode'],
                ['id' => 'no', 'label' => 'No', 'description' => 'Keep current theme'],
            ],
            'start_date' => now()->addHour(),
            'end_date' => now()->addWeek(),
            'voting_power_strategy' => 'one_user_one_vote',
            'status' => PollStatus::DRAFT->value,
        ];

        Livewire::test(CreatePoll::class)
            ->fillForm($pollData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('polls', [
            'title' => 'Should we add Dark Mode?',
            'created_by' => $this->user->uuid,
        ]);
    });

    it('validates required fields on poll creation', function () {
        Livewire::test(CreatePoll::class)
            ->fillForm([
                'title' => '',
                'type' => '',
                'options' => [],
            ])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'type' => 'required',
                'options' => 'required',
            ]);
    });

    it('can edit a poll', function () {
        $poll = Poll::factory()->create();

        Livewire::test(EditPoll::class, ['record' => $poll->getRouteKey()])
            ->assertFormSet([
                'title' => $poll->title,
                'type' => $poll->type->value,
            ])
            ->fillForm([
                'title' => 'Updated Poll Title',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($poll->fresh()->title)->toBe('Updated Poll Title');
    });

    it('can filter polls by status', function () {
        $activePoll = Poll::factory()->active()->create();
        $draftPoll = Poll::factory()->draft()->create();

        Livewire::test(ListPolls::class)
            ->filterTable('status', PollStatus::ACTIVE->value)
            ->assertCanSeeTableRecords([$activePoll])
            ->assertCanNotSeeTableRecords([$draftPoll]);
    });

    it('can filter polls by type', function () {
        $yesNoPoll = Poll::factory()->yesNo()->create();
        $singleChoicePoll = Poll::factory()->singleChoice()->create();

        Livewire::test(ListPolls::class)
            ->filterTable('type', PollType::YES_NO->value)
            ->assertCanSeeTableRecords([$yesNoPoll])
            ->assertCanNotSeeTableRecords([$singleChoicePoll]);
    });

    it('displays poll statistics correctly', function () {
        $poll = Poll::factory()->create();
        Vote::factory()->forPoll($poll)->count(5)->create();

        Livewire::test(ListPolls::class)
            ->assertTableColumnStateSet('votes_count', '5', $poll);
    });

    it('can activate a draft poll', function () {
        $poll = Poll::factory()->draft()->create([
            'start_date' => now()->subMinute(),
            'end_date' => now()->addWeek(),
        ]);

        Livewire::test(ListPolls::class)
            ->callTableAction('activate', $poll)
            ->assertSuccessful();

        expect($poll->fresh()->status)->toBe(PollStatus::ACTIVE);
    });

    it('can cancel a poll with reason', function () {
        $poll = Poll::factory()->draft()->create();

        Livewire::test(ListPolls::class)
            ->callTableAction('cancel', $poll, data: [
                'reason' => 'No longer needed',
            ])
            ->assertSuccessful();

        expect($poll->fresh()->status)->toBe(PollStatus::CANCELLED);
        expect($poll->fresh()->metadata['cancellation_reason'])->toBe('No longer needed');
    });

    it('can complete an expired active poll', function () {
        $poll = Poll::factory()->active()->create([
            'end_date' => now()->subMinute(),
        ]);

        // Add some votes
        Vote::factory()->forPoll($poll)->yesVote()->count(3)->create();
        Vote::factory()->forPoll($poll)->noVote()->count(2)->create();

        Livewire::test(ListPolls::class)
            ->callTableAction('complete', $poll)
            ->assertSuccessful();

        expect($poll->fresh()->status)->toBe(PollStatus::CLOSED);
    });

    it('can bulk activate multiple draft polls', function () {
        $draftPolls = Poll::factory()->draft()->count(3)->create([
            'start_date' => now()->subMinute(),
            'end_date' => now()->addWeek(),
        ]);

        Livewire::test(ListPolls::class)
            ->callTableBulkAction('activate', $draftPolls)
            ->assertSuccessful();

        foreach ($draftPolls as $poll) {
            expect($poll->fresh()->status)->toBe(PollStatus::ACTIVE);
        }
    });

    it('displays poll actions correctly based on status', function () {
        $draftPoll = Poll::factory()->draft()->create();
        $activePoll = Poll::factory()->active()->create();
        $expiredPoll = Poll::factory()->active()->create(['end_date' => now()->subMinute()]);

        // Draft polls should show activate action
        Livewire::test(ListPolls::class)
            ->assertTableActionVisible('activate', $draftPoll)
            ->assertTableActionHidden('complete', $draftPoll);

        // Active polls should show cancel action
        Livewire::test(ListPolls::class)
            ->assertTableActionVisible('cancel', $activePoll)
            ->assertTableActionHidden('activate', $activePoll)
            ->assertTableActionHidden('complete', $activePoll);

        // Expired active polls should show complete action
        Livewire::test(ListPolls::class)
            ->assertTableActionVisible('complete', $expiredPoll)
            ->assertTableActionVisible('cancel', $expiredPoll);
    });

    it('can search polls by title', function () {
        $poll1 = Poll::factory()->create(['title' => 'Dark Mode Implementation']);
        $poll2 = Poll::factory()->create(['title' => 'Light Theme Update']);

        Livewire::test(ListPolls::class)
            ->searchTable('Dark Mode')
            ->assertCanSeeTableRecords([$poll1])
            ->assertCanNotSeeTableRecords([$poll2]);
    });

    it('sorts polls by creation date descending by default', function () {
        $oldPoll = Poll::factory()->create(['created_at' => now()->subDays(2)]);
        $newPoll = Poll::factory()->create(['created_at' => now()->subDay()]);

        Livewire::test(ListPolls::class)
            ->assertTableRecordsOrder([$newPoll, $oldPoll]);
    });
});