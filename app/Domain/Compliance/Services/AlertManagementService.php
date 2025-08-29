<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Events\AlertEscalated;
use App\Domain\Compliance\Events\AlertResolved;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Alert management service for compliance monitoring.
 * Handles alert creation, escalation, assignment, and resolution.
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
     * Create a new compliance alert.
     */
    public function createAlert(array $data): ComplianceAlert
    {
        DB::beginTransaction();

        try {
            // Create the alert
            $alert = ComplianceAlert::create([
                'alert_id'         => $this->generateAlertId($data['type'] ?? 'GENERAL'),
                'type'             => $data['type'],
                'severity'         => $data['severity'] ?? 'medium',
                'status'           => ComplianceAlert::STATUS_OPEN,
                'title'            => $data['title'],
                'description'      => $data['description'],
                'source'           => $data['source'] ?? 'system',
                'entity_type'      => $data['entity_type'] ?? null,
                'entity_id'        => $data['entity_id'] ?? null,
                'transaction_id'   => $data['transaction_id'] ?? null,
                'account_id'       => $data['account_id'] ?? null,
                'user_id'          => $data['user_id'] ?? null,
                'rule_id'          => $data['rule_id'] ?? null,
                'pattern_data'     => $data['pattern_data'] ?? null,
                'evidence'         => $data['evidence'] ?? null,
                'risk_score'       => $data['risk_score'] ?? 0,
                'confidence_score' => $data['confidence_score'] ?? 0,
                'metadata'         => $data['metadata'] ?? [],
                'tags'             => $data['tags'] ?? [],
                'detected_at'      => $data['detected_at'] ?? now(),
                'expires_at'       => $this->calculateExpiryDate($data['severity'] ?? 'medium'),
            ]);

            // Check for similar alerts
            $similarAlerts = $this->findSimilarAlerts($alert);

            // Determine if escalation needed
            if ($this->shouldEscalate($alert, $similarAlerts)) {
                $this->escalateAlert($alert, $similarAlerts);
            }

            // Auto-assign if rules match
            $this->autoAssignAlert($alert);

            // Send notifications
            $this->sendAlertNotifications($alert);

            DB::commit();

            Log::info('Compliance alert created', [
                'alert_id' => $alert->alert_id,
                'type'     => $alert->type,
                'severity' => $alert->severity,
            ]);

            return $alert;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create compliance alert', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update alert status.
     */
    public function updateAlertStatus(ComplianceAlert $alert, string $status, ?User $user = null, ?string $notes = null): ComplianceAlert
    {
        DB::beginTransaction();

        try {
            $previousStatus = $alert->status;

            $alert->update([
                'status'            => $status,
                'status_changed_at' => now(),
                'status_changed_by' => $user?->id,
            ]);

            // Add to history
            $history = $alert->history ?? [];
            $history[] = [
                'timestamp'   => now()->toIso8601String(),
                'action'      => 'status_change',
                'from_status' => $previousStatus,
                'to_status'   => $status,
                'user_id'     => $user?->id,
                'user_name'   => $user?->name,
                'notes'       => $notes,
            ];
            $alert->update(['history' => $history]);

            // Handle status-specific actions
            switch ($status) {
                case ComplianceAlert::STATUS_RESOLVED:
                    $this->handleAlertResolution($alert, $user, $notes);
                    break;

                case ComplianceAlert::STATUS_ESCALATED:
                    $this->handleAlertEscalation($alert, $user, $notes);
                    break;

                case ComplianceAlert::STATUS_FALSE_POSITIVE:
                    $this->handleFalsePositive($alert, $user, $notes);
                    break;
            }

            DB::commit();

            return $alert;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Assign alert to user.
     */
    public function assignAlert(ComplianceAlert $alert, User $assignee, ?User $assignedBy = null): ComplianceAlert
    {
        $alert->update([
            'assigned_to' => $assignee->id,
            'assigned_at' => now(),
            'assigned_by' => $assignedBy?->id,
        ]);

        // Add to history
        $history = $alert->history ?? [];
        $history[] = [
            'timestamp'        => now()->toIso8601String(),
            'action'           => 'assigned',
            'assigned_to'      => $assignee->id,
            'assigned_to_name' => $assignee->name,
            'assigned_by'      => $assignedBy?->id,
            'assigned_by_name' => $assignedBy?->name,
        ];
        $alert->update(['history' => $history]);

        // Notify assignee
        $assignee->notify(new \App\Notifications\AlertAssigned($alert));

        return $alert;
    }

    /**
     * Add investigation notes to alert.
     */
    public function addInvestigationNote(ComplianceAlert $alert, string $note, User $user, array $attachments = []): ComplianceAlert
    {
        $notes = $alert->investigation_notes ?? [];

        $notes[] = [
            'id'          => uniqid('note_'),
            'timestamp'   => now()->toIso8601String(),
            'user_id'     => $user->id,
            'user_name'   => $user->name,
            'note'        => $note,
            'attachments' => $attachments,
        ];

        $alert->update(['investigation_notes' => $notes]);

        return $alert;
    }

    /**
     * Link alerts together.
     */
    public function linkAlerts(ComplianceAlert $alert, array $relatedAlertIds, string $linkType = 'related'): ComplianceAlert
    {
        $linkedAlerts = $alert->linked_alerts ?? [];

        foreach ($relatedAlertIds as $relatedId) {
            $linkedAlerts[] = [
                'alert_id'  => $relatedId,
                'link_type' => $linkType,
                'linked_at' => now()->toIso8601String(),
            ];
        }

        $alert->update(['linked_alerts' => $linkedAlerts]);

        return $alert;
    }

    /**
     * Create case from alerts.
     */
    public function createCaseFromAlerts(array $alertIds, array $caseData): ComplianceCase
    {
        DB::beginTransaction();

        try {
            // Get alerts
            $alerts = ComplianceAlert::whereIn('id', $alertIds)->get();

            // Create case
            $case = ComplianceCase::create([
                'case_id'          => $this->generateCaseId(),
                'title'            => $caseData['title'],
                'description'      => $caseData['description'] ?? $this->generateCaseDescription($alerts),
                'priority'         => $caseData['priority'] ?? $this->determineCasePriority($alerts),
                'status'           => ComplianceCase::STATUS_OPEN,
                'type'             => $caseData['type'] ?? 'investigation',
                'alert_count'      => $alerts->count(),
                'total_risk_score' => $alerts->sum('risk_score'),
                'entities'         => $this->extractEntitiesFromAlerts($alerts),
                'evidence'         => $this->consolidateEvidence($alerts),
                'assigned_to'      => $caseData['assigned_to'] ?? null,
                'created_by'       => $caseData['created_by'] ?? null,
            ]);

            // Link alerts to case
            foreach ($alerts as $alert) {
                $alert->update([
                    'case_id' => $case->id,
                    'status'  => ComplianceAlert::STATUS_IN_REVIEW,
                ]);
            }

            DB::commit();

            Log::info('Compliance case created from alerts', [
                'case_id'     => $case->case_id,
                'alert_count' => $alerts->count(),
            ]);

            return $case;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get alert statistics.
     */
    public function getAlertStatistics(array $filters = []): array
    {
        $query = ComplianceAlert::query();

        // Apply filters
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        return [
            'total_alerts'        => $query->count(),
            'by_status'           => (clone $query)->groupBy('status')->selectRaw('status, count(*) as total')->get()->pluck('total', 'status'),
            'by_severity'         => (clone $query)->groupBy('severity')->selectRaw('severity, count(*) as total')->get()->pluck('total', 'severity'),
            'by_type'             => (clone $query)->groupBy('type')->selectRaw('type, count(*) as total')->get()->pluck('total', 'type'),
            'avg_resolution_time' => $this->calculateAverageResolutionTime($query),
            'false_positive_rate' => $this->calculateFalsePositiveRate($query),
            'escalation_rate'     => $this->calculateEscalationRate($query),
        ];
    }

    /**
     * Get alert trends.
     */
    public function getAlertTrends(string $period = '7d'): array
    {
        $startDate = match ($period) {
            '24h'   => now()->subDay(),
            '7d'    => now()->subWeek(),
            '30d'   => now()->subMonth(),
            '90d'   => now()->subMonths(3),
            default => now()->subWeek(),
        };

        $alerts = ComplianceAlert::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count, severity')
            ->groupBy('date', 'severity')
            ->get();

        // Format for charting
        $trends = [];
        foreach ($alerts as $alert) {
            $trends[$alert->date][$alert->severity] = $alert->count;
        }

        return $trends;
    }

    /**
     * Search alerts.
     */
    public function searchAlerts(array $criteria, int $limit = 50): Collection
    {
        $query = ComplianceAlert::query();

        // Text search
        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('alert_id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $query->whereIn('status', $criteria['status']);
            } else {
                $query->where('status', $criteria['status']);
            }
        }

        // Filter by severity
        if (isset($criteria['severity'])) {
            if (is_array($criteria['severity'])) {
                $query->whereIn('severity', $criteria['severity']);
            } else {
                $query->where('severity', $criteria['severity']);
            }
        }

        // Filter by type
        if (isset($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        // Filter by assignment
        if (isset($criteria['assigned_to'])) {
            $query->where('assigned_to', $criteria['assigned_to']);
        }

        // Filter by date range
        if (isset($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }
        if (isset($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        // Filter by risk score
        if (isset($criteria['min_risk_score'])) {
            $query->where('risk_score', '>=', $criteria['min_risk_score']);
        }

        // Sorting
        $sortBy = $criteria['sort_by'] ?? 'created_at';
        $sortOrder = $criteria['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->limit($limit)->get();
    }

    // Private methods

    private function generateAlertId(string $type): string
    {
        $prefix = match ($type) {
            'transaction' => 'TXN',
            'pattern'     => 'PTN',
            'velocity'    => 'VEL',
            'threshold'   => 'THR',
            'behavior'    => 'BHV',
            default       => 'ALT',
        };

        return $prefix . '-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    private function generateCaseId(): string
    {
        return 'CASE-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    private function calculateExpiryDate(string $severity): ?\Carbon\Carbon
    {
        $hours = self::AUTO_CLOSE_HOURS[$severity] ?? 72;

        if ($hours === 0) {
            return null; // No expiry
        }

        return now()->addHours($hours);
    }

    private function findSimilarAlerts(ComplianceAlert $alert): Collection
    {
        $query = ComplianceAlert::where('type', $alert->type)
            ->where('status', '!=', ComplianceAlert::STATUS_RESOLVED)
            ->where('id', '!=', $alert->id);

        // Similar entity
        if ($alert->entity_id) {
            $query->where('entity_id', $alert->entity_id);
        }

        // Similar user
        if ($alert->user_id) {
            $query->where('user_id', $alert->user_id);
        }

        // Similar account
        if ($alert->account_id) {
            $query->where('account_id', $alert->account_id);
        }

        // Within time window
        $query->where('created_at', '>=', now()->subHours(24));

        return $query->get();
    }

    private function shouldEscalate(ComplianceAlert $alert, Collection $similarAlerts): bool
    {
        $threshold = self::ESCALATION_THRESHOLDS[$alert->severity] ?? 3;

        // Always escalate critical alerts
        if ($alert->severity === 'critical') {
            return true;
        }

        // Escalate if threshold exceeded
        if ($similarAlerts->count() >= $threshold) {
            return true;
        }

        // Escalate if high risk score
        if ($alert->risk_score >= 80) {
            return true;
        }

        return false;
    }

    private function escalateAlert(ComplianceAlert $alert, Collection $similarAlerts): void
    {
        $alert->update([
            'status'            => ComplianceAlert::STATUS_ESCALATED,
            'escalated_at'      => now(),
            'escalation_reason' => $this->generateEscalationReason($alert, $similarAlerts),
        ]);

        Event::dispatch(new AlertEscalated($alert, $similarAlerts));

        Log::warning('Alert escalated', [
            'alert_id'      => $alert->alert_id,
            'similar_count' => $similarAlerts->count(),
        ]);
    }

    private function generateEscalationReason(ComplianceAlert $alert, Collection $similarAlerts): string
    {
        if ($alert->severity === 'critical') {
            return 'Critical severity alert requires immediate attention';
        }

        if ($alert->risk_score >= 80) {
            return 'High risk score (' . $alert->risk_score . ') requires escalation';
        }

        if ($similarAlerts->count() > 0) {
            return 'Multiple similar alerts detected (' . $similarAlerts->count() . ' similar alerts in 24h)';
        }

        return 'Escalation threshold reached';
    }

    private function autoAssignAlert(ComplianceAlert $alert): void
    {
        // Auto-assignment logic based on rules
        // This would typically involve checking user roles, workload, expertise, etc.

        // Example: Assign high severity alerts to senior analysts
        if ($alert->severity === 'high' || $alert->severity === 'critical') {
            try {
                $seniorAnalyst = User::role('senior_compliance_analyst')->first();
                if ($seniorAnalyst) {
                    $this->assignAlert($alert, $seniorAnalyst);
                }
            } catch (Exception $e) {
                // Role doesn't exist or no users with this role - skip auto-assignment
                Log::debug('Auto-assignment skipped: ' . $e->getMessage());
            }
        }
    }

    private function sendAlertNotifications(ComplianceAlert $alert): void
    {
        // Send notifications based on severity and type
        if ($alert->severity === 'critical') {
            // Immediate notification to compliance team
            try {
                $complianceTeam = User::role(['compliance_officer', 'senior_compliance_analyst'])->get();
                if ($complianceTeam->isNotEmpty()) {
                    Notification::send($complianceTeam, new \App\Notifications\CriticalComplianceAlert($alert));
                }
            } catch (Exception $e) {
                // Roles don't exist - skip team notification
                Log::debug('Compliance team notification skipped: ' . $e->getMessage());
            }
        }

        // Send to assigned user if any
        if ($alert->assigned_to) {
            $assignee = User::find($alert->assigned_to);
            $assignee?->notify(new \App\Notifications\AlertAssigned($alert));
        }
    }

    private function handleAlertResolution(ComplianceAlert $alert, ?User $user, ?string $notes): void
    {
        $alert->update([
            'resolved_at'           => now(),
            'resolved_by'           => $user?->id,
            'resolution_notes'      => $notes,
            'resolution_time_hours' => $alert->created_at->diffInHours(now()),
        ]);

        Event::dispatch(new AlertResolved($alert));

        // Update rule effectiveness if applicable
        if ($alert->rule_id) {
            $this->updateRuleEffectiveness($alert->rule_id, true);
        }
    }

    private function handleAlertEscalation(ComplianceAlert $alert, ?User $user, ?string $notes): void
    {
        // Create high priority case if not already linked
        if (! $alert->case_id) {
            $case = $this->createCaseFromAlerts(
                [$alert->id],
                [
                    'title'       => 'Escalated Alert: ' . $alert->title,
                    'description' => $notes ?? $alert->description,
                    'priority'    => 'high',
                    'created_by'  => $user?->id,
                ]
            );

            $alert->update(['case_id' => $case->id]);
        }
    }

    private function handleFalsePositive(ComplianceAlert $alert, ?User $user, ?string $notes): void
    {
        $alert->update([
            'resolved_at'          => now(),
            'resolved_by'          => $user?->id,
            'false_positive_notes' => $notes,
        ]);

        // Update rule effectiveness
        if ($alert->rule_id) {
            $this->updateRuleEffectiveness($alert->rule_id, false);
        }

        // Log for pattern analysis
        Log::info('False positive alert', [
            'alert_id' => $alert->alert_id,
            'rule_id'  => $alert->rule_id,
            'notes'    => $notes,
        ]);
    }

    private function updateRuleEffectiveness(string $ruleId, bool $truePositive): void
    {
        $rule = \App\Domain\Compliance\Models\TransactionMonitoringRule::find($ruleId);

        if ($rule) {
            if ($truePositive) {
                $rule->increment('true_positives');
            } else {
                $rule->increment('false_positives');
            }

            // Recalculate accuracy
            $total = $rule->true_positives + $rule->false_positives;
            if ($total > 0) {
                $rule->update([
                    'accuracy_rate' => ($rule->true_positives / $total) * 100,
                ]);
            }
        }
    }

    private function determineCasePriority(Collection $alerts): string
    {
        $maxSeverity = $alerts->pluck('severity')->map(function ($severity) {
            return match ($severity) {
                'critical' => 4,
                'high'     => 3,
                'medium'   => 2,
                'low'      => 1,
                default    => 0,
            };
        })->max();

        return match ($maxSeverity) {
            4       => 'critical',
            3       => 'high',
            2       => 'medium',
            1       => 'low',
            default => 'medium',
        };
    }

    private function generateCaseDescription(Collection $alerts): string
    {
        $types = $alerts->pluck('type')->unique()->implode(', ');
        $count = $alerts->count();
        $riskScore = $alerts->avg('risk_score');

        return "Investigation case for {$count} alerts of type(s): {$types}. Average risk score: " . round($riskScore, 2);
    }

    private function extractEntitiesFromAlerts(Collection $alerts): array
    {
        $entities = [];

        foreach ($alerts as $alert) {
            if ($alert->entity_type && $alert->entity_id) {
                $entities[] = [
                    'type' => $alert->entity_type,
                    'id'   => $alert->entity_id,
                ];
            }
        }

        return array_unique($entities, SORT_REGULAR);
    }

    private function consolidateEvidence(Collection $alerts): array
    {
        $evidence = [];

        foreach ($alerts as $alert) {
            if ($alert->evidence) {
                $evidence[] = [
                    'alert_id'    => $alert->alert_id,
                    'evidence'    => $alert->evidence,
                    'detected_at' => $alert->detected_at,
                ];
            }
        }

        return $evidence;
    }

    private function calculateAverageResolutionTime($query): float
    {
        $resolved = clone $query;
        $resolved->whereNotNull('resolved_at');

        $avg = $resolved->avg('resolution_time_hours');

        return $avg ?? 0;
    }

    private function calculateFalsePositiveRate($query): float
    {
        $total = clone $query;
        $totalCount = $total->count();

        if ($totalCount === 0) {
            return 0;
        }

        $falsePositives = clone $query;
        $falsePositiveCount = $falsePositives->where('status', ComplianceAlert::STATUS_FALSE_POSITIVE)->count();

        return ($falsePositiveCount / $totalCount) * 100;
    }

    private function calculateEscalationRate($query): float
    {
        $total = clone $query;
        $totalCount = $total->count();

        if ($totalCount === 0) {
            return 0;
        }

        $escalated = clone $query;
        $escalatedCount = $escalated->where('status', ComplianceAlert::STATUS_ESCALATED)->count();

        return ($escalatedCount / $totalCount) * 100;
    }
}
