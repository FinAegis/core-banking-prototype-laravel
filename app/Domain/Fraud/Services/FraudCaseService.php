<?php

namespace App\Domain\Fraud\Services;

use App\Models\FraudCase;
use App\Models\FraudScore;
use App\Models\User;
use App\Models\Transaction;
use App\Domain\Fraud\Events\FraudCaseCreated;
use App\Domain\Fraud\Events\FraudCaseUpdated;
use App\Domain\Fraud\Events\FraudCaseResolved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class FraudCaseService
{
    /**
     * Create fraud case from fraud score
     */
    public function createFromFraudScore(FraudScore $fraudScore): FraudCase
    {
        return DB::transaction(function () use ($fraudScore) {
            // Determine priority based on risk level
            $priority = $this->determinePriority($fraudScore);
            
            // Create case
            $case = FraudCase::create([
                'case_number' => $this->generateCaseNumber(),
                'fraud_score_id' => $fraudScore->id,
                'entity_id' => $fraudScore->entity_id,
                'entity_type' => $fraudScore->entity_type,
                'priority' => $priority,
                'risk_level' => $fraudScore->risk_level,
                'total_score' => $fraudScore->total_score,
                'triggered_rules' => $fraudScore->triggered_rules,
                'risk_factors' => $this->extractRiskFactors($fraudScore),
                'entity_snapshot' => $fraudScore->entity_snapshot,
                'initial_decision' => $fraudScore->decision,
                'status' => FraudCase::STATUS_OPEN,
                'assigned_to' => $this->autoAssign($priority),
                'investigation_notes' => [[
                    'timestamp' => now()->toIso8601String(),
                    'type' => 'system',
                    'note' => 'Case created automatically from fraud detection system',
                    'author' => 'System',
                ]],
            ]);
            
            // Link related entities
            $this->linkRelatedEntities($case, $fraudScore);
            
            // Calculate loss amount if transaction
            if ($fraudScore->entity_type === Transaction::class) {
                $transaction = Transaction::find($fraudScore->entity_id);
                if ($transaction) {
                    $case->update(['loss_amount' => $transaction->amount]);
                }
            }
            
            event(new FraudCaseCreated($case));
            
            return $case;
        });
    }
    
    /**
     * Update case investigation
     */
    public function updateInvestigation(FraudCase $case, array $data): FraudCase
    {
        DB::transaction(function () use ($case, &$data) {
            // Add investigation note
            if (isset($data['note'])) {
                $case->addInvestigationNote(
                    $data['note'],
                    Auth::user()->name ?? 'Investigator',
                    $data['note_type'] ?? 'investigation'
                );
            }
            
            // Update evidence
            if (isset($data['evidence'])) {
                $this->addEvidence($case, $data['evidence']);
            }
            
            // Update status
            if (isset($data['status'])) {
                $this->updateStatus($case, $data['status']);
            }
            
            // Update fields
            $case->update([
                'assigned_to' => $data['assigned_to'] ?? $case->assigned_to,
                'priority' => $data['priority'] ?? $case->priority,
                'tags' => $data['tags'] ?? $case->tags,
            ]);
            
            event(new FraudCaseUpdated($case));
        });
        
        return $case->fresh();
    }
    
    /**
     * Resolve fraud case
     */
    public function resolveCase(FraudCase $case, string $resolution, string $outcome): FraudCase
    {
        DB::transaction(function () use ($case, $resolution, $outcome) {
            $case->update([
                'status' => FraudCase::STATUS_CLOSED,
                'resolution' => $resolution,
                'outcome' => $outcome,
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
                'resolution_notes' => [
                    'resolution' => $resolution,
                    'outcome' => $outcome,
                    'resolved_by' => Auth::user()->name ?? 'Unknown',
                    'resolved_at' => now()->toIso8601String(),
                ],
            ]);
            
            // Update fraud score outcome
            if ($case->fraudScore) {
                $case->fraudScore->update(['outcome' => $outcome]);
                
                // Train ML model with outcome
                app(MachineLearningService::class)->trainWithFeedback(
                    $case->fraudScore,
                    $outcome
                );
            }
            
            // Calculate recovery if applicable
            if ($outcome === FraudCase::OUTCOME_FRAUD && $case->recovery_amount > 0) {
                $case->update([
                    'recovery_percentage' => ($case->recovery_amount / $case->loss_amount) * 100,
                ]);
            }
            
            // Update related entities based on outcome
            $this->updateRelatedEntities($case, $outcome);
            
            // Add resolution note
            $case->addInvestigationNote(
                "Case resolved: {$resolution}",
                Auth::user()->name ?? 'System',
                'resolution'
            );
            
            event(new FraudCaseResolved($case));
        });
        
        return $case->fresh();
    }
    
    /**
     * Search fraud cases
     */
    public function searchCases(array $filters): Collection
    {
        $query = FraudCase::query();
        
        // Status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Priority filter
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        // Risk level filter
        if (isset($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }
        
        // Date range
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Assigned to
        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        
        // Amount range
        if (isset($filters['min_amount'])) {
            $query->where('loss_amount', '>=', $filters['min_amount']);
        }
        
        if (isset($filters['max_amount'])) {
            $query->where('loss_amount', '<=', $filters['max_amount']);
        }
        
        // Search term
        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('case_number', 'like', "%{$searchTerm}%")
                  ->orWhere('entity_id', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('tags', $searchTerm);
            });
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->paginate($filters['per_page'] ?? 20);
    }
    
    /**
     * Get case statistics
     */
    public function getCaseStatistics(array $filters = []): array
    {
        $baseQuery = FraudCase::query();
        
        // Apply date filters if provided
        if (isset($filters['date_from'])) {
            $baseQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $baseQuery->where('created_at', '<=', $filters['date_to']);
        }
        
        return [
            'total_cases' => $baseQuery->count(),
            'open_cases' => (clone $baseQuery)->where('status', FraudCase::STATUS_OPEN)->count(),
            'investigating_cases' => (clone $baseQuery)->where('status', FraudCase::STATUS_INVESTIGATING)->count(),
            'closed_cases' => (clone $baseQuery)->where('status', FraudCase::STATUS_CLOSED)->count(),
            
            'by_priority' => [
                'critical' => (clone $baseQuery)->where('priority', FraudCase::PRIORITY_CRITICAL)->count(),
                'high' => (clone $baseQuery)->where('priority', FraudCase::PRIORITY_HIGH)->count(),
                'medium' => (clone $baseQuery)->where('priority', FraudCase::PRIORITY_MEDIUM)->count(),
                'low' => (clone $baseQuery)->where('priority', FraudCase::PRIORITY_LOW)->count(),
            ],
            
            'by_outcome' => [
                'fraud' => (clone $baseQuery)->where('outcome', FraudCase::OUTCOME_FRAUD)->count(),
                'legitimate' => (clone $baseQuery)->where('outcome', FraudCase::OUTCOME_LEGITIMATE)->count(),
                'unknown' => (clone $baseQuery)->where('outcome', FraudCase::OUTCOME_UNKNOWN)->count(),
            ],
            
            'financial_impact' => [
                'total_loss' => (clone $baseQuery)->sum('loss_amount'),
                'total_recovered' => (clone $baseQuery)->sum('recovery_amount'),
                'average_loss' => (clone $baseQuery)->avg('loss_amount'),
                'average_recovery_rate' => (clone $baseQuery)->avg('recovery_percentage'),
            ],
            
            'performance' => [
                'average_resolution_time' => $this->calculateAverageResolutionTime($baseQuery),
                'cases_per_investigator' => $this->getCasesPerInvestigator($baseQuery),
            ],
        ];
    }
    
    /**
     * Escalate case
     */
    public function escalateCase(FraudCase $case, string $reason): FraudCase
    {
        $newPriority = match($case->priority) {
            FraudCase::PRIORITY_LOW => FraudCase::PRIORITY_MEDIUM,
            FraudCase::PRIORITY_MEDIUM => FraudCase::PRIORITY_HIGH,
            FraudCase::PRIORITY_HIGH => FraudCase::PRIORITY_CRITICAL,
            default => FraudCase::PRIORITY_CRITICAL,
        };
        
        $case->update([
            'priority' => $newPriority,
            'escalated' => true,
            'escalation_reason' => $reason,
            'escalated_at' => now(),
        ]);
        
        $case->addInvestigationNote(
            "Case escalated to {$newPriority} priority. Reason: {$reason}",
            Auth::user()->name ?? 'System',
            'escalation'
        );
        
        event(new FraudCaseUpdated($case));
        
        return $case;
    }
    
    /**
     * Link similar cases
     */
    public function linkSimilarCases(FraudCase $case): Collection
    {
        // Find similar cases based on various criteria
        $similarCases = FraudCase::where('id', '!=', $case->id)
            ->where(function ($query) use ($case) {
                // Same entity
                $query->where(function ($q) use ($case) {
                    $q->where('entity_id', $case->entity_id)
                      ->where('entity_type', $case->entity_type);
                });
                
                // Similar risk factors
                if (!empty($case->risk_factors)) {
                    $query->orWhere(function ($q) use ($case) {
                        foreach ($case->risk_factors as $factor) {
                            $q->orWhereJsonContains('risk_factors', $factor);
                        }
                    });
                }
                
                // Similar triggered rules
                if (!empty($case->triggered_rules)) {
                    $query->orWhere(function ($q) use ($case) {
                        foreach ($case->triggered_rules as $rule) {
                            $q->orWhereJsonContains('triggered_rules', $rule);
                        }
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Update case with related cases
        if ($similarCases->isNotEmpty()) {
            $relatedCaseIds = $similarCases->pluck('id')->toArray();
            $case->update(['related_cases' => $relatedCaseIds]);
        }
        
        return $similarCases;
    }
    
    /**
     * Generate case number
     */
    protected function generateCaseNumber(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        
        $lastCase = FraudCase::where('case_number', 'like', "FC-{$year}{$month}-%")
            ->orderBy('case_number', 'desc')
            ->first();
        
        if ($lastCase) {
            $lastNumber = intval(substr($lastCase->case_number, -6));
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }
        
        return "FC-{$year}{$month}-{$newNumber}";
    }
    
    /**
     * Determine priority based on fraud score
     */
    protected function determinePriority(FraudScore $fraudScore): string
    {
        if ($fraudScore->total_score >= 90 || !empty($fraudScore->triggered_rules)) {
            return FraudCase::PRIORITY_CRITICAL;
        } elseif ($fraudScore->total_score >= 70) {
            return FraudCase::PRIORITY_HIGH;
        } elseif ($fraudScore->total_score >= 50) {
            return FraudCase::PRIORITY_MEDIUM;
        } else {
            return FraudCase::PRIORITY_LOW;
        }
    }
    
    /**
     * Auto-assign case to investigator
     */
    protected function autoAssign(string $priority): ?int
    {
        // In production, implement round-robin or workload-based assignment
        // For now, return null (unassigned)
        return null;
    }
    
    /**
     * Extract risk factors from fraud score
     */
    protected function extractRiskFactors(FraudScore $fraudScore): array
    {
        $riskFactors = [];
        
        // From behavioral analysis
        if (isset($fraudScore->behavioral_factors['risk_factors'])) {
            $riskFactors = array_merge($riskFactors, $fraudScore->behavioral_factors['risk_factors']);
        }
        
        // From device analysis
        if (isset($fraudScore->device_factors['risk_factors'])) {
            $riskFactors = array_merge($riskFactors, $fraudScore->device_factors['risk_factors']);
        }
        
        // From ML analysis
        if (isset($fraudScore->ml_explanation['risk_factors'])) {
            $riskFactors = array_merge($riskFactors, $fraudScore->ml_explanation['risk_factors']);
        }
        
        return array_unique($riskFactors);
    }
    
    /**
     * Link related entities to case
     */
    protected function linkRelatedEntities(FraudCase $case, FraudScore $fraudScore): void
    {
        $relatedEntities = [];
        
        if ($fraudScore->entity_type === Transaction::class) {
            $transaction = Transaction::find($fraudScore->entity_id);
            if ($transaction) {
                $relatedEntities[] = [
                    'type' => 'user',
                    'id' => $transaction->account->user_id,
                    'description' => 'Transaction owner',
                ];
                
                $relatedEntities[] = [
                    'type' => 'account',
                    'id' => $transaction->account_id,
                    'description' => 'Source account',
                ];
                
                // Add recipient if available
                if (isset($transaction->metadata['recipient_account_id'])) {
                    $relatedEntities[] = [
                        'type' => 'account',
                        'id' => $transaction->metadata['recipient_account_id'],
                        'description' => 'Recipient account',
                    ];
                }
            }
        } elseif ($fraudScore->entity_type === User::class) {
            // Add user's accounts
            $user = User::find($fraudScore->entity_id);
            if ($user) {
                foreach ($user->accounts as $account) {
                    $relatedEntities[] = [
                        'type' => 'account',
                        'id' => $account->id,
                        'description' => 'User account',
                    ];
                }
            }
        }
        
        $case->update(['related_entities' => $relatedEntities]);
    }
    
    /**
     * Add evidence to case
     */
    protected function addEvidence(FraudCase $case, array $evidence): void
    {
        $currentEvidence = $case->evidence ?? [];
        
        $newEvidence = [
            'id' => uniqid('evidence_'),
            'type' => $evidence['type'] ?? 'document',
            'description' => $evidence['description'],
            'added_by' => Auth::user()->name ?? 'Unknown',
            'added_at' => now()->toIso8601String(),
            'metadata' => $evidence['metadata'] ?? [],
        ];
        
        if (isset($evidence['file_path'])) {
            $newEvidence['file_path'] = $evidence['file_path'];
        }
        
        $currentEvidence[] = $newEvidence;
        $case->update(['evidence' => $currentEvidence]);
    }
    
    /**
     * Update case status
     */
    protected function updateStatus(FraudCase $case, string $newStatus): void
    {
        $oldStatus = $case->status;
        
        $case->update([
            'status' => $newStatus,
            'status_changed_at' => now(),
        ]);
        
        if ($newStatus === FraudCase::STATUS_INVESTIGATING && !$case->investigation_started_at) {
            $case->update(['investigation_started_at' => now()]);
        }
        
        $case->addInvestigationNote(
            "Status changed from {$oldStatus} to {$newStatus}",
            Auth::user()->name ?? 'System',
            'status_change'
        );
    }
    
    /**
     * Update related entities based on case outcome
     */
    protected function updateRelatedEntities(FraudCase $case, string $outcome): void
    {
        if ($outcome === FraudCase::OUTCOME_FRAUD) {
            // If fraud confirmed, take protective actions
            foreach ($case->related_entities ?? [] as $entity) {
                if ($entity['type'] === 'user') {
                    $user = User::find($entity['id']);
                    if ($user) {
                        $user->update(['risk_rating' => 'high']);
                    }
                }
            }
        }
    }
    
    /**
     * Calculate average resolution time
     */
    protected function calculateAverageResolutionTime($baseQuery): ?float
    {
        $resolvedCases = (clone $baseQuery)
            ->whereNotNull('resolved_at')
            ->get();
        
        if ($resolvedCases->isEmpty()) {
            return null;
        }
        
        $totalHours = 0;
        foreach ($resolvedCases as $case) {
            $totalHours += $case->created_at->diffInHours($case->resolved_at);
        }
        
        return round($totalHours / $resolvedCases->count(), 2);
    }
    
    /**
     * Get cases per investigator
     */
    protected function getCasesPerInvestigator($baseQuery): array
    {
        return (clone $baseQuery)
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as case_count')
            ->pluck('case_count', 'assigned_to')
            ->toArray();
    }
}