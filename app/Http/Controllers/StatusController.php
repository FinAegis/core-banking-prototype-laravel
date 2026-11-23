<?php

namespace App\Http\Controllers;

use App\Models\SystemHealthCheck;
use App\Models\SystemIncident;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'System Status',
    description: 'System status and health monitoring'
)]
class StatusController extends Controller
{
        #[OA\Get(
            path: '/status',
            operationId: 'systemStatusIndex',
            tags: ['System Status'],
            summary: 'System status page',
            description: 'Returns the system status page'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function index()
    {
        $status = $this->checkSystemStatus();
        $services = $this->getServicesStatus();
        $incidents = $this->getRecentIncidents();
        $uptime = $this->calculateUptime();

        return view('status', compact('status', 'services', 'incidents', 'uptime'));
    }

        #[OA\Get(
            path: '/status/api',
            operationId: 'systemStatusApi',
            tags: ['System Status'],
            summary: 'API status endpoint',
            description: 'Returns the API health status as JSON'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function api()
    {
        $status = $this->checkSystemStatus();

        return response()->json(
            [
                'status'    => $status,
                'services'  => $this->getServicesStatus(),
                'uptime'    => $this->calculateUptime(),
                'incidents' => $this->getRecentIncidents(true),
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }

    private function checkSystemStatus()
    {
        // Get latest health checks from database
        $latestChecks = SystemHealthCheck::getLatestStatuses();

        // Perform real-time checks for critical services
        $realtimeChecks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
            'storage'  => $this->checkStorage(),
        ];

        // Merge with database records
        $checks = [];
        foreach ($realtimeChecks as $service => $check) {
            $checks[$service] = $check;

            // Record the check
            SystemHealthCheck::create(
                [
                    'service'       => $service,
                    'check_type'    => 'realtime',
                    'status'        => $check['status'],
                    'response_time' => $check['response_time'] ?? null,
                    'error_message' => $check['status'] !== 'operational' ? ($check['message'] ?? null) : null,
                    'checked_at'    => now(),
                ]
            );
        }

        // Add checks from database for services not checked in real-time
        foreach ($latestChecks as $service => $healthCheck) {
            if (! isset($checks[$service])) {
                $checks[$service] = [
                    'status'        => $healthCheck->status,
                    'response_time' => $healthCheck->response_time,
                    'message'       => $healthCheck->error_message ?? 'Status from the last monitoring check.',
                ];
            }
        }

        $allOperational = collect($checks)->every(fn ($check) => $check['status'] === 'operational');
        $hasDown = collect($checks)->contains(fn ($check) => $check['status'] === 'down');

        return [
            'overall'       => $hasDown ? 'down' : ($allOperational ? 'operational' : 'degraded'),
            'checks'        => $checks,
            'response_time' => $this->measureResponseTime(),
            'last_checked'  => now(),
        ];
    }

    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $time = round((microtime(true) - $start) * 1000, 2);

            $status = $time > 100 ? 'degraded' : 'operational';

            return [
                'status'        => $status,
                'response_time' => $time,
                'message'       => $status === 'operational' ? 'The database is answering normally.' : 'The database is answering slowly.',
            ];
        } catch (Exception $e) {
            return [
                'status'        => 'down',
                'response_time' => null,
                'message'       => 'The database is not answering.',
            ];
        }
    }

    private function checkCache()
    {
        try {
            $key = 'status-check-' . time();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);

            return [
                'status'  => $result ? 'operational' : 'degraded',
                'message' => $result ? 'The cache is working normally.' : 'The cache has problems.',
            ];
        } catch (Exception $e) {
            return [
                'status'  => 'down',
                'message' => 'The cache is not available.',
            ];
        }
    }

    private function checkQueue()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = 0;

            // Try to count pending jobs if the table exists
            if (Schema::hasTable('jobs')) {
                $pendingJobs = DB::table('jobs')->count();
            }

            $status = 'operational';
            if ($failedJobs > 100 || $pendingJobs > 1000) {
                $status = 'degraded';
            }

            return [
                'status'       => $status,
                'failed_jobs'  => $failedJobs,
                'pending_jobs' => $pendingJobs,
                'message'      => $failedJobs > 0 ? "$failedJobs background tasks failed." : 'Background tasks are running normally.',
            ];
        } catch (Exception $e) {
            return [
                'status'  => 'unknown',
                'message' => 'We could not check the background tasks.',
            ];
        }
    }

    private function checkStorage()
    {
        try {
            $disk = storage_path();
            $free = disk_free_space($disk);
            $total = disk_total_space($disk);
            $used_percentage = round(($total - $free) / $total * 100, 2);

            return [
                'status'  => $used_percentage > 90 ? 'degraded' : 'operational',
                'usage'   => $used_percentage . '%',
                'message' => "$used_percentage% of the disk is in use.",
            ];
        } catch (Exception $e) {
            return [
                'status'  => 'unknown',
                'message' => 'We could not check the file storage.',
            ];
        }
    }

    private function measureResponseTime()
    {
        // Get average response time from recent health checks
        $avgResponseTime = SystemHealthCheck::where('service', 'database')
            ->where('checked_at', '>=', now()->subHour())
            ->whereNotNull('response_time')
            ->avg('response_time');

        return round($avgResponseTime ?? 0, 2);
    }

    private function getServicesStatus()
    {
        $services = [
            'web' => [
                'name'        => 'Website',
                'description' => 'The website and the account pages you open in a browser.',
                'category'    => 'Core services',
            ],
            'auth' => [
                'name'        => 'Sign-in',
                'description' => 'Signing in, signing up and staying signed in.',
                'category'    => 'Core services',
            ],
            'api' => [
                'name'        => 'App interface',
                'description' => 'The interface that the mobile app and partner services call.',
                'category'    => 'Interfaces',
            ],
            'graphql' => [
                'name'        => 'Query interface',
                'description' => 'A second interface where apps ask for exactly the data they need.',
                'category'    => 'Interfaces',
            ],
            'x402' => [
                'name'        => 'Pay-per-request payments',
                'description' => 'Lets software pay a small amount for a single web request.',
                'category'    => 'Interfaces',
            ],
            'database' => [
                'name'        => 'Database',
                'description' => 'Stores accounts and their records.',
                'category'    => 'Infrastructure',
            ],
            'cache' => [
                'name'        => 'Cache',
                'description' => 'Keeps often used data in fast memory.',
                'category'    => 'Infrastructure',
            ],
            'queue' => [
                'name'        => 'Background tasks',
                'description' => 'Runs tasks in the background, such as sending emails and notifications.',
                'category'    => 'Infrastructure',
            ],
            'storage' => [
                'name'        => 'File storage',
                'description' => 'Stores uploaded and generated files.',
                'category'    => 'Infrastructure',
            ],
            'email' => [
                'name'        => 'Email',
                'description' => 'Sends account emails, such as sign-in codes and confirmations.',
                'category'    => 'Connected services',
            ],
            'blockchain' => [
                'name'        => 'Blockchain networks',
                'description' => 'Connections to the blockchain networks that the wallet uses.',
                'category'    => 'Connected services',
            ],
        ];

        $result = [];
        foreach ($services as $serviceKey => $serviceInfo) {
            // Get latest status from health checks
            $latestCheck = SystemHealthCheck::where('service', $serviceKey)
                ->orderBy('checked_at', 'desc')
                ->first();

            $status = $latestCheck ? $latestCheck->status : 'operational';
            $uptime = SystemHealthCheck::calculateUptime($serviceKey, 30);

            $result[] = [
                'name'        => $serviceInfo['name'],
                'description' => $serviceInfo['description'],
                'category'    => $serviceInfo['category'],
                'status'      => $status,
                'uptime'      => $uptime . '%',
            ];
        }

        return $result;
    }

    private function getRecentIncidents($forApi = false)
    {
        $incidents = SystemIncident::with('updates')
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get();

        if ($forApi) {
            $result = [];
            foreach ($incidents as $incident) {
                $updates = [];
                foreach ($incident->updates as $update) {
                    $updates[] = [
                        'status'     => $update->status,
                        'message'    => $update->message,
                        'created_at' => $update->created_at->toIso8601String(),
                    ];
                }

                $result[] = [
                    'id'          => $incident->id,
                    'title'       => $incident->title,
                    'status'      => $incident->status,
                    'impact'      => $incident->impact,
                    'started_at'  => $incident->started_at->toIso8601String(),
                    'resolved_at' => $incident->resolved_at?->toIso8601String(),
                    'duration'    => $incident->duration,
                    'updates'     => $updates,
                ];
            }

            return $result;
        }

        return $incidents;
    }

    private function calculateUptime()
    {
        // Calculate overall platform uptime based on all services
        $services = ['web', 'auth', 'api', 'graphql', 'x402', 'database', 'cache', 'queue', 'storage', 'email', 'blockchain'];
        $totalUptime = 0;
        $serviceCount = 0;

        foreach ($services as $service) {
            $uptime = SystemHealthCheck::calculateUptime($service, 30);
            if ($uptime > 0) {
                $totalUptime += $uptime;
                $serviceCount++;
            }
        }

        $overallUptime = $serviceCount > 0 ? ($totalUptime / $serviceCount) : 100;

        // Calculate total downtime minutes
        $totalChecks = SystemHealthCheck::where('checked_at', '>=', now()->subDays(30))->count();
        $failedChecks = SystemHealthCheck::where('checked_at', '>=', now()->subDays(30))
            ->whereIn('status', ['degraded', 'down'])
            ->count();

        // Assuming checks run every minute
        $downtimeMinutes = $failedChecks;

        return [
            'percentage'       => round($overallUptime, 2),
            'period'           => '30 days',
            'downtime_minutes' => $downtimeMinutes,
        ];
    }
}
