<?php

namespace App\Domain\Webhook\Console\Commands;

use App\Domain\Custodian\Jobs\ProcessCustodianWebhook;
use App\Domain\Custodian\Models\CustodianWebhook;
use Exception;
use Illuminate\Console\Command;

class RetryFailedWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:retry-failed 
                            {--max-attempts=3 : Maximum number of attempts}
                            {--custodian= : Only retry webhooks from specific custodian}
                            {--limit=100 : Maximum number of webhooks to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed custodian webhooks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxAttempts = (int) $this->option('max-attempts');
        $custodian = $this->option('custodian');
        $limit = (int) $this->option('limit');

        $query = CustodianWebhook::retryable($maxAttempts);

        if ($custodian) {
            $query->where('custodian_name', $custodian);
        }

        $webhooks = $query->limit($limit)->get();

        if ($webhooks->isEmpty()) {
            $this->info('No failed webhooks found to retry.');

            return Command::SUCCESS;
        }

        $this->info("Found {$webhooks->count()} webhooks to retry.");

        $bar = $this->output->createProgressBar($webhooks->count());
        $bar->start();

        $dispatched = 0;
        foreach ($webhooks as $webhook) {
            try {
                // Reset status to pending for retry
                $webhook->update(['status' => 'pending']);

                // Dispatch job to process webhook
                dispatch(new ProcessCustodianWebhook($webhook->uuid));

                $dispatched++;
            } catch (Exception $e) {
                $this->error("\nFailed to dispatch webhook {$webhook->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Successfully dispatched {$dispatched} webhooks for retry.");

        return Command::SUCCESS;
    }
}
