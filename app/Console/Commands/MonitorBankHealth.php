<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Custodian\Services\CustodianHealthMonitor;
use Illuminate\Console\Command;

class MonitorBankHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banks:monitor-health 
                            {--interval=10 : Check interval in seconds}
                            {--custodian= : Monitor specific custodian only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor real-time health status of bank connectors';

    public function __construct(
        private readonly CustodianHealthMonitor $healthMonitor
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $specificCustodian = $this->option('custodian');
        
        $this->info('Starting real-time bank health monitoring...');
        $this->info("Check interval: {$interval} seconds");
        
        if ($specificCustodian) {
            $this->info("Monitoring custodian: {$specificCustodian}");
        }
        
        $this->info('Press Ctrl+C to stop monitoring');
        $this->newLine();
        
        while (true) {
            $this->displayHealthStatus($specificCustodian);
            
            if (!$this->runningInBackground()) {
                sleep($interval);
                // Clear screen for better readability
                $this->clearScreen();
            } else {
                // If running in background, exit after one iteration
                break;
            }
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Display health status for all or specific custodian
     */
    private function displayHealthStatus(?string $specificCustodian): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->info("=== Bank Health Status at {$timestamp} ===");
        $this->newLine();
        
        if ($specificCustodian) {
            $health = $this->healthMonitor->getCustodianHealth($specificCustodian);
            $this->displayCustodianHealth($health);
        } else {
            $allHealth = $this->healthMonitor->getAllCustodiansHealth();
            
            foreach ($allHealth as $custodian => $health) {
                $this->displayCustodianHealth($health);
                $this->newLine();
            }
        }
        
        // Display recommendations
        $this->displayRecommendations($specificCustodian);
    }
    
    /**
     * Display individual custodian health
     */
    private function displayCustodianHealth(array $health): void
    {
        $custodian = $health['custodian'];
        $status = $health['status'];
        $available = $health['available'] ? 'YES' : 'NO';
        $failureRate = $health['overall_failure_rate'] ?? 0;
        
        // Color code based on status
        $statusColor = match ($status) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy' => 'red',
            default => 'gray',
        };
        
        $this->line("<fg=white;bg=blue> {$custodian} </>");
        $this->line("Status: <fg={$statusColor}>{$status}</>");
        $this->line("Available: {$available}");
        $this->line("Failure Rate: {$failureRate}%");
        
        // Display circuit breaker metrics if available
        if (isset($health['circuit_breaker_metrics'])) {
            $this->line('Circuit Breakers:');
            foreach ($health['circuit_breaker_metrics'] as $operation => $metrics) {
                $state = $metrics['state'];
                $stateColor = match ($state) {
                    'closed' => 'green',
                    'open' => 'red',
                    'half_open' => 'yellow',
                    default => 'gray',
                };
                
                $this->line("  - {$operation}: <fg={$stateColor}>{$state}</> (failures: {$metrics['failure_count']})");
            }
        }
        
        // Display recommendations if any
        if (!empty($health['recommendations'])) {
            $this->line('Recommendations:');
            foreach ($health['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }
    }
    
    /**
     * Display overall recommendations
     */
    private function displayRecommendations(?string $specificCustodian): void
    {
        if (!$specificCustodian) {
            $this->newLine();
            $this->line('<fg=cyan>Overall Recommendations:</>');
            
            // Get healthiest custodian for common currencies
            $currencies = ['USD', 'EUR', 'GBP'];
            foreach ($currencies as $currency) {
                $healthiest = $this->healthMonitor->getHealthiestCustodian($currency);
                if ($healthiest) {
                    $this->line("  • Best custodian for {$currency}: <fg=green>{$healthiest}</>");
                }
            }
        }
    }
    
    /**
     * Clear console screen
     */
    private function clearScreen(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            system('cls');
        } else {
            // Unix/Linux/Mac
            system('clear');
        }
    }
    
    /**
     * Check if running in background
     */
    private function runningInBackground(): bool
    {
        return !posix_isatty(STDOUT);
    }
}