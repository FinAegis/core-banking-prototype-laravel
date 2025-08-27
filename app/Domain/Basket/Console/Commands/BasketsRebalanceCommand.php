<?php

namespace App\Domain\Basket\Console\Commands;

use App\Domain\Basket\Services\BasketRebalancingService;
use App\Domain\Governance\Models\GcuVotingProposal;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BasketsRebalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baskets:rebalance {--dry-run : Show what would be rebalanced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebalance dynamic baskets including GCU based on voting results';

    /**
     * Execute the console command.
     */
    public function handle(BasketRebalancingService $rebalancingService)
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        // Find the most recent closed voting proposal that passed
        $proposal = GcuVotingProposal::where('status', 'closed')
            ->where('voting_ends_at', '<=', now())
            ->orderBy('voting_ends_at', 'desc')
            ->first();

        if (! $proposal) {
            $this->info('No closed voting proposals found.');

            return 0;
        }

        // Check if it passed
        if (! $proposal->hasPassed()) {
            $this->info("Latest proposal '{$proposal->title}' did not pass.");
            $this->info("Participation: {$proposal->participation_rate}% (required: {$proposal->minimum_participation}%)");
            $this->info("Approval: {$proposal->approval_rate}% (required: {$proposal->minimum_approval}%)");

            return 0;
        }

        // Check if already implemented
        if ($proposal->status === 'implemented') {
            $this->info("Proposal '{$proposal->title}' has already been implemented.");

            return 0;
        }

        $this->info("Implementing proposal: {$proposal->title}");
        $this->info('New composition:');
        foreach ($proposal->proposed_composition as $currency => $percentage) {
            $this->info("  {$currency}: {$percentage}%");
        }

        if ($isDryRun) {
            $this->info("\nDRY RUN complete - no changes made.");

            return 0;
        }

        try {
            DB::transaction(
                function () use ($proposal, $rebalancingService) {
                    // Update GCU composition in config/database
                    $this->updateGcuComposition($proposal->proposed_composition);

                    // Trigger basket rebalancing
                    $rebalancingService->rebalanceAllDynamicBaskets();

                    // Mark proposal as implemented
                    $proposal->update(
                        [
                        'status'                 => 'implemented',
                        'implemented_at'         => now(),
                        'implementation_details' => [
                        'executed_by'          => 'system',
                        'execution_time'       => now()->toDateTimeString(),
                        'previous_composition' => $proposal->current_composition,
                        ],
                        ]
                    );
                }
            );

            $this->info("\nSuccessfully implemented proposal and rebalanced baskets.");
        } catch (Exception $e) {
            $this->error("Failed to implement proposal: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }

    /**
     * Update GCU composition in the system.
     */
    protected function updateGcuComposition(array $composition)
    {
        // In a real system, this would update a database table or configuration
        // For now, we'll just log the change
        $this->info("\nUpdating GCU composition in system configuration...");

        // You could update a settings table, cache, or trigger events here
        // Example: Settings::set('gcu.composition', $composition);
    }
}
