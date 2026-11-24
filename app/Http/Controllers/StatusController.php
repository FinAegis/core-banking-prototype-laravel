<?php

namespace App\Http\Controllers;

use App\Models\SystemHealthCheck;
use App\Models\SystemIncident;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(
    name: 'System Status',
    description: 'System status and health monitoring'
)]
class StatusController extends Controller
{
    /**
     * Services checked live when the page is viewed. The results are cached
     * for LIVE_CHECK_CACHE_SECONDS and never written to
     * system_health_checks; only the scheduled command writes rows.
     */
    private const LIVE_CHECKED = ['database', 'cache', 'queue', 'storage'];

    /**
     * Services checked by the scheduled system:health-check command. That
     * command runs every five minutes in production and staging only.
     */
    private const SCHEDULE_CHECKED = ['web', 'api'];

    /**
     * A scheduled result older than this is too old to show as the status.
     */
    private const SCHEDULED_CHECK_MAX_AGE_MINUTES = 15;

    private const LIVE_CHECK_CACHE_KEY = 'status-page:live-checks';

    private const LIVE_CHECK_CACHE_SECONDS = 60;

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
        $services = $this->getServicesStatus($status['checks']);
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
                'services'  => $this->getServicesStatus($status['checks']),
                'uptime'    => $this->calculateUptime(),
                'incidents' => $this->getRecentIncidents(true),
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * @return array{overall: string, checks: array<string, array<string, mixed>>, response_time: float|null, last_checked: Carbon}
     */
    private function checkSystemStatus(): array
    {
        $names = collect($this->serviceDefinitions())->map(fn (array $service): string => $service['name']);

        $live = $this->liveChecks();

        $checks = [];
        foreach ($live['checks'] as $service => $check) {
            $checks[$service] = ['name' => $names[$service]] + $check;
        }

        // Add the recent results of the scheduled checks. Older results and
        // rows for services without a real check are left out.
        foreach (self::SCHEDULE_CHECKED as $service) {
            $healthCheck = $this->recentScheduledCheck($service);
            if ($healthCheck === null) {
                continue;
            }

            $checks[$service] = [
                'name'          => $names[$service],
                'status'        => $healthCheck->status,
                'response_time' => $healthCheck->response_time,
                'message'       => match ($healthCheck->status) {
                    'operational' => 'The last automatic check passed.',
                    'degraded'    => 'The last automatic check found slow answers.',
                    default       => 'The last automatic check failed.',
                },
            ];
        }

        $allOperational = collect($checks)->every(fn ($check) => $check['status'] === 'operational');
        $hasDown = collect($checks)->contains(fn ($check) => $check['status'] === 'down');

        return [
            'overall'       => $hasDown ? 'down' : ($allOperational ? 'operational' : 'degraded'),
            'checks'        => $checks,
            'response_time' => $live['checks']['database']['response_time'] ?? null,
            'last_checked'  => Carbon::createFromTimestamp($live['checked_at']),
        ];
    }

    /**
     * The live checks, reused for LIVE_CHECK_CACHE_SECONDS so that page views
     * do not query the database, cache, queue and disk every time. Page views
     * never write health check rows.
     *
     * @return array{checked_at: int, checks: array<string, array<string, mixed>>}
     */
    private function liveChecks(): array
    {
        try {
            /** @var array{checked_at: int, checks: array<string, array<string, mixed>>} $live */
            $live = Cache::remember(
                self::LIVE_CHECK_CACHE_KEY,
                self::LIVE_CHECK_CACHE_SECONDS,
                fn (): array => $this->runLiveChecks()
            );

            return $live;
        } catch (Throwable) {
            // The cache store itself failed. Check without it; the cache
            // check below then reports the problem.
            return $this->runLiveChecks();
        }
    }

    /**
     * @return array{checked_at: int, checks: array<string, array<string, mixed>>}
     */
    private function runLiveChecks(): array
    {
        return [
            'checked_at' => now()->getTimestamp(),
            'checks'     => [
                'database' => $this->checkDatabase(),
                'cache'    => $this->checkCache(),
                'queue'    => $this->checkQueue(),
                'storage'  => $this->checkStorage(),
            ],
        ];
    }

    private function recentScheduledCheck(string $service): ?SystemHealthCheck
    {
        /** @var SystemHealthCheck|null $check */
        $check = SystemHealthCheck::where('service', $service)
            ->where('checked_at', '>=', now()->subMinutes(self::SCHEDULED_CHECK_MAX_AGE_MINUTES))
            ->orderBy('checked_at', 'desc')
            ->first();

        return $check;
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

    /**
     * The services shown on the page. Only the services in LIVE_CHECKED and
     * SCHEDULE_CHECKED have a real check. Email is not one of them: the
     * scheduled command only reads the mail configuration.
     *
     * @return array<string, array{name: string, description: string, category: string}>
     */
    private function serviceDefinitions(): array
    {
        return [
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
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     * @return array<int, array{key: string, name: string, description: string, category: string, status: string, uptime: string|null}>
     */
    private function getServicesStatus(array $checks): array
    {
        $result = [];
        foreach ($this->serviceDefinitions() as $serviceKey => $serviceInfo) {
            if (! in_array($serviceKey, [...self::LIVE_CHECKED, ...self::SCHEDULE_CHECKED], true)) {
                // Nothing checks this service, so the page makes no claim about it.
                $status = 'not_monitored';
                $uptime = null;
            } else {
                // A scheduled service without a recent result has no current status.
                $status = $checks[$serviceKey]['status'] ?? 'unknown';
                $uptime = SystemHealthCheck::calculateUptime($serviceKey, 30);
            }

            $result[] = [
                'key'         => $serviceKey,
                'name'        => $serviceInfo['name'],
                'description' => $serviceInfo['description'],
                'category'    => $serviceInfo['category'],
                'status'      => $status,
                'uptime'      => $uptime === null ? null : $uptime . '%',
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

    /**
     * Share of passed checks over 30 days, counted only for services with a
     * real check. Without any recorded check there is no figure.
     *
     * @return array{percentage: float|null, period: string, checks_total: int, checks_failed: int}
     */
    private function calculateUptime(): array
    {
        $monitored = [...self::LIVE_CHECKED, ...self::SCHEDULE_CHECKED];
        $since = now()->subDays(30);

        $totalChecks = SystemHealthCheck::whereIn('service', $monitored)
            ->where('checked_at', '>=', $since)
            ->count();
        $failedChecks = SystemHealthCheck::whereIn('service', $monitored)
            ->where('checked_at', '>=', $since)
            ->whereIn('status', ['degraded', 'down'])
            ->count();

        return [
            'percentage'    => $totalChecks > 0 ? round(($totalChecks - $failedChecks) / $totalChecks * 100, 2) : null,
            'period'        => '30 days',
            'checks_total'  => $totalChecks,
            'checks_failed' => $failedChecks,
        ];
    }
}
