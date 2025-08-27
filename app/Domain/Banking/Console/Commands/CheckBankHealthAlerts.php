<?php

declare(strict_types=1);

namespace App\Domain\Banking\Console\Commands;

use App\Domain\Custodian\Services\BankAlertingService;
use Exception;
use Illuminate\Console\Command;

class CheckBankHealthAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banks:check-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check bank health and send alerts if necessary';

    public function __construct(
        private readonly BankAlertingService $alertingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking bank health for alerts...');

        try {
            $this->alertingService->performHealthCheck();

            $this->info('Bank health check completed successfully.');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to check bank health: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
