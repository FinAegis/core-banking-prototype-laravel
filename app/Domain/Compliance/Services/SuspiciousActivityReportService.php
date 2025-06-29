<?php

namespace App\Domain\Compliance\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\SuspiciousActivityReport;
use App\Domain\Compliance\Events\SARCreated;
use App\Domain\Compliance\Events\SARSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuspiciousActivityReportService
{
    /**
     * Create SAR from transaction
     */
    public function createFromTransaction(Transaction $transaction, array $alerts): SuspiciousActivityReport
    {
        return DB::transaction(function () use ($transaction, $alerts) {
            $account = $transaction->account;
            $user = $account->user;
            
            // Determine priority based on alerts
            $priority = $this->determinePriority($alerts);
            
            // Get related transactions
            $relatedTransactions = $this->findRelatedTransactions($transaction);
            
            $sar = SuspiciousActivityReport::create([
                'status' => SuspiciousActivityReport::STATUS_DRAFT,
                'priority' => $priority,
                'subject_user_id' => $user?->id,
                'subject_type' => SuspiciousActivityReport::SUBJECT_TYPE_TRANSACTION,
                'subject_details' => [
                    'name' => $user?->name ?? 'Unknown',
                    'account_number' => $account->account_number,
                    'user_id' => $user?->id,
                ],
                'activity_start_date' => $relatedTransactions->min('created_at') ?? $transaction->created_at,
                'activity_end_date' => $relatedTransactions->max('created_at') ?? $transaction->created_at,
                'total_amount' => $relatedTransactions->sum('amount'),
                'primary_currency' => $transaction->currency,
                'transaction_count' => $relatedTransactions->count(),
                'involved_accounts' => [$account->id],
                'involved_parties' => $this->extractInvolvedParties($relatedTransactions),
                'activity_types' => $this->determineActivityTypes($alerts),
                'activity_description' => $this->generateActivityDescription($transaction, $alerts),
                'red_flags' => $this->extractRedFlags($alerts),
                'triggering_rules' => array_column($alerts, 'rule_id'),
                'related_transactions' => $relatedTransactions->pluck('id')->toArray(),
            ]);
            
            event(new SARCreated($sar));
            
            return $sar;
        });
    }
    
    /**
     * Create SAR from detected pattern
     */
    public function createFromPattern(array $pattern, Collection $transactions): SuspiciousActivityReport
    {
        return DB::transaction(function () use ($pattern, $transactions) {
            $accounts = $transactions->pluck('account')->unique('id');
            $users = $accounts->pluck('user')->filter()->unique('id');
            
            $sar = SuspiciousActivityReport::create([
                'status' => SuspiciousActivityReport::STATUS_DRAFT,
                'priority' => SuspiciousActivityReport::PRIORITY_HIGH,
                'subject_user_id' => $users->count() === 1 ? $users->first()->id : null,
                'subject_type' => SuspiciousActivityReport::SUBJECT_TYPE_PATTERN,
                'subject_details' => [
                    'pattern_type' => $pattern['type'],
                    'accounts' => $accounts->pluck('account_number')->toArray(),
                    'users' => $users->pluck('name')->toArray(),
                ],
                'activity_start_date' => $transactions->min('created_at'),
                'activity_end_date' => $transactions->max('created_at'),
                'total_amount' => $transactions->sum('amount'),
                'primary_currency' => $transactions->first()->currency,
                'transaction_count' => $transactions->count(),
                'involved_accounts' => $accounts->pluck('id')->toArray(),
                'involved_parties' => $this->extractInvolvedParties($transactions),
                'activity_types' => $this->getActivityTypesForPattern($pattern['type']),
                'activity_description' => $this->generatePatternDescription($pattern, $transactions),
                'red_flags' => $this->getRedFlagsForPattern($pattern['type']),
                'triggering_rules' => [],
                'related_transactions' => $transactions->pluck('id')->toArray(),
            ]);
            
            event(new SARCreated($sar));
            
            return $sar;
        });
    }
    
    /**
     * Update SAR investigation
     */
    public function updateInvestigation(
        SuspiciousActivityReport $sar,
        User $investigator,
        string $findings,
        array $additionalData = []
    ): void {
        if (!$sar->investigator_id) {
            $sar->startInvestigation($investigator);
        }
        
        $sar->update(array_merge($additionalData, [
            'investigation_findings' => $findings,
        ]));
        
        if (isset($additionalData['complete']) && $additionalData['complete']) {
            $sar->completeInvestigation($findings);
        }
    }
    
    /**
     * Submit SAR to regulator
     */
    public function submitToRegulator(SuspiciousActivityReport $sar): array
    {
        // Validate SAR is complete
        $validation = $this->validateForSubmission($sar);
        if (!$validation['valid']) {
            throw new \Exception('SAR validation failed: ' . implode(', ', $validation['errors']));
        }
        
        // In production, this would submit to FinCEN or appropriate regulator
        // For now, simulate submission
        $submissionResult = $this->simulateRegulatorySubmission($sar);
        
        if ($submissionResult['success']) {
            $sar->fileWithRegulator(
                $submissionResult['reference'],
                $submissionResult['jurisdiction']
            );
            
            event(new SARSubmitted($sar));
        }
        
        return $submissionResult;
    }
    
    /**
     * Find related transactions
     */
    protected function findRelatedTransactions(Transaction $transaction): Collection
    {
        // Find transactions that might be related
        $account = $transaction->account;
        $timeWindow = now()->subDays(30);
        
        return Transaction::where('account_id', $account->id)
            ->where('created_at', '>=', $timeWindow)
            ->where(function ($query) use ($transaction) {
                // Similar amounts (within 10%)
                $query->whereBetween('amount', [
                    $transaction->amount * 0.9,
                    $transaction->amount * 1.1,
                ])
                // Or same day
                ->orWhereDate('created_at', $transaction->created_at->toDateString());
            })
            ->get();
    }
    
    /**
     * Extract involved parties from transactions
     */
    protected function extractInvolvedParties(Collection $transactions): array
    {
        $parties = [];
        
        foreach ($transactions as $transaction) {
            $metadata = $transaction->metadata ?? [];
            
            // Extract counterparties from metadata
            if (isset($metadata['counterparty'])) {
                $parties[] = $metadata['counterparty'];
            }
            if (isset($metadata['beneficiary'])) {
                $parties[] = $metadata['beneficiary'];
            }
            if (isset($metadata['originator'])) {
                $parties[] = $metadata['originator'];
            }
        }
        
        return array_values(array_unique($parties));
    }
    
    /**
     * Determine priority based on alerts
     */
    protected function determinePriority(array $alerts): string
    {
        $highRiskCount = 0;
        
        foreach ($alerts as $alert) {
            if ($alert['risk_level'] === 'high') {
                $highRiskCount++;
            }
        }
        
        if ($highRiskCount >= 3) {
            return SuspiciousActivityReport::PRIORITY_CRITICAL;
        } elseif ($highRiskCount >= 1) {
            return SuspiciousActivityReport::PRIORITY_HIGH;
        } elseif (count($alerts) >= 3) {
            return SuspiciousActivityReport::PRIORITY_MEDIUM;
        } else {
            return SuspiciousActivityReport::PRIORITY_LOW;
        }
    }
    
    /**
     * Determine activity types from alerts
     */
    protected function determineActivityTypes(array $alerts): array
    {
        $types = [];
        
        foreach ($alerts as $alert) {
            if (str_contains($alert['rule_name'], 'Structuring')) {
                $types[] = 'structuring';
            }
            if (str_contains($alert['rule_name'], 'Rapid Movement')) {
                $types[] = 'layering';
            }
            if (str_contains($alert['rule_name'], 'High-Risk Geography')) {
                $types[] = 'other';
            }
        }
        
        return array_unique($types) ?: ['other'];
    }
    
    /**
     * Extract red flags from alerts
     */
    protected function extractRedFlags(array $alerts): array
    {
        $redFlags = [];
        
        foreach ($alerts as $alert) {
            switch ($alert['category']) {
                case 'velocity':
                    $redFlags[] = 'rapid_movement';
                    break;
                case 'pattern':
                    $redFlags[] = 'unusual_transaction_pattern';
                    break;
                case 'geography':
                    $redFlags[] = 'high_risk_geography';
                    break;
                case 'behavior':
                    $redFlags[] = 'inconsistent_activity';
                    break;
            }
        }
        
        return array_unique($redFlags);
    }
    
    /**
     * Generate activity description
     */
    protected function generateActivityDescription(Transaction $transaction, array $alerts): string
    {
        $description = sprintf(
            "Suspicious activity detected on account %s. ",
            $transaction->account->account_number
        );
        
        $alertDescriptions = array_map(fn($alert) => $alert['description'], $alerts);
        $description .= "Alerts triggered: " . implode('; ', $alertDescriptions) . ". ";
        
        $description .= sprintf(
            "Transaction amount: %s %s. ",
            $transaction->currency,
            number_format($transaction->amount, 2)
        );
        
        return $description;
    }
    
    /**
     * Get activity types for pattern
     */
    protected function getActivityTypesForPattern(string $patternType): array
    {
        return match($patternType) {
            'smurfing' => ['structuring'],
            'layering' => ['layering'],
            'rapid_movement' => ['layering'],
            default => ['other'],
        };
    }
    
    /**
     * Get red flags for pattern
     */
    protected function getRedFlagsForPattern(string $patternType): array
    {
        return match($patternType) {
            'smurfing' => ['unusual_transaction_pattern', 'multiple_accounts'],
            'layering' => ['rapid_movement', 'complex_structure'],
            'rapid_movement' => ['rapid_movement', 'no_business_purpose'],
            default => ['unusual_transaction_pattern'],
        };
    }
    
    /**
     * Generate pattern description
     */
    protected function generatePatternDescription(array $pattern, Collection $transactions): string
    {
        $patternDescriptions = [
            'smurfing' => 'Multiple small transactions detected that appear to be structured to avoid reporting thresholds',
            'layering' => 'Complex series of transactions detected that may be attempting to obscure the source of funds',
            'rapid_movement' => 'Rapid movement of funds detected through accounts with minimal holding time',
        ];
        
        $description = $patternDescriptions[$pattern['type']] ?? 'Suspicious pattern detected';
        
        $description .= sprintf(
            '. Pattern involves %d transactions totaling %s across %d accounts over %d days.',
            $transactions->count(),
            number_format($transactions->sum('amount'), 2),
            $transactions->pluck('account_id')->unique()->count(),
            $transactions->min('created_at')->diffInDays($transactions->max('created_at'))
        );
        
        return $description;
    }
    
    /**
     * Validate SAR for submission
     */
    protected function validateForSubmission(SuspiciousActivityReport $sar): array
    {
        $errors = [];
        
        // Required fields
        if (empty($sar->activity_description)) {
            $errors[] = 'Activity description is required';
        }
        
        if (empty($sar->investigation_findings)) {
            $errors[] = 'Investigation findings are required';
        }
        
        if (!$sar->decision) {
            $errors[] = 'Decision is required';
        }
        
        if (!$sar->decision_rationale) {
            $errors[] = 'Decision rationale is required';
        }
        
        if ($sar->transaction_count === 0) {
            $errors[] = 'At least one transaction must be linked';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Simulate regulatory submission
     */
    protected function simulateRegulatorySubmission(SuspiciousActivityReport $sar): array
    {
        // In production, this would connect to FinCEN BSA E-Filing or similar
        
        return [
            'success' => true,
            'reference' => 'BSA-' . now()->format('Y') . '-' . rand(100000, 999999),
            'jurisdiction' => 'US-FinCEN',
            'submitted_at' => now()->toIso8601String(),
            'acknowledgment' => [
                'received' => true,
                'tracking_number' => 'ACK-' . uniqid(),
                'estimated_processing_time' => '3-5 business days',
            ],
        ];
    }
    
    /**
     * Generate SAR report for internal use
     */
    public function generateReport(SuspiciousActivityReport $sar): array
    {
        return [
            'sar_number' => $sar->sar_number,
            'status' => $sar->status,
            'priority' => $sar->priority,
            'filing_status' => $sar->filed_with_regulator ? 'Filed' : 'Not Filed',
            'filing_reference' => $sar->filing_reference,
            'subject' => $sar->subject_details,
            'activity_period' => [
                'start' => $sar->activity_start_date->toDateString(),
                'end' => $sar->activity_end_date->toDateString(),
                'duration_days' => $sar->getActivityDuration(),
            ],
            'financial_summary' => [
                'total_amount' => $sar->total_amount,
                'transaction_count' => $sar->transaction_count,
                'primary_currency' => $sar->primary_currency,
            ],
            'suspicious_activity' => [
                'types' => $sar->activity_types,
                'description' => $sar->activity_description,
                'red_flags' => $sar->red_flags,
            ],
            'investigation' => [
                'investigator' => $sar->investigator?->name,
                'started_at' => $sar->investigation_started_at?->toDateTimeString(),
                'completed_at' => $sar->investigation_completed_at?->toDateTimeString(),
                'findings' => $sar->investigation_findings,
            ],
            'decision' => [
                'decision' => $sar->decision,
                'rationale' => $sar->decision_rationale,
                'decision_maker' => $sar->decisionMaker?->name,
                'decision_date' => $sar->decision_date?->toDateTimeString(),
            ],
        ];
    }
}