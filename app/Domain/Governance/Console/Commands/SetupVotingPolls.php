<?php

namespace App\Domain\Governance\Console\Commands;

use App\Domain\Governance\Services\VotingTemplateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class SetupVotingPolls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voting:setup 
                            {--month= : Setup voting for a specific month (YYYY-MM)}
                            {--year= : Setup all monthly polls for a year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up currency basket voting polls';

    /**
     * Execute the console command.
     */
    public function handle(VotingTemplateService $templateService): int
    {
        if ($year = $this->option('year')) {
            $this->setupYearlyPolls($templateService, (int) $year);
        } elseif ($month = $this->option('month')) {
            $this->setupMonthlyPoll($templateService, $month);
        } else {
            // Default: Set up next month's poll
            $this->setupNextMonthPoll($templateService);
        }

        return Command::SUCCESS;
    }

    private function setupYearlyPolls(VotingTemplateService $templateService, int $year): void
    {
        $this->info("Setting up voting polls for year {$year}...");

        $polls = $templateService->scheduleYearlyVotingPolls($year);

        $this->info('Created ' . count($polls) . " voting polls for {$year}:");

        foreach ($polls as $poll) {
            $this->line("- {$poll->title} (voting: {$poll->start_date->format('M d')} - {$poll->end_date->format('M d')})");
        }
    }

    private function setupMonthlyPoll(VotingTemplateService $templateService, string $month): void
    {
        try {
            $votingMonth = Carbon::parse($month . '-01');
            $poll = $templateService->createMonthlyBasketVotingPoll($votingMonth);

            $this->info("Created voting poll: {$poll->title}");
            $this->line("Voting period: {$poll->start_date->format('M d, Y')} - {$poll->end_date->format('M d, Y')}");
        } catch (Exception $e) {
            $this->error('Invalid month format. Please use YYYY-MM format.');
        }
    }

    private function setupNextMonthPoll(VotingTemplateService $templateService): void
    {
        $nextMonth = now()->addMonth()->startOfMonth();
        $poll = $templateService->createMonthlyBasketVotingPoll($nextMonth);

        $this->info("Created voting poll for next month: {$poll->title}");
        $this->line("Voting period: {$poll->start_date->format('M d, Y')} - {$poll->end_date->format('M d, Y')}");
        $this->line("Status: {$poll->status->value}");

        if ($this->confirm('Would you like to activate this poll now?')) {
            $poll->update(['status' => \App\Domain\Governance\Enums\PollStatus::ACTIVE]);
            $this->info('Poll activated successfully!');
        }
    }
}
