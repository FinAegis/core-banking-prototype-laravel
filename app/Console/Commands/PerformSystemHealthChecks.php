<?php

namespace App\Console\Commands;

use App\Models\SystemHealthCheck;
use App\Models\SystemIncident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerformSystemHealthChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:health-check {--service=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform system health checks and record results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set a reasonable memory limit for health checks
        ini_set('memory_limit', '256M');

        $service = $this->option('service');

        if ($service) {
            $this->checkSpecificService($service);
        } else {
            $this->checkAllServices();
        }

        $this->info('Health checks completed.');

        // Clean up memory
        gc_collect_cycles();
    }

    private function checkAllServices()
    {
        $services = [
            'database' => [$this, 'checkDatabase'],
            'cache'    => [$this, 'checkCache'],
            'queue'    => [$this, 'checkQueue'],
            'storage'  => [$this, 'checkStorage'],
        ];

        // Skip web and API checks in testing environment to save memory
        if (app()->environment('production', 'staging')) {
            $services['web'] = [$this, 'checkWebResponse'];
            $services['api'] = [$this, 'checkApiResponse'];
        }

        $services['email'] = [$this, 'checkEmailService'];

        foreach ($services as $service => $method) {
            $this->performCheck($service, $method);

            // Free memory after each check
            if (app()->environment('testing')) {
                gc_collect_cycles();
            }
        }
    }

    private function checkSpecificService($service)
    {
        $method = 'check' . ucfirst($service);

        if (! method_exists($this, $method)) {
            $this->error("Unknown service: $service");

            return;
        }

        $this->performCheck($service, [$this, $method]);
    }

    private function performCheck($service, callable $method)
    {
        $this->info("Checking $service...");

        try {
            $result = call_user_func($method);

            SystemHealthCheck::create(
                [
                    'service'       => $service,
                    'check_type'    => 'scheduled',
                    'status'        => $result['status'],
                    'response_time' => $result['response_time'] ?? null,
                    'metadata'      => $result['metadata'] ?? null,
                    'error_message' => $result['error_message'] ?? null,
                    'checked_at'    => now(),
                ]
            );

            $this->line("  Status: {$result['status']}");

            if (isset($result['response_time'])) {
                $this->line("  Response time: {$result['response_time']}ms");
            }

            // Check if we need to create or resolve an incident
            $this->manageIncidents($service, $result['status']);
        } catch (\Exception $e) {
            $this->error("  Error checking $service: " . $e->getMessage());

            SystemHealthCheck::create(
                [
                    'service'       => $service,
                    'check_type'    => 'scheduled',
                    'status'        => 'down',
                    'error_message' => $e->getMessage(),
                    'checked_at'    => now(),
                ]
            );

            $this->manageIncidents($service, 'down');
        }
    }

    private function checkDatabase()
    {
        $start = microtime(true);

        try {
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            // Check query performance
            $status = $responseTime > 100 ? 'degraded' : 'operational';

            return [
                'status'        => $status,
                'response_time' => $responseTime,
                'metadata'      => [
                    'connection' => config('database.default'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache()
    {
        try {
            $key = 'health-check-' . time();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);

            if (! $result) {
                return [
                    'status'        => 'degraded',
                    'error_message' => 'Cache write/read test failed',
                ];
            }

            return [
                'status'   => 'operational',
                'metadata' => [
                    'driver' => config('cache.default'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();

            $status = 'operational';
            if ($failedJobs > 100 || $pendingJobs > 1000) {
                $status = 'degraded';
            }

            return [
                'status'   => $status,
                'metadata' => [
                    'failed_jobs'  => $failedJobs,
                    'pending_jobs' => $pendingJobs,
                    'driver'       => config('queue.default'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage()
    {
        try {
            $disk = storage_path();
            $free = disk_free_space($disk);
            $total = disk_total_space($disk);
            $usedPercentage = round(($total - $free) / $total * 100, 2);

            $status = 'operational';
            if ($usedPercentage > 90) {
                $status = 'degraded';
            } elseif ($usedPercentage > 95) {
                $status = 'down';
            }

            return [
                'status'   => $status,
                'metadata' => [
                    'disk_usage_percent' => $usedPercentage,
                    'free_space_gb'      => round($free / 1024 / 1024 / 1024, 2),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkWebResponse()
    {
        try {
            $url = config('app.url');
            $start = microtime(true);

            $response = Http::timeout(10)->get($url);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if (! $response->successful()) {
                return [
                    'status'        => 'down',
                    'response_time' => $responseTime,
                    'error_message' => "HTTP {$response->status()}",
                ];
            }

            $status = 'operational';
            if ($responseTime > 1000) {
                $status = 'degraded';
            }

            return [
                'status'        => $status,
                'response_time' => $responseTime,
                'metadata'      => [
                    'http_status' => $response->status(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkApiResponse()
    {
        try {
            $url = config('app.url') . '/api/status';
            $start = microtime(true);

            $response = Http::timeout(10)->get($url);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if (! $response->successful()) {
                return [
                    'status'        => 'down',
                    'response_time' => $responseTime,
                    'error_message' => "HTTP {$response->status()}",
                ];
            }

            $status = 'operational';
            if ($responseTime > 500) {
                $status = 'degraded';
            }

            return [
                'status'        => $status,
                'response_time' => $responseTime,
                'metadata'      => [
                    'http_status' => $response->status(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function checkEmailService()
    {
        try {
            // Check mail configuration
            $driver = config('mail.default');

            if (! $driver || $driver === 'array') {
                return [
                    'status'   => 'operational',
                    'metadata' => [
                        'driver' => $driver,
                        'note'   => 'Using local/test driver',
                    ],
                ];
            }

            // For production drivers, could implement actual test email
            return [
                'status'   => 'operational',
                'metadata' => [
                    'driver' => $driver,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'        => 'down',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    private function manageIncidents($service, $status)
    {
        // Check for active incidents
        $activeIncident = SystemIncident::where('service', $service)
            ->active()
            ->first();

        if ($status === 'down' && ! $activeIncident) {
            // Create new incident
            $incident = SystemIncident::create(
                [
                    'title'             => ucfirst($service) . ' Service Outage',
                    'description'       => "The {$service} service is experiencing issues and is currently unavailable.",
                    'service'           => $service,
                    'impact'            => 'major',
                    'status'            => 'identified',
                    'started_at'        => now(),
                    'affected_services' => [$service],
                ]
            );

            $incident->addUpdate('identified', "Automated monitoring detected {$service} service is down.");

            Log::error("System incident created for {$service} service");
        } elseif ($status === 'operational' && $activeIncident) {
            // Resolve the incident
            $activeIncident->addUpdate('resolved', "The {$service} service has been restored and is operating normally.");

            Log::info("System incident resolved for {$service} service");
        }
    }
}
