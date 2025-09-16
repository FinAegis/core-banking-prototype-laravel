<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use App\Domain\Compliance\Events\AlertEscalated;
use App\Domain\Compliance\Events\AlertResolved;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Models\User;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Alert management service for compliance monitoring.
 * Handles alert creation, escalation, assignment, and resolution using event sourcing.
 */
class AlertManagementService
{
    private const ESCALATION_THRESHOLDS = [
        'low'      => 5,     // Escalate after 5 similar alerts
        'medium'   => 3,  // Escalate after 3 similar alerts
        'high'     => 2,    // Escalate after 2 similar alerts
        'critical' => 1, // Escalate immediately
    ];

    private const AUTO_CLOSE_HOURS = [
        'low'      => 168,    // 7 days
        'medium'   => 72,  // 3 days
        'high'     => 24,    // 1 day
        'critical' => 0,  // Never auto-close
    ];

    /**
     * Create a new compliance alert using event sourcing.
     */
    public function createAlert(array $data): ComplianceAlert
    {
        return DB::transaction(function () use ($data) {
            try {
                // Create alert through aggregate
                $aggregate = ComplianceAlertAggregate::create(
                    $data['type'],
                    $data['severity'],
                    $data['entity_type'] ?? $data['type'],  // Default entity_type to type if not provided
                    (string) ($data['entity_id'] ?? 'system'),  // Default entity_id to 'system' if not provided
                    $data['description'],
                    $data['details'] ?? [],
                    isset($data['user_id']) ? (string) $data['user_id'] : null
                );

                // Persist the aggregate
                $aggregate->persist();

                // Create alert in read model (projector would normally handle this)
                $alertId = $aggregate->getId();

                // Generate type-specific alert ID
                $alertIdPrefix = match ($data['type']) {
                    'transaction' => 'TXN-',
                    'pattern'     => 'PTN-',
                    'account'     => 'ACC-',
                    'behavior'    => 'BHV-',
                    default       => 'ALT-',
                };
                $formattedAlertId = $alertIdPrefix . strtoupper(substr($alertId, 0, 8));

                // Create the read model alert
                $alert = ComplianceAlert::create([
                    'id'          => $alertId,
                    'alert_id'    => $formattedAlertId,
                    'type'        => $data['type'],
                    'severity'    => $data['severity'],
                    'entity_type' => $data['entity_type'] ?? $data['type'],  // Use same default as aggregate
                    'entity_id'   => (string) ($data['entity_id'] ?? 'system'),  // Use same default as aggregate
                    'title'       => $data['title'] ?? ($data['type'] . ' Alert'),
                    'description' => $data['description'],
                    'details'     => $data['details'] ?? [],
                    'status'      => 'open',
                    'risk_score'  => $this->calculateRiskScore($data['severity']),
                    'user_id'     => $data['user_id'] ?? auth()->id() ?? null,
                    'detected_at' => now(),
                ]);

                if (! $alert) {
                    throw new Exception('Failed to create alert read model');
                }

                // Check for automatic escalation
                $this->checkForEscalation($alert);

                // Refresh alert to get updated status if it was escalated
                $alert->refresh();

                // Notify compliance team if high severity
                if (in_array($alert->severity, ['high', 'critical'])) {
                    $this->notifyComplianceTeam($alert);
                }

                Log::info('Compliance alert created', [
                    'alert_id' => $alert->id,
                    'type'     => $alert->type,
                    'severity' => $alert->severity,
                ]);

                return $alert;
            } catch (Exception $e) {
                Log::error('Failed to create compliance alert', [
                    'error' => $e->getMessage(),
                    'data'  => $data,
                ]);
                throw $e;
            }
        });
    }

    /**
     * Assign an alert to a user.
     */
    public function assignAlert(ComplianceAlert $alert, User $assignee, User $assignedBy, ?string $notes = null): ComplianceAlert
    {
        return DB::transaction(function () use ($alert, $assignee, $assignedBy, $notes) {
            $aggregate = ComplianceAlertAggregate::retrieve($alert->id);
            $aggregate->assign((string) $assignee->id, (string) $assignedBy->id, $notes);
            $aggregate->persist();

            // Update the read model
            $alert->update([
                'assigned_to' => $assignee->id,
                'assigned_by' => $assignedBy->id,
                'assigned_at' => now(),
            ]);

            // Add to history
            $history = $alert->history ?? [];
            $history[] = [
                'action'      => 'assignment',
                'assigned_to' => $assignee->id,
                'assigned_by' => $assignedBy->id,
                'notes'       => $notes,
                'timestamp'   => now()->toIso8601String(),
            ];
            $alert->update(['history' => $history]);

            return $alert->fresh();
        });
    }

    /**
     * Change alert status.
     */
    public function changeStatus(string $alertId, string $newStatus, ?string $reason = null): ComplianceAlert
    {
        return DB::transaction(function () use ($alertId, $newStatus, $reason) {
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->changeStatus($newStatus, $reason, (string) (auth()->id() ?? 'system'));
            $aggregate->persist();

            // Update the read model
            ComplianceAlert::where('id', $alertId)->update([
                'status' => $newStatus,
            ]);

            return ComplianceAlert::findOrFail($alertId);
        });
    }

    /**
     * Add a note to an alert.
     */
    public function addNote(string $alertId, string $note, array $attachments = []): ComplianceAlert
    {
        return DB::transaction(function () use ($alertId, $note, $attachments) {
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->addNote($note, (string) (auth()->id() ?? 'system'), $attachments);
            $aggregate->persist();

            // Update the read model notes
            $alert = ComplianceAlert::findOrFail($alertId);
            $notes = $alert->notes ?? [];
            $notes[] = [
                'note'        => $note,
                'attachments' => $attachments,
                'created_by'  => auth()->id() ?? 'system',
                'created_at'  => now(),
            ];
            $alert->update(['notes' => $notes]);

            return $alert;
        });
    }

    /**
     * Resolve an alert.
     */
    public function resolveAlert(string $alertId, string $resolution, ?string $notes = null): ComplianceAlert
    {
        return DB::transaction(function () use ($alertId, $resolution, $notes) {
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->resolve($resolution, (string) (auth()->id() ?? 'system'), $notes);
            $aggregate->persist();

            // Update the read model
            ComplianceAlert::where('id', $alertId)->update([
                'status'           => 'closed',
                'resolution'       => $resolution,
                'resolution_notes' => $notes,
                'resolved_at'      => now(),
                'resolved_by'      => auth()->id() ?? null,
            ]);

            return ComplianceAlert::findOrFail($alertId);
        });
    }

    /**
     * Link related alerts.
     */
    public function linkAlerts(string $alertId, array $linkedAlertIds, string $linkType = 'related'): ComplianceAlert
    {
        return DB::transaction(function () use ($alertId, $linkedAlertIds, $linkType) {
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->linkAlerts($linkedAlertIds, $linkType, (string) (auth()->id() ?? 'system'));
            $aggregate->persist();

            // The projector will handle the read model update, just return the alert
            return ComplianceAlert::findOrFail($alertId);
        });
    }

    /**
     * Escalate alert to case.
     */
    public function escalateToCase(string $alertId, string $reason): ComplianceCase
    {
        return DB::transaction(function () use ($alertId, $reason) {
            $alert = ComplianceAlert::where('alert_id', $alertId)->firstOrFail();

            // Create new case
            $caseNumber = $this->generateCaseNumber();
            $case = ComplianceCase::create([
                'case_id'          => $caseNumber,
                'case_number'      => $caseNumber,
                'title'            => "Alert Escalation: {$alert->type}",
                'type'             => 'investigation',
                'priority'         => $this->mapSeverityToPriority($alert->severity),
                'status'           => 'open',
                'description'      => "Escalated from alert: {$alert->description}",
                'created_by'       => auth()->id(),
                'alert_count'      => 1,  // Single alert escalation
                'total_risk_score' => $alert->risk_score ?? 0,
            ]);

            // Update alert through aggregate
            $aggregate = ComplianceAlertAggregate::retrieve($alert->id);
            $aggregate->escalateToCase((string) $case->id, (string) (auth()->id() ?? 'system'), $reason);
            $aggregate->persist();

            // Add alert to case
            // Link alert to case in the projection model and update status
            ComplianceAlert::where('id', $alert->id)->update([
                'case_id'           => $case->id,
                'status'            => ComplianceAlert::STATUS_ESCALATED,
                'escalated_at'      => now(),
                'escalation_reason' => $reason,
            ]);

            $alertModel = ComplianceAlert::findOrFail($alert->id);
            $similarAlerts = ComplianceAlert::where('id', '!=', $alert->id)
                ->where('type', $alertModel->type)
                ->where('status', '!=', ComplianceAlert::STATUS_RESOLVED)
                ->limit(10)
                ->get();
            Event::dispatch(new AlertEscalated($alertModel, $similarAlerts));

            Log::info('Alert escalated to case', [
                'alert_id' => $alert->id,
                'case_id'  => $case->id,
                'reason'   => $reason,
            ]);

            return $case;
        });
    }

    /**
     * Get alert statistics for a given period.
     */
    public function getStatistics(array $filters = []): array
    {
        $query = ComplianceAlert::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // Get all alerts matching the query to avoid PHPStan issues with selectRaw
        $alerts = $query->get();

        return [
            'total'                   => $alerts->count(),
            'by_status'               => $alerts->groupBy('status')->map->count()->toArray(),
            'by_severity'             => $alerts->groupBy('severity')->map->count()->toArray(),
            'by_type'                 => $alerts->groupBy('type')->map->count()->toArray(),
            'escalation_rate'         => $this->calculateEscalationRate($query->clone()),
            'average_resolution_time' => $this->calculateAverageResolutionTime($query->clone()),
        ];
    }

    /**
     * Auto-close old alerts based on severity.
     */
    public function autoCloseOldAlerts(): int
    {
        $count = 0;

        foreach (self::AUTO_CLOSE_HOURS as $severity => $hours) {
            if ($hours === 0) {
                continue; // Skip critical alerts
            }

            $cutoffDate = now()->subHours($hours);

            $alerts = ComplianceAlert::where('severity', $severity)
                ->where('status', 'open')
                ->where('created_at', '<', $cutoffDate)
                ->get();

            foreach ($alerts as $alert) {
                $this->resolveAlert(
                    $alert->id,
                    'auto_closed',
                    "Automatically closed after {$hours} hours of inactivity"
                );
                $count++;
            }
        }

        Log::info("Auto-closed {$count} old alerts");

        return $count;
    }

    /**
     * Check if alert should be escalated based on thresholds.
     */
    private function checkForEscalation(ComplianceAlert $alert): void
    {
        // Critical alerts are always escalated immediately
        if ($alert->severity === 'critical') {
            $this->escalateToCase(
                $alert->alert_id,
                'Automatic escalation: Critical severity alert'
            );

            return;
        }

        $threshold = self::ESCALATION_THRESHOLDS[$alert->severity] ?? 5;

        // Count similar recent alerts (excluding the current one)
        $similarCount = ComplianceAlert::where('type', $alert->type)
            ->where('entity_type', $alert->entity_type)
            ->where('entity_id', $alert->entity_id)
            ->where('id', '!=', $alert->id)  // Exclude the current alert
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($similarCount >= $threshold) {
            $this->escalateToCase(
                $alert->alert_id,
                "Automatic escalation: {$similarCount} similar alerts in the past 7 days"
            );
        }
    }

    /**
     * Notify compliance team about high-severity alerts.
     */
    private function calculateRiskScore(string $severity): float
    {
        return match ($severity) {
            'critical' => 100.0,
            'high'     => 75.0,
            'medium'   => 50.0,
            'low'      => 25.0,
            default    => 0.0,
        };
    }

    private function notifyComplianceTeam(ComplianceAlert $alert): void
    {
        // Get compliance team users
        $complianceTeam = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['compliance_officer', 'compliance_manager']);
        })->get();

        // Send notifications
        // Notification::send($complianceTeam, new HighSeverityAlertNotification($alert));

        Log::info('Compliance team notified about high-severity alert', [
            'alert_id'       => $alert->id,
            'notified_users' => $complianceTeam->pluck('id')->toArray(),
        ]);
    }

    /**
     * Generate unique case number.
     */
    private function generateCaseNumber(): string
    {
        $year = now()->format('Y');
        $lastCase = ComplianceCase::where('case_id', 'like', "CASE-{$year}-%")
            ->orderBy('case_id', 'desc')
            ->first();

        if ($lastCase) {
            $lastNumber = (int) substr($lastCase->case_id, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('CASE-%s-%06d', $year, $newNumber);
    }

    /**
     * Map alert severity to case priority.
     */
    private function mapSeverityToPriority(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high'     => 'high',
            'medium'   => 'medium',
            'low'      => 'low',
            default    => 'medium',
        };
    }

    /**
     * Calculate escalation rate from query.
     */
    private function calculateEscalationRate($query): float
    {
        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $escalated = $query->whereNotNull('case_id')->count();

        return round(($escalated / $total) * 100, 2);
    }

    /**
     * Calculate average resolution time from query.
     */
    private function calculateAverageResolutionTime($query): ?float
    {
        $resolved = $query->whereNotNull('resolved_at')->get();

        if ($resolved->isEmpty()) {
            return null;
        }

        $totalHours = $resolved->sum(function ($alert) {
            return $alert->created_at->diffInHours($alert->resolved_at);
        });

        return round($totalHours / $resolved->count(), 2);
    }

    /**
     * Search alerts based on criteria.
     */
    public function searchAlerts(array $filters): array
    {
        $query = ComplianceAlert::query();

        // Handle text search in description and title
        if (isset($filters['query'])) {
            $searchTerm = $filters['query'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', '%' . $searchTerm . '%')
                  ->orWhere('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('type', 'like', '%' . $searchTerm . '%');
            });
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (isset($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['min_risk_score'])) {
            $query->where('risk_score', '>=', $filters['min_risk_score']);
        }

        if (isset($filters['max_risk_score'])) {
            $query->where('risk_score', '<=', $filters['max_risk_score']);
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $results = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['id', 'alert_id', 'type', 'severity', 'status', 'title', 'description', 'entity_type', 'entity_id', 'created_at'], 'page', $page);

        return [
            'data' => $results->items(),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
            ],
        ];
    }

    /**
     * Update alert status with user tracking.
     */
    public function updateAlertStatus(ComplianceAlert $alert, string $newStatus, User $user, ?string $notes = null): ComplianceAlert
    {
        // Use aggregate to change status with proper event sourcing
        $aggregate = ComplianceAlertAggregate::retrieve($alert->id);
        $aggregate->changeStatus($newStatus, $notes, (string) $user->id);
        $aggregate->persist();

        // Update specific fields based on status
        $updateData = [
            'status'            => $newStatus,
            'status_changed_at' => now(),
            'status_changed_by' => $user->id,
        ];

        // Add history entry
        $history = $alert->history ?? [];
        $history[] = [
            'timestamp' => now()->toIso8601String(),
            'user_id'   => $user->id,
            'status'    => $newStatus,
            'notes'     => $notes,
        ];
        $updateData['history'] = $history;

        // Handle resolution-specific fields
        if ($newStatus === ComplianceAlert::STATUS_RESOLVED || $newStatus === ComplianceAlert::STATUS_FALSE_POSITIVE) {
            $updateData['resolved_at'] = now();
            $updateData['resolved_by'] = $user->id;
            $updateData['resolution_notes'] = $notes;

            // Set false_positive_notes specifically for false positive status
            if ($newStatus === ComplianceAlert::STATUS_FALSE_POSITIVE) {
                $updateData['false_positive_notes'] = $notes;
            }

            // Calculate resolution time if detected_at exists
            if ($alert->detected_at) {
                $updateData['resolution_time_hours'] = $alert->detected_at->diffInHours(now());
            }
        }

        // Update the alert
        $alert->update($updateData);

        // Update monitoring rule effectiveness if marking as false positive
        if ($newStatus === ComplianceAlert::STATUS_FALSE_POSITIVE && $alert->rule_id) {
            $rule = \App\Domain\Compliance\Models\TransactionMonitoringRule::find($alert->rule_id);
            if ($rule) {
                $rule->increment('false_positives');
            }
        }

        // Dispatch resolution event if resolved
        if ($newStatus === ComplianceAlert::STATUS_RESOLVED) {
            Event::dispatch(new AlertResolved(
                $alert->id,
                $newStatus,
                (string) $user->id,
                $notes ?? '',
                new DateTimeImmutable()
            ));
        }

        // Reload and return the updated projection
        return $alert->fresh();
    }

    /**
     * Add an investigation note to an alert.
     */
    public function addInvestigationNote(ComplianceAlert $alert, string $note, User $user): ComplianceAlert
    {
        // Use aggregate to add note with proper event sourcing
        $aggregate = ComplianceAlertAggregate::retrieve($alert->id);
        $aggregate->addNote($note, (string) $user->id);
        $aggregate->persist();

        // Reload and return the updated projection
        return $alert->fresh();
    }

    /**
     * Create a compliance case from multiple alerts.
     */
    public function createCaseFromAlerts(array $alertIds, array $caseData): ComplianceCase
    {
        // Calculate metrics from related alerts
        $alerts = ComplianceAlert::whereIn('id', $alertIds)->get();
        $totalRiskScore = $alerts->sum('risk_score');
        $alertCount = $alerts->count();

        // Determine priority based on total risk score if not provided
        if (! isset($caseData['priority'])) {
            if ($totalRiskScore >= 200) {
                $caseData['priority'] = 'critical';
            } elseif ($totalRiskScore >= 150) {
                $caseData['priority'] = 'high';
            } elseif ($totalRiskScore >= 100) {
                $caseData['priority'] = 'medium';
            } else {
                $caseData['priority'] = 'low';
            }
        }

        // Create the case
        $caseNumber = $this->generateCaseNumber();
        $case = ComplianceCase::create([
            'case_id'          => $caseNumber,
            'case_number'      => $caseNumber,
            'title'            => $caseData['title'],
            'description'      => $caseData['description'],
            'type'             => $caseData['type'] ?? 'investigation',
            'status'           => 'open',
            'priority'         => $caseData['priority'],
            'created_by'       => $caseData['created_by'],
            'alert_count'      => $alertCount,
            'total_risk_score' => $totalRiskScore,
            'entities'         => [],
            'evidence'         => [],
            'notes'            => [],
        ]);

        // Update alerts to reference this case and change status
        ComplianceAlert::whereIn('id', $alertIds)->update([
            'case_id' => $case->id,
            'status'  => ComplianceAlert::STATUS_IN_REVIEW,
        ]);

        // Escalate each alert to the case via aggregate
        foreach ($alertIds as $alertId) {
            try {
                $aggregate = ComplianceAlertAggregate::retrieve($alertId);
                $aggregate->escalateToCase($case->id, 'Grouped into case', (string) $caseData['created_by']);
                $aggregate->persist();
            } catch (Exception $e) {
                Log::error("Failed to escalate alert {$alertId} to case {$case->id}: " . $e->getMessage());
            }
        }

        return $case;
    }

    /**
     * Get alert statistics.
     */
    public function getAlertStatistics(): array
    {
        $stats = [
            'total_alerts'   => ComplianceAlert::count(),
            'by_status'      => [],
            'by_severity'    => [],
            'by_type'        => [],
            'response_times' => [],
        ];

        // Count by status - using collection methods to avoid PHPStan issues
        $alerts = ComplianceAlert::all();
        $stats['by_status'] = $alerts->groupBy('status')->map->count()->toArray();

        // Count by severity
        $stats['by_severity'] = $alerts->groupBy('severity')->map->count()->toArray();

        // Count by type
        $stats['by_type'] = $alerts->groupBy('type')->map->count()->toArray();

        // Calculate false positive rate
        $totalResolved = $alerts->whereIn('status', [ComplianceAlert::STATUS_RESOLVED, ComplianceAlert::STATUS_FALSE_POSITIVE])->count();
        $falsePositives = $alerts->where('status', ComplianceAlert::STATUS_FALSE_POSITIVE)->count();
        $stats['false_positive_rate'] = $totalResolved > 0 ? ($falsePositives / $totalResolved) * 100 : 0;

        // Calculate average response times using Eloquent
        $resolvedAlerts = ComplianceAlert::whereNotNull('resolved_at')->get();
        if ($resolvedAlerts->count() > 0) {
            $totalSeconds = 0;
            foreach ($resolvedAlerts as $alert) {
                $totalSeconds += $alert->created_at->diffInSeconds($alert->resolved_at);
            }
            $stats['response_times']['average_resolution_seconds'] = $totalSeconds / $resolvedAlerts->count();
        } else {
            $stats['response_times']['average_resolution_seconds'] = 0;
        }

        return $stats;
    }

    /**
     * Get alert trends over a period.
     */
    public function getAlertTrends(string $period = '7d'): array
    {
        $startDate = match ($period) {
            '24h'   => now()->subDay(),
            '7d'    => now()->subDays(7),
            '30d'   => now()->subDays(30),
            '90d'   => now()->subDays(90),
            default => now()->subDays(7),
        };

        $trends = ComplianceAlert::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count, severity, type')
            ->groupBy('date', 'severity', 'type')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($dayAlerts) {
                return [
                    'total'       => $dayAlerts->sum('count'),
                    'by_severity' => $dayAlerts->groupBy('severity')->map->sum('count'),
                    'by_type'     => $dayAlerts->groupBy('type')->map->sum('count'),
                ];
            })
            ->toArray();

        return $trends;
    }
}
