<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\SystemHealthCheck;
use App\Models\SystemIncident;
use Carbon\Carbon;

class StatusController extends Controller
{
    public function index()
    {
        $status = $this->checkSystemStatus();
        $services = $this->getServicesStatus();
        $incidents = $this->getRecentIncidents();
        $uptime = $this->calculateUptime();

        return view('status', compact('status', 'services', 'incidents', 'uptime'));
    }

    public function api()
    {
        $status = $this->checkSystemStatus();

        return response()->json([
            'status' => $status,
            'services' => $this->getServicesStatus(),
            'uptime' => $this->calculateUptime(),
            'incidents' => $this->getRecentIncidents(true),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    private function checkSystemStatus()
    {
        // Get latest health checks from database
        $latestChecks = SystemHealthCheck::getLatestStatuses();
        
        // Perform real-time checks for critical services
        $realtimeChecks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];
        
        // Merge with database records
        $checks = [];
        foreach ($realtimeChecks as $service => $check) {
            $checks[$service] = $check;
            
            // Record the check
            SystemHealthCheck::create([
                'service' => $service,
                'check_type' => 'realtime',
                'status' => $check['status'],
                'response_time' => $check['response_time'] ?? null,
                'error_message' => $check['status'] !== 'operational' ? ($check['message'] ?? null) : null,
                'checked_at' => now(),
            ]);
        }
        
        // Add checks from database for services not checked in real-time
        foreach ($latestChecks as $service => $healthCheck) {
            if (!isset($checks[$service])) {
                $checks[$service] = [
                    'status' => $healthCheck->status,
                    'response_time' => $healthCheck->response_time,
                    'message' => $healthCheck->error_message ?? 'Service status from monitoring',
                ];
            }
        }

        $allOperational = collect($checks)->every(fn($check) => $check['status'] === 'operational');
        $hasDown = collect($checks)->contains(fn($check) => $check['status'] === 'down');

        return [
            'overall' => $hasDown ? 'down' : ($allOperational ? 'operational' : 'degraded'),
            'checks' => $checks,
            'response_time' => $this->measureResponseTime(),
            'last_checked' => now()
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
                'status' => $status,
                'response_time' => $time,
                'message' => $status === 'operational' ? 'Database connection successful' : 'Database response slow'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'response_time' => null,
                'message' => 'Database connection failed'
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
                'status' => $result ? 'operational' : 'degraded',
                'message' => $result ? 'Cache working properly' : 'Cache issues detected'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'message' => 'Cache service unavailable'
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
                'status' => $status,
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
                'message' => $failedJobs > 0 ? "$failedJobs failed jobs" : 'Queue processing normally'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'message' => 'Unable to check queue status'
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
                'status' => $used_percentage > 90 ? 'degraded' : 'operational',
                'usage' => $used_percentage . '%',
                'message' => "Disk usage: $used_percentage%"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'message' => 'Unable to check storage'
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
                'name' => 'Web Application',
                'description' => 'Main platform interface',
            ],
            'api' => [
                'name' => 'API Services',
                'description' => 'REST API endpoints and webhooks',
            ],
            'database' => [
                'name' => 'Database Cluster',
                'description' => 'Primary and replica databases',
            ],
            'queue' => [
                'name' => 'Queue Workers',
                'description' => 'Background job processing',
            ],
            'cache' => [
                'name' => 'CDN & Cache',
                'description' => 'Content delivery and caching',
            ],
            'email' => [
                'name' => 'Email Service',
                'description' => 'Transactional email delivery',
            ]
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
                'name' => $serviceInfo['name'],
                'description' => $serviceInfo['description'],
                'status' => $status,
                'uptime' => $uptime . '%'
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
            return $incidents->map(function ($incident) {
                return [
                    'id' => $incident->id,
                    'title' => $incident->title,
                    'status' => $incident->status,
                    'impact' => $incident->impact,
                    'started_at' => $incident->started_at->toIso8601String(),
                    'resolved_at' => $incident->resolved_at?->toIso8601String(),
                    'duration' => $incident->duration,
                    'updates' => $incident->updates->map(function ($update) {
                        return [
                            'status' => $update->status,
                            'message' => $update->message,
                            'created_at' => $update->created_at->toIso8601String(),
                        ];
                    }),
                ];
            });
        }
        
        return $incidents;
    }

    private function calculateUptime()
    {
        // Calculate overall platform uptime based on all services
        $services = ['web', 'api', 'database', 'queue', 'cache', 'email'];
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
            'percentage' => round($overallUptime, 2),
            'period' => '30 days',
            'downtime_minutes' => $downtimeMinutes
        ];
    }
}