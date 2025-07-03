<?php

namespace App\Console\Commands;

use App\Models\GcuVotingProposal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VotingSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voting:setup {--month=next : Current, next, or specific month number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create monthly GCU composition voting proposal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthOption = $this->option('month');
        
        // Determine the month
        $now = now();
        if ($monthOption === 'current') {
            $targetMonth = $now;
        } elseif ($monthOption === 'next') {
            $targetMonth = $now->copy()->addMonth();
        } elseif (is_numeric($monthOption)) {
            $targetMonth = Carbon::create($now->year, $monthOption, 1);
            if ($targetMonth->lt($now)) {
                $targetMonth->addYear();
            }
        } else {
            $this->error('Invalid month option. Use "current", "next", or a month number (1-12).');
            return 1;
        }
        
        // Check if proposal already exists for this month
        $existingProposal = GcuVotingProposal::whereYear('voting_starts_at', $targetMonth->year)
            ->whereMonth('voting_starts_at', $targetMonth->month)
            ->first();
            
        if ($existingProposal) {
            $this->warn("A proposal already exists for {$targetMonth->format('F Y')}");
            return 0;
        }
        
        // Get current composition from config
        $currentComposition = config('platform.gcu.composition', [
            'USD' => 30,
            'EUR' => 25,
            'GBP' => 15,
            'CHF' => 10,
            'JPY' => 15,
            'XAU' => 5,
        ]);
        
        // Calculate total GCU supply
        $totalGcuSupply = \App\Models\AccountBalance::where('asset_code', 'GCU')
            ->sum('balance');
        
        // Create the proposal
        $votingStartDate = Carbon::create($targetMonth->year, $targetMonth->month, 20, 0, 0, 0);
        $votingEndDate = $votingStartDate->copy()->addDays(7);
        
        $proposal = GcuVotingProposal::create([
            'title' => "GCU Composition Vote - {$targetMonth->format('F Y')}",
            'description' => "Monthly voting poll to determine the Global Currency Unit composition for {$targetMonth->format('F Y')}. Vote on the optimal currency basket allocation.",
            'rationale' => "This is the regular monthly composition vote allowing GCU holders to democratically determine the basket weights based on current economic conditions and community consensus.",
            'proposed_composition' => $currentComposition, // Start with current as template
            'current_composition' => $currentComposition,
            'status' => 'active',
            'voting_starts_at' => $votingStartDate,
            'voting_ends_at' => $votingEndDate,
            'minimum_participation' => 10,
            'minimum_approval' => 50,
            'total_gcu_supply' => $totalGcuSupply,
            'created_by' => User::role('admin')->first()?->id,
        ]);
        
        $this->info("Created voting proposal for {$targetMonth->format('F Y')}");
        $this->info("Voting starts: {$votingStartDate->format('Y-m-d H:i')}");
        $this->info("Voting ends: {$votingEndDate->format('Y-m-d H:i')}");
        $this->info("Total GCU Supply: " . number_format($totalGcuSupply / 100, 2));
        
        return 0;
    }
}