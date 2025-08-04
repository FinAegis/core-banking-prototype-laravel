<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Compliance\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent as StoredEvent;

class RegulatoryReportingService
{
    /**
     * Generate Currency Transaction Report (CTR) for large transactions.
     */
    public function generateCTR(Carbon $date): string
    {
        $threshold = 1000000; // $10,000 in cents

        // Query large transactions from the event store
        $largeTransactions = StoredEvent::whereIn(
            'event_class',
            [
                'App\\Domain\\Account\\Events\\MoneyAdded',
                'App\\Domain\\Account\\Events\\MoneySubtracted',
                'App\\Domain\\Account\\Events\\MoneyTransferred',
            ]
        )
            ->whereDate('created_at', $date)
            ->get()
            ->filter(
                function ($event) use ($threshold) {
                    /** @var StoredEvent $event */
                    $properties = $event->event_properties;

                    return isset($properties['money']['amount']) && $properties['money']['amount'] >= $threshold;
                }
            );

        $report = [
            'report_type'        => 'Currency Transaction Report (CTR)',
            'report_date'        => $date->toDateString(),
            'generated_at'       => now()->toISOString(),
            'threshold'          => $threshold,
            'total_transactions' => $largeTransactions->count(),
            'transactions'       => [],
        ];

        /** @var Collection<int, StoredEvent> $largeTransactions */
        foreach ($largeTransactions as $event) {
            /** @var StoredEvent $event */
            $properties = $event->event_properties;
            /** @var \Illuminate\Database\Eloquent\Model|null $account */
            $account = Account::where('uuid', $event->aggregate_uuid)->first();
            $user = $account ? User::where('uuid', $account->user_uuid)->first() : null;

            $report['transactions'][] = [
                'transaction_id' => $event->id,
                'timestamp'      => Carbon::parse($event->created_at)->toISOString(),
                'type'           => class_basename($event->event_class),
                'amount'         => $properties['money']['amount'],
                'currency'       => $properties['money']['currency'] ?? 'USD',
                'account_uuid'   => $event->aggregate_uuid,
                'user_name'      => $user?->name ?? 'Unknown',
                'user_email'     => $user?->email ?? 'Unknown',
                'kyc_status'     => $user?->kyc_status ?? 'unknown',
                'risk_rating'    => $user?->risk_rating ?? 'unknown',
            ];
        }

        // Save report
        $filename = "regulatory/ctr/ctr_{$date->format('Y_m_d')}.json";
        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        // Log report generation
        AuditLog::log(
            'regulatory.ctr_generated',
            null,
            null,
            ['transaction_count' => $largeTransactions->count()],
            ['date'              => $date->toDateString(), 'filename' => $filename],
            'regulatory,compliance,ctr'
        );

        return $filename;
    }

    /**
     * Generate Suspicious Activity Report (SAR) candidates.
     */
    public function generateSARCandidates(Carbon $startDate, Carbon $endDate): string
    {
        $suspiciousPatterns = $this->detectSuspiciousPatterns($startDate, $endDate);

        $report = [
            'report_type'      => 'Suspicious Activity Report (SAR) Candidates',
            'period_start'     => $startDate->toDateString(),
            'period_end'       => $endDate->toDateString(),
            'generated_at'     => now()->toISOString(),
            'total_candidates' => $suspiciousPatterns->count(),
            'patterns'         => $suspiciousPatterns,
        ];

        // Save report
        $filename = "regulatory/sar/sar_candidates_{$startDate->format('Y_m_d')}_{$endDate->format('Y_m_d')}.json";
        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        // Log report generation
        AuditLog::log(
            'regulatory.sar_candidates_generated',
            null,
            null,
            ['candidate_count' => $suspiciousPatterns->count()],
            ['start_date'      => $startDate->toDateString(), 'end_date' => $endDate->toDateString(), 'filename' => $filename],
            'regulatory,compliance,sar'
        );

        return $filename;
    }

    /**
     * Generate comprehensive compliance summary report.
     */
    public function generateComplianceSummary(Carbon $month): string
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        $report = [
            'report_type'  => 'Monthly Compliance Summary',
            'month'        => $month->format('F Y'),
            'generated_at' => now()->toISOString(),
            'metrics'      => [
                'kyc'          => $this->getKycMetrics($startDate, $endDate),
                'transactions' => $this->getTransactionMetrics($startDate, $endDate),
                'users'        => $this->getUserMetrics($startDate, $endDate),
                'risk'         => $this->getRiskMetrics(),
                'gdpr'         => $this->getGdprMetrics($startDate, $endDate),
            ],
        ];

        // Save report
        $filename = "regulatory/compliance/summary_{$month->format('Y_m')}.json";
        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        // Log report generation
        AuditLog::log(
            'regulatory.compliance_summary_generated',
            null,
            null,
            ['month'    => $month->format('Y-m')],
            ['filename' => $filename],
            'regulatory,compliance,summary'
        );

        return $filename;
    }

    /**
     * Generate KYC compliance report.
     */
    public function generateKycReport(): string
    {
        $report = [
            'report_type'  => 'KYC Compliance Report',
            'generated_at' => now()->toISOString(),
            'statistics'   => [
                'total_users'          => User::count(),
                'kyc_status_breakdown' => User::select('kyc_status', DB::raw('count(*) as count'))
                    ->groupBy('kyc_status')
                    ->pluck('count', 'kyc_status'),
                'kyc_level_breakdown' => User::select('kyc_level', DB::raw('count(*) as count'))
                    ->groupBy('kyc_level')
                    ->pluck('count', 'kyc_level'),
                'pep_users'       => User::where('pep_status', true)->count(),
                'high_risk_users' => User::where('risk_rating', 'high')->count(),
                'expired_kyc'     => User::where('kyc_status', 'expired')->count(),
                'expiring_soon'   => User::where('kyc_status', 'approved')
                    ->where('kyc_expires_at', '<=', now()->addDays(30))
                    ->count(),
            ],
            'pending_verifications' => User::where('kyc_status', 'pending')->get()->map(
                function ($user) {
                    return [
                        'user_uuid'    => $user->uuid,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'submitted_at' => $user->kyc_submitted_at,
                        'days_pending' => $user->kyc_submitted_at ? now()->diffInDays($user->kyc_submitted_at) : null,
                    ];
                }
            ),
        ];

        // Save report
        $filename = 'regulatory/kyc/report_' . now()->format('Y_m_d') . '.json';
        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        // Log report generation
        AuditLog::log(
            'regulatory.kyc_report_generated',
            null,
            null,
            $report['statistics'],
            ['filename' => $filename],
            'regulatory,compliance,kyc'
        );

        return $filename;
    }

    /**
     * Detect suspicious patterns in transactions.
     */
    protected function detectSuspiciousPatterns(Carbon $startDate, Carbon $endDate): Collection
    {
        $patterns = collect();

        // Pattern 1: Rapid succession of transactions (potential structuring)
        $rapidTransactions = DB::table('stored_events')
            ->select('aggregate_uuid', DB::raw('count(*) as transaction_count'), DB::raw('min(created_at) as first_transaction'), DB::raw('max(created_at) as last_transaction'))
            ->whereIn(
                'event_class',
                [
                    'App\\Domain\\Account\\Events\\MoneyAdded',
                    'App\\Domain\\Account\\Events\\MoneySubtracted',
                ]
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('aggregate_uuid')
            ->having('transaction_count', '>', 10)
            ->get()
            ->filter(
                function ($item) {
                    $duration = Carbon::parse($item->first_transaction)->diffInHours(Carbon::parse($item->last_transaction));

                    return $duration <= 24; // More than 10 transactions in 24 hours
                }
            );

        foreach ($rapidTransactions as $item) {
            $patterns->push(
                [
                    'pattern_type'      => 'rapid_transactions',
                    'account_uuid'      => $item->aggregate_uuid,
                    'transaction_count' => $item->transaction_count,
                    'time_span_hours'   => Carbon::parse($item->first_transaction)->diffInHours(Carbon::parse($item->last_transaction)),
                    'first_transaction' => $item->first_transaction,
                    'last_transaction'  => $item->last_transaction,
                ]
            );
        }

        // Pattern 2: Just-below-threshold transactions
        $thresholdAmount = 999000; // Just below $10,000
        $justBelowThreshold = StoredEvent::whereIn(
            'event_class',
            [
                'App\\Domain\\Account\\Events\\MoneyAdded',
                'App\\Domain\\Account\\Events\\MoneySubtracted',
            ]
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->filter(
                function ($event) use ($thresholdAmount) {
                    /** @var StoredEvent $event */
                    $properties = $event->event_properties;
                    $amount = $properties['money']['amount'] ?? 0;

                    return $amount >= $thresholdAmount * 0.9 && $amount < $thresholdAmount;
                }
            )
            ->groupBy('aggregate_uuid')
            ->filter(
                function ($group) {
                    return $group->count() >= 3; // 3 or more just-below-threshold transactions
                }
            );

        foreach ($justBelowThreshold as $accountUuid => $transactions) {
            $patterns->push(
                [
                    'pattern_type'      => 'threshold_avoidance',
                    'account_uuid'      => $accountUuid,
                    'transaction_count' => $transactions->count(),
                    'transactions'      => $transactions->map(
                        function ($event) {
                            /** @var StoredEvent $event */
                            $properties = $event->event_properties;

                            return [
                                'id'        => $event->id,
                                'amount'    => $properties['money']['amount'],
                                'timestamp' => Carbon::parse($event->created_at)->toISOString(),
                            ];
                        }
                    )->values(),
                ]
            );
        }

        // Pattern 3: Round number transactions
        $roundNumberTransactions = StoredEvent::whereIn(
            'event_class',
            [
                'App\\Domain\\Account\\Events\\MoneyAdded',
                'App\\Domain\\Account\\Events\\MoneySubtracted',
            ]
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->filter(
                function ($event) {
                    /** @var StoredEvent $event */
                    $properties = $event->event_properties;
                    $amount = $properties['money']['amount'] ?? 0;

                    // Check if amount is a round number (ends with at least 3 zeros)
                    return $amount >= 100000 && $amount % 100000 === 0;
                }
            )
            ->groupBy('aggregate_uuid')
            ->filter(
                function ($group) {
                    return $group->count() >= 5; // 5 or more round number transactions
                }
            );

        foreach ($roundNumberTransactions as $accountUuid => $transactions) {
            $patterns->push(
                [
                    'pattern_type'      => 'round_numbers',
                    'account_uuid'      => $accountUuid,
                    'transaction_count' => $transactions->count(),
                    'total_amount'      => $transactions->sum(
                        function ($event) {
                            /** @var StoredEvent $event */
                            $properties = $event->event_properties;

                            return $properties['money']['amount'] ?? 0;
                        }
                    ),
                ]
            );
        }

        return $patterns;
    }

    /**
     * Get KYC metrics for the period.
     */
    protected function getKycMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'new_submissions' => User::whereBetween('kyc_submitted_at', [$startDate, $endDate])->count(),
            'approved'        => User::whereBetween('kyc_approved_at', [$startDate, $endDate])->count(),
            'rejected'        => User::where('kyc_status', 'rejected')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count(),
            'average_processing_time_hours' => User::whereBetween('kyc_approved_at', [$startDate, $endDate])
                ->whereNotNull('kyc_submitted_at')
                ->get()
                ->map(fn ($user) => $user->kyc_submitted_at->diffInHours($user->kyc_approved_at))
                ->average() ?? 0,
        ];
    }

    /**
     * Get transaction metrics for the period.
     */
    protected function getTransactionMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $events = StoredEvent::whereIn(
            'event_class',
            [
                'App\\Domain\\Account\\Events\\MoneyAdded',
                'App\\Domain\\Account\\Events\\MoneySubtracted',
                'App\\Domain\\Account\\Events\\MoneyTransferred',
            ]
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        /** @var Collection<int, StoredEvent> $events */
        $totalVolume = $events->sum(
            function ($event) {
                /** @var StoredEvent $event */
                $properties = $event->event_properties;

                return $properties['money']['amount'] ?? 0;
            }
        );

        return [
            'total_count'              => $events->count(),
            'total_volume'             => $totalVolume,
            'average_transaction_size' => $events->count() > 0 ? $totalVolume / $events->count() : 0,
            'large_transactions'       => $events->filter(
                function ($event) {
                    /** @var StoredEvent $event */
                    $properties = $event->event_properties;

                    return ($properties['money']['amount'] ?? 0) >= 1000000; // $10,000
                }
            )->count(),
        ];
    }

    /**
     * Get user metrics for the period.
     */
    protected function getUserMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'new_users'    => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_users' => Account::whereBetween('updated_at', [$startDate, $endDate])
                ->distinct('user_uuid')
                ->count('user_uuid'),
            'users_with_completed_kyc' => User::where('kyc_status', 'approved')->count(),
        ];
    }

    /**
     * Get risk metrics.
     */
    protected function getRiskMetrics(): array
    {
        return [
            'high_risk_users'   => User::where('risk_rating', 'high')->count(),
            'medium_risk_users' => User::where('risk_rating', 'medium')->count(),
            'low_risk_users'    => User::where('risk_rating', 'low')->count(),
            'pep_users'         => User::where('pep_status', true)->count(),
        ];
    }

    /**
     * Get GDPR metrics for the period.
     */
    protected function getGdprMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $auditLogs = AuditLog::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'data_export_requests'              => $auditLogs->clone()->where('action', 'gdpr.data_exported')->count(),
            'deletion_requests'                 => $auditLogs->clone()->where('action', 'gdpr.deletion_requested')->count(),
            'consent_updates'                   => $auditLogs->clone()->where('action', 'gdpr.consent_updated')->count(),
            'users_with_marketing_consent'      => User::whereNotNull('marketing_consent_at')->count(),
            'users_with_data_retention_consent' => User::where('data_retention_consent', true)->count(),
        ];
    }
}
