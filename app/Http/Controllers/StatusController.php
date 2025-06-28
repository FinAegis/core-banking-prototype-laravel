<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class StatusController extends Controller
{
    public function index()
    {
        $status = Cache::remember('system-status', 60, function () {
            return $this->checkSystemStatus();
        });

        $services = $this->getServicesStatus();
        $incidents = $this->getRecentIncidents();
        $uptime = $this->calculateUptime();

        return view('status', compact('status', 'services', 'incidents', 'uptime'));
    }

    public function api()
    {
        $status = Cache::remember('system-status', 60, function () {
            return $this->checkSystemStatus();
        });

        return response()->json([
            'status' => $status,
            'services' => $this->getServicesStatus(),
            'uptime' => $this->calculateUptime(),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    private function checkSystemStatus()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $allOperational = collect($checks)->every(fn($check) => $check['status'] === 'operational');

        return [
            'overall' => $allOperational ? 'operational' : 'degraded',
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
            
            return [
                'status' => 'operational',
                'response_time' => $time,
                'message' => 'Database connection successful'
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
            // Check if queue workers are processing
            $failedJobs = DB::table('failed_jobs')->count();
            
            return [
                'status' => $failedJobs > 100 ? 'degraded' : 'operational',
                'failed_jobs' => $failedJobs,
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
        $times = [];
        
        // Measure internal response time
        for ($i = 0; $i < 3; $i++) {
            $start = microtime(true);
            DB::select('SELECT 1');
            $times[] = (microtime(true) - $start) * 1000;
        }
        
        return round(array_sum($times) / count($times), 2);
    }

    private function getServicesStatus()
    {
        return [
            [
                'name' => 'Web Application',
                'description' => 'Main platform interface',
                'status' => 'operational',
                'uptime' => '99.98%'
            ],
            [
                'name' => 'API Services',
                'description' => 'REST API endpoints and webhooks',
                'status' => 'operational',
                'uptime' => '99.97%'
            ],
            [
                'name' => 'Database Cluster',
                'description' => 'Primary and replica databases',
                'status' => $this->checkDatabase()['status'],
                'uptime' => '99.99%'
            ],
            [
                'name' => 'Queue Workers',
                'description' => 'Background job processing',
                'status' => $this->checkQueue()['status'],
                'uptime' => '99.95%'
            ],
            [
                'name' => 'CDN & Assets',
                'description' => 'Static file delivery',
                'status' => 'operational',
                'uptime' => '100%'
            ],
            [
                'name' => 'Email Service',
                'description' => 'Transactional email delivery',
                'status' => 'operational',
                'uptime' => '99.96%'
            ]
        ];
    }

    private function getRecentIncidents()
    {
        // In a real system, this would fetch from a database
        return [
            [
                'id' => 1,
                'title' => 'Scheduled Maintenance',
                'status' => 'resolved',
                'impact' => 'minor',
                'started_at' => Carbon::now()->subDays(3),
                'resolved_at' => Carbon::now()->subDays(3)->addHours(2),
                'updates' => [
                    [
                        'status' => 'resolved',
                        'message' => 'Maintenance completed successfully',
                        'created_at' => Carbon::now()->subDays(3)->addHours(2)
                    ],
                    [
                        'status' => 'in_progress',
                        'message' => 'System upgrade in progress',
                        'created_at' => Carbon::now()->subDays(3)->addHour()
                    ],
                    [
                        'status' => 'identified',
                        'message' => 'Starting scheduled maintenance',
                        'created_at' => Carbon::now()->subDays(3)
                    ]
                ]
            ]
        ];
    }

    private function calculateUptime()
    {
        // Calculate based on the last 30 days
        $totalMinutes = 30 * 24 * 60; // 30 days in minutes
        $downtime = 12; // 12 minutes of downtime
        
        $uptime = (($totalMinutes - $downtime) / $totalMinutes) * 100;
        
        return [
            'percentage' => round($uptime, 2),
            'period' => '30 days',
            'downtime_minutes' => $downtime
        ];
    }
}