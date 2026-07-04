<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services\Certification;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\ComplianceChangeLog;
use App\Domain\Compliance\Models\ComplianceEvidence;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SOC 2 Evidence Collection Service.
 *
 * Collects, stores, and retrieves compliance evidence for SOC 2 audit periods.
 * Supports access logs, configuration snapshots, and change management evidence
 * with SHA-256 integrity hashing for tamper detection.
 */
class EvidenceCollectionService
{
    /**
     * Collect evidence for a given audit period.
     *
     * @param string $period The audit period identifier (e.g. '2026-Q1')
     * @param string $type   Evidence type to collect: 'all', 'access', 'config', or 'change_management'
     *
     * @return array<string, mixed>
     */
    public function collectEvidence(string $period, string $type = 'all'): array
    {
        if (config('compliance-certification.soc2.demo_mode', false)) {
            if (app()->environment('production')) {
                throw new RuntimeException('SOC 2 demo evidence is disabled in production: SOC2_DEMO_MODE=true would return fabricated audit data stamped as production. Set SOC2_DEMO_MODE=false and use real evidence collection.');
            }

            return $this->getDemoEvidence($period);
        }

        $evidence = [];

        if ($type === 'all' || $type === 'access') {
            $accessData = $this->collectAccessEvidence($period);
            $evidence['access'] = $this->storeEvidence('access', $period, $accessData);
        }

        if ($type === 'all' || $type === 'config') {
            $configData = $this->collectConfigSnapshot();
            $evidence['config'] = $this->storeEvidence('config_snapshot', $period, $configData);
        }

        if ($type === 'all' || $type === 'change_management') {
            $changeData = $this->collectChangeLogEvidence($period);
            $evidence['change_management'] = $this->storeEvidence('change_management', $period, $changeData);
        }

        Log::info('SOC 2 evidence collected', [
            'period'         => $period,
            'type'           => $type,
            'evidence_types' => array_keys($evidence),
        ]);

        return $evidence;
    }

    /**
     * Retrieve stored evidence with optional filters.
     *
     * @param string|null $period Filter by audit period
     * @param string|null $type   Filter by evidence type
     *
     * @return Collection<int, ComplianceEvidence>
     */
    public function getEvidence(?string $period = null, ?string $type = null): Collection
    {
        $query = ComplianceEvidence::query();

        if ($period !== null) {
            $query->forPeriod($period);
        }

        if ($type !== null) {
            $query->forType($type);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Collect access evidence from audit logs for a period.
     *
     * Queries AuditLog entries within the period date range and summarizes
     * access patterns by user and action type.
     *
     * @param string $period The audit period identifier (e.g. '2026-Q1')
     *
     * @return array<string, mixed>
     */
    public function collectAccessEvidence(string $period): array
    {
        [$startDate, $endDate] = $this->parsePeriodDates($period);

        $logs = AuditLog::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        $byUser = $logs->groupBy('user_uuid')->map(function (Collection $userLogs) {
            return [
                'action_count'   => $userLogs->count(),
                'actions'        => $userLogs->groupBy('action')->map->count()->toArray(),
                'first_activity' => $userLogs->min('created_at')?->toIso8601String(),
                'last_activity'  => $userLogs->max('created_at')?->toIso8601String(),
            ];
        })->toArray();

        $byAction = $logs->groupBy('action')->map->count()->toArray();

        return [
            'period'       => $period,
            'total_events' => $logs->count(),
            'unique_users' => $logs->unique('user_uuid')->count(),
            'by_user'      => $byUser,
            'by_action'    => $byAction,
            'collected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Capture a snapshot of security-relevant configuration values.
     *
     * @return array<string, mixed>
     */
    public function collectConfigSnapshot(): array
    {
        return [
            'app_debug'            => config('app.debug'),
            'app_env'              => config('app.env'),
            'session_lifetime'     => config('session.lifetime'),
            'session_driver'       => config('session.driver'),
            'session_encrypt'      => config('session.encrypt'),
            'auth_defaults'        => config('auth.defaults'),
            'auth_guards'          => array_keys(config('auth.guards', [])),
            'auth_providers'       => array_keys(config('auth.providers', [])),
            'hashing_driver'       => config('hashing.driver'),
            'mail_encryption'      => config('mail.mailers.smtp.encryption', null),
            'cache_driver'         => config('cache.default'),
            'queue_driver'         => config('queue.default'),
            'logging_channel'      => config('logging.default'),
            'password_timeout'     => config('auth.password_timeout'),
            'sanctum_expiration'   => config('sanctum.expiration'),
            'cors_allowed_origins' => config('cors.allowed_origins', []),
            'collected_at'         => now()->toIso8601String(),
        ];
    }

    /**
     * Collect change management evidence from compliance change logs.
     *
     * @param string $period The audit period identifier
     *
     * @return array<string, mixed>
     */
    public function collectChangeLogEvidence(string $period): array
    {
        [$startDate, $endDate] = $this->parsePeriodDates($period);

        $changes = ComplianceChangeLog::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        $byType = $changes->groupBy('change_type')->map(function (Collection $typeChanges) {
            return [
                'count'   => $typeChanges->count(),
                'changes' => $typeChanges->map(function (ComplianceChangeLog $change) {
                    return [
                        'id'               => $change->id,
                        'description'      => $change->description,
                        'changed_by'       => $change->changed_by,
                        'environment'      => $change->environment,
                        'ticket_reference' => $change->ticket_reference,
                        'created_at'       => $change->created_at?->toIso8601String(),
                    ];
                })->toArray(),
            ];
        })->toArray();

        return [
            'period'        => $period,
            'total_changes' => $changes->count(),
            'by_type'       => $byType,
            'environments'  => $changes->unique('environment')->pluck('environment')->filter()->values()->toArray(),
            'collected_at'  => now()->toIso8601String(),
        ];
    }

    /**
     * Generate a SHA-256 integrity hash for evidence data.
     *
     * @param array<string, mixed> $data
     */
    public function generateIntegrityHash(array $data): string
    {
        return hash('sha256', json_encode($data));
    }

    /**
     * Store evidence with an integrity hash.
     *
     * @param string               $type   Evidence type identifier
     * @param string               $period Audit period
     * @param array<string, mixed> $data   Evidence data payload
     *
     * @return array<string, mixed>
     */
    private function storeEvidence(string $type, string $period, array $data): array
    {
        $integrityHash = $this->generateIntegrityHash($data);

        $evidence = ComplianceEvidence::create([
            'evidence_type'  => $type,
            'period'         => $period,
            'data'           => $data,
            'integrity_hash' => $integrityHash,
            'collected_by'   => auth()->user()?->name ?? 'system',
            'metadata'       => [
                'version'      => '1.0',
                'collector'    => static::class,
                'collected_at' => now()->toIso8601String(),
            ],
        ]);

        return [
            'id'             => $evidence->id,
            'type'           => $type,
            'period'         => $period,
            'integrity_hash' => $integrityHash,
            'record_count'   => is_countable($data) ? count($data) : 0,
            'collected_at'   => $evidence->created_at?->toIso8601String(),
        ];
    }

    /**
     * Parse a period string into start and end Carbon dates.
     *
     * Supports formats: 'YYYY-QN' (quarterly) and 'YYYY-MM' (monthly).
     *
     * @param string $period
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parsePeriodDates(string $period): array
    {
        if (preg_match('/^(\d{4})-Q(\d)$/', $period, $matches)) {
            $year = (int) $matches[1];
            $quarter = (int) $matches[2];
            $startMonth = ($quarter - 1) * 3 + 1;

            $startDate = Carbon::create($year, $startMonth, 1)->startOfDay();
            $endDate = $startDate->copy()->addMonths(3)->subSecond();

            return [$startDate, $endDate];
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            $startDate = Carbon::create((int) $matches[1], (int) $matches[2], 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth();

            return [$startDate, $endDate];
        }

        $reviewDays = (int) config('compliance-certification.soc2.review_period', 90);
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($reviewDays);

        return [$startDate, $endDate];
    }

    /**
     * Return realistic simulated evidence data for demo mode.
     *
     * @param string $period
     *
     * @return array<string, mixed>
     */
    private function getDemoEvidence(string $period): array
    {
        $now = now()->toIso8601String();

        return [
            'access' => [
                'id'             => 'demo-access-' . md5($period),
                'type'           => 'access',
                'period'         => $period,
                'integrity_hash' => hash('sha256', 'demo-access-' . $period),
                'record_count'   => 1247,
                'collected_at'   => $now,
                'data'           => [
                    'period'       => $period,
                    'total_events' => 1247,
                    'unique_users' => 23,
                    'by_user'      => [
                        'admin-001'    => ['action_count' => 312, 'actions' => ['login' => 45, 'view' => 198, 'update' => 69]],
                        'analyst-002'  => ['action_count' => 189, 'actions' => ['login' => 31, 'view' => 142, 'export' => 16]],
                        'operator-003' => ['action_count' => 156, 'actions' => ['login' => 28, 'view' => 95, 'update' => 33]],
                    ],
                    'by_action' => [
                        'login'  => 284,
                        'view'   => 623,
                        'update' => 198,
                        'create' => 87,
                        'delete' => 12,
                        'export' => 43,
                    ],
                ],
            ],
            'config' => [
                'id'             => 'demo-config-' . md5($period),
                'type'           => 'config_snapshot',
                'period'         => $period,
                'integrity_hash' => hash('sha256', 'demo-config-' . $period),
                'record_count'   => 16,
                'collected_at'   => $now,
                'data'           => [
                    'app_debug'        => false,
                    'app_env'          => 'production',
                    'session_lifetime' => 120,
                    'session_driver'   => 'redis',
                    'session_encrypt'  => true,
                    'auth_defaults'    => ['guard' => 'web', 'passwords' => 'users'],
                    'hashing_driver'   => 'bcrypt',
                    'collected_at'     => $now,
                ],
            ],
            'change_management' => [
                'id'             => 'demo-changes-' . md5($period),
                'type'           => 'change_management',
                'period'         => $period,
                'integrity_hash' => hash('sha256', 'demo-changes-' . $period),
                'record_count'   => 34,
                'collected_at'   => $now,
                'data'           => [
                    'period'        => $period,
                    'total_changes' => 34,
                    'by_type'       => [
                        'deployment'     => ['count' => 12],
                        'configuration'  => ['count' => 8],
                        'access_change'  => ['count' => 6],
                        'infrastructure' => ['count' => 5],
                        'policy_update'  => ['count' => 3],
                    ],
                    'environments' => ['production', 'staging'],
                ],
            ],
        ];
    }
}
