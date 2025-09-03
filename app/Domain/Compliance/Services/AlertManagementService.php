<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use App\Domain\Compliance\Events\AlertEscalated;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Models\User;
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
        'high'     => 1,    // Escalate immediately
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
                    $data['entity_type'],
                    (string) $data['entity_id'],
                    $data['description'],
                    $data['details'] ?? [],
                    $data['user_id'] ?? null
                );

                // Persist the aggregate
                $aggregate->persist();

                // Create alert in read model (projector would normally handle this)
                $alertId = $aggregate->getId();

                // Create the read model alert
                $alert = ComplianceAlert::create([
                    'id'          => $alertId,
                    'alert_id'    => $alertId,
                    'type'        => $data['type'],
                    'severity'    => $data['severity'],
                    'entity_type' => $data['entity_type'],
                    'entity_id'   => (string) $data['entity_id'],
                    'title'       => $data['title'] ?? ($data['type'] . ' Alert'),
                    'description' => $data['description'],
                    'details'     => $data['details'] ?? [],
                    'status'      => 'open',
                    'risk_score'  => $this->calculateRiskScore($data['severity']),
                    'created_by'  => $data['user_id'] ?? auth()->id() ?? null,
                ]);

                if (! $alert) {
                    throw new Exception('Failed to create alert read model');
                }

                // Check for automatic escalation
                $this->checkForEscalation($alert);

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
    public function assignAlert(string $alertId, string $userId, ?string $notes = null): ComplianceAlert
    {
        return DB::transaction(function () use ($alertId, $userId, $notes) {
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->assign($userId, (string) (auth()->id() ?? 'system'), $notes);
            $aggregate->persist();

            // Update the read model
            ComplianceAlert::where('id', $alertId)->update([
                'assigned_to' => $userId,
                'assigned_at' => now(),
            ]);

            return ComplianceAlert::findOrFail($alertId);
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

            // Update the read model with linked alerts
            $alert = ComplianceAlert::findOrFail($alertId);
            $linkedAlerts = $alert->linked_alerts ?? [];
            $linkedAlerts = array_unique(array_merge($linkedAlerts, $linkedAlertIds));
            $alert->update(['linked_alerts' => $linkedAlerts]);

            return $alert;
        });
    }

    /**
     * Escalate alert to case.
     */
    public function escalateToCase(string $alertId, string $reason): ComplianceCase
    {
        return DB::transaction(function () use ($alertId, $reason) {
            $alert = ComplianceAlert::findOrFail($alertId);

            // Create new case
            $case = ComplianceCase::create([
                'case_id'     => $this->generateCaseNumber(),
                'case_number' => $this->generateCaseNumber(),  // Add case_number
                'title'       => "Alert Escalation: {$alert->type}", // Add required title
                'type'        => 'investigation', // Add required type
                'priority'    => $this->mapSeverityToPriority($alert->severity),
                'status'      => 'open',
                'description' => "Escalated from alert: {$alert->description}",
                'created_by'  => (string) (auth()->id() ?? 'system'),
            ]);

            // Update alert through aggregate
            $aggregate = ComplianceAlertAggregate::retrieve($alertId);
            $aggregate->escalateToCase((string) $case->id, (string) (auth()->id() ?? 'system'), $reason);
            $aggregate->persist();

            // Add alert to case
            // Link alert to case in the projection model
            ComplianceAlert::where('id', $alertId)->update(['case_id' => $case->id]);

            Event::dispatch(new AlertEscalated($alertId, $case->id, $reason));

            Log::info('Alert escalated to case', [
                'alert_id' => $alertId,
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

        return [
            'total'     => $query->count(),
            'by_status' => $query->clone()->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_severity' => $query->clone()->groupBy('severity')
                ->selectRaw('severity, count(*) as count')
                ->pluck('count', 'severity')
                ->toArray(),
            'by_type' => $query->clone()->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
                ->toArray(),
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
        $threshold = self::ESCALATION_THRESHOLDS[$alert->severity] ?? 5;

        // Count similar recent alerts
        $similarCount = ComplianceAlert::where('type', $alert->type)
            ->where('entity_type', $alert->entity_type)
            ->where('entity_id', $alert->entity_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($similarCount >= $threshold) {
            $this->escalateToCase(
                $alert->id,
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
        $resolved = $query->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->first();

        return $resolved ? round($resolved->avg_hours, 2) : null;
    }

    /**
     * Search alerts based on criteria.
     */
    public function searchAlerts(array $filters): array
    {
        $query = ComplianceAlert::query();

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
            ->paginate($perPage, ['*'], 'page', $page);

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
}
