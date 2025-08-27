<?php

namespace App\Http\Controllers;

use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianTransfer;
use App\Domain\Custodian\Models\CustodianWebhook;
use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CustodianIntegrationController extends Controller
{
    public function __construct(
        private CustodianHealthMonitor $healthMonitor,
        private CustodianRegistry $custodianRegistry
    ) {
    }

    /**
     * Display custodian integration status dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */

        // Check if user has appropriate permissions
        if (! $user->hasRole(['super_admin', 'bank_admin', 'operations_manager'])) {
            abort(403, 'Unauthorized access to custodian integration status');
        }

        // Get all configured custodians
        $custodians = $this->getCustodiansStatus();

        // Get recent transfers
        $recentTransfers = $this->getRecentTransfers();

        // Get webhook statistics
        $webhookStats = $this->getWebhookStatistics();

        // Get account synchronization status
        $syncStatus = $this->getSynchronizationStatus();

        // Get health metrics
        $healthMetrics = $this->getHealthMetrics();

        return view(
            'custodian-integration.index',
            compact(
                'custodians',
                'recentTransfers',
                'webhookStats',
                'syncStatus',
                'healthMetrics'
            )
        );
    }

    /**
     * Show detailed status for a specific custodian.
     */
    public function show(Request $request, string $custodianCode)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin', 'operations_manager'])) {
            abort(403);
        }

        // Get custodian details
        $custodian = $this->getCustodianDetails($custodianCode);

        if (! $custodian) {
            abort(404, 'Custodian not found');
        }

        // Get custodian accounts
        $accounts = CustodianAccount::where('custodian_code', $custodianCode)
            ->with('account')
            ->get();

        // Get recent transfers for this custodian
        $transfers = CustodianTransfer::where('custodian_code', $custodianCode)
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Get webhook history
        $webhooks = CustodianWebhook::where('custodian', $custodianCode)
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Get health history
        $healthHistory = $this->getHealthHistory($custodianCode);

        return view(
            'custodian-integration.show',
            compact(
                'custodian',
                'accounts',
                'transfers',
                'webhooks',
                'healthHistory'
            )
        );
    }

    /**
     * Test connection to a custodian.
     */
    public function testConnection(Request $request, string $custodianCode)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin'])) {
            abort(403);
        }

        try {
            $connector = $this->custodianRegistry->get($custodianCode);
            $result = $connector->testConnection();

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data'    => $result,
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Trigger manual synchronization.
     */
    public function synchronize(Request $request, string $custodianCode)
    {
        $user = Auth::user();
        /** @var User $user */
        if (! $user->hasRole(['super_admin', 'bank_admin'])) {
            abort(403);
        }

        try {
            // Dispatch synchronization job
            dispatch(new \App\Jobs\SynchronizeCustodianAccounts($custodianCode));

            return redirect()
                ->route('custodian-integration.show', $custodianCode)
                ->with('success', 'Synchronization initiated successfully');
        } catch (Exception $e) {
            return redirect()
                ->route('custodian-integration.show', $custodianCode)
                ->with('error', 'Failed to initiate synchronization: ' . $e->getMessage());
        }
    }

    /**
     * Get custodians status.
     */
    private function getCustodiansStatus(): array
    {
        $custodians = config('custodians', []);
        $status = [];

        foreach ($custodians as $code => $config) {
            $health = $this->healthMonitor->getHealth($code);
            $accounts = CustodianAccount::where('custodian_code', $code)->count();
            $lastSync = Cache::get("custodian_last_sync_{$code}");

            $status[] = [
                'code'         => $code,
                'name'         => $config['name'] ?? $code,
                'type'         => $config['type'] ?? 'unknown',
                'status'       => $health['status'] ?? 'unknown',
                'health_score' => $health['score'] ?? 0,
                'accounts'     => $accounts,
                'last_sync'    => $lastSync,
                'features'     => $config['features'] ?? [],
            ];
        }

        return $status;
    }

    /**
     * Get recent transfers.
     */
    private function getRecentTransfers()
    {
        return CustodianTransfer::with(['sourceAccount', 'destinationAccount'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(
                function ($transfer) {
                    return [
                        'id'          => $transfer->id,
                        'custodian'   => $transfer->custodian_code,
                        'type'        => $transfer->type,
                        'amount'      => $transfer->amount,
                        'currency'    => $transfer->currency,
                        'status'      => $transfer->status,
                        'source'      => $transfer->sourceAccount->name ?? 'External',
                        'destination' => $transfer->destinationAccount->name ?? 'External',
                        'created_at'  => $transfer->created_at,
                    ];
                }
            );
    }

    /**
     * Get webhook statistics.
     */
    private function getWebhookStatistics(): array
    {
        $total = CustodianWebhook::count();
        $processed = CustodianWebhook::where('status', 'processed')->count();
        $failed = CustodianWebhook::where('status', 'failed')->count();
        $pending = CustodianWebhook::where('status', 'pending')->count();

        $recentWebhooks = CustodianWebhook::where('created_at', '>=', now()->subHours(24))
            ->select('custodian', 'event_type')
            ->get()
            ->groupBy('custodian')
            ->map(
                function ($group) {
                    return $group->groupBy('event_type')->map->count();
                }
            );

        return [
            'total'               => $total,
            'processed'           => $processed,
            'failed'              => $failed,
            'pending'             => $pending,
            'success_rate'        => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
            'recent_by_custodian' => $recentWebhooks,
        ];
    }

    /**
     * Get synchronization status.
     */
    private function getSynchronizationStatus(): array
    {
        $custodians = config('custodians', []);
        $status = [];

        foreach (array_keys($custodians) as $code) {
            $lastSync = Cache::get("custodian_last_sync_{$code}");
            $syncErrors = Cache::get("custodian_sync_errors_{$code}", 0);
            $nextSync = Cache::get("custodian_next_sync_{$code}");

            $status[] = [
                'custodian' => $code,
                'last_sync' => $lastSync,
                'next_sync' => $nextSync,
                'errors'    => $syncErrors,
                'status'    => $this->getSyncStatusLabel($lastSync),
            ];
        }

        return $status;
    }

    /**
     * Get health metrics.
     */
    private function getHealthMetrics(): array
    {
        $custodians = config('custodians', []);
        $metrics = [];

        foreach (array_keys($custodians) as $code) {
            $health = $this->healthMonitor->getHealth($code);
            $metrics[$code] = [
                'status'          => $health['status'] ?? 'unknown',
                'score'           => $health['score'] ?? 0,
                'response_time'   => $health['response_time'] ?? null,
                'error_rate'      => $health['error_rate'] ?? 0,
                'circuit_breaker' => $health['circuit_breaker_status'] ?? 'closed',
            ];
        }

        return $metrics;
    }

    /**
     * Get custodian details.
     */
    private function getCustodianDetails(string $custodianCode): ?array
    {
        $config = config("custodians.{$custodianCode}");

        if (! $config) {
            return null;
        }

        $health = $this->healthMonitor->getHealth($custodianCode);

        return [
            'code'          => $custodianCode,
            'name'          => $config['name'] ?? $custodianCode,
            'type'          => $config['type'] ?? 'unknown',
            'base_url'      => $config['base_url'] ?? null,
            'features'      => $config['features'] ?? [],
            'health'        => $health,
            'configuration' => [
                'timeout'                   => $config['timeout'] ?? 30,
                'retry_attempts'            => $config['retry_attempts'] ?? 3,
                'circuit_breaker_threshold' => $config['circuit_breaker_threshold'] ?? 5,
            ],
        ];
    }

    /**
     * Get health history.
     */
    private function getHealthHistory(string $custodianCode): array
    {
        // Get health history from cache or database
        $history = Cache::get("custodian_health_history_{$custodianCode}", []);

        // If no cache, generate mock data for demo
        if (empty($history)) {
            $history = [];
            for ($i = 23; $i >= 0; $i--) {
                $history[] = [
                    'timestamp'     => now()->subHours($i),
                    'status'        => rand(0, 100) > 10 ? 'healthy' : 'degraded',
                    'score'         => rand(70, 100),
                    'response_time' => rand(100, 500),
                ];
            }
        }

        return $history;
    }

    /**
     * Get sync status label.
     */
    private function getSyncStatusLabel($lastSync): string
    {
        if (! $lastSync) {
            return 'never';
        }

        $minutesAgo = now()->diffInMinutes($lastSync);

        if ($minutesAgo < 5) {
            return 'synced';
        } elseif ($minutesAgo < 60) {
            return 'recent';
        } elseif ($minutesAgo < 1440) {
            return 'stale';
        } else {
            return 'outdated';
        }
    }
}
