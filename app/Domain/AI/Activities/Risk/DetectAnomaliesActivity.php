<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use App\Models\User;
use Workflow\Activity;

/**
 * Detect Anomalies Activity.
 *
 * Identifies unusual patterns and anomalies in transactions.
 */
class DetectAnomaliesActivity extends Activity
{
    /**
     * Execute anomaly detection.
     *
     * @param array{user_id: string, amount: float, recipient: ?string} $input
     *
     * @return array{detected: array<string>, risk_score: float}
     */
    public function execute(array $input): array
    {
        $userId = $input['user_id'] ?? '';
        $amount = $input['amount'] ?? 0;
        $recipient = $input['recipient'] ?? null;

        $anomalies = [];

        // Check for large transaction amounts
        if ($amount > 50000) {
            $anomalies[] = 'Large transaction amount';
        }

        // Check for unusual amounts (not round numbers)
        if ($this->isUnusualAmount($amount)) {
            $anomalies[] = 'Unusual transaction amount pattern';
        }

        // Check for suspicious recipients
        if ($recipient && $this->isSuspiciousRecipient($recipient)) {
            $anomalies[] = 'Suspicious recipient pattern';
        }

        // Check for dormant account reactivation
        if ($this->isDormantAccountReactivated($userId)) {
            $anomalies[] = 'Dormant account suddenly active';
        }

        // Check for unusual time patterns
        if ($this->isUnusualTime()) {
            $anomalies[] = 'Transaction at unusual time';
        }

        // Calculate risk score based on anomalies
        $riskScore = $this->calculateRiskScore($anomalies, $amount);

        return [
            'detected'   => $anomalies,
            'risk_score' => $riskScore,
        ];
    }

    private function isUnusualAmount(float $amount): bool
    {
        // Check if amount has unusual decimal places (potential structuring)
        $decimal = $amount - floor($amount);

        return $decimal > 0 && $decimal != 0.5 && $decimal != 0.25 && $decimal != 0.75;
    }

    private function isSuspiciousRecipient(?string $recipient): bool
    {
        if (! $recipient) {
            return false;
        }

        $suspiciousPatterns = [
            'offshore',
            'crypto',
            'exchange',
            'mixer',
            'anonymous',
            'numbered',
        ];

        $recipientLower = strtolower($recipient);
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($recipientLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isDormantAccountReactivated(string $userId): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        // Check if account was inactive for more than 90 days
        $lastActivity = $user->transactions()
            ->where('transactions.created_at', '<', now()->subDays(90))
            ->orderBy('transactions.created_at', 'desc')
            ->first();

        if ($lastActivity) {
            $recentActivity = $user->transactions()
                ->where('transactions.created_at', '>=', now()->subDays(7))
                ->count();

            return $recentActivity > 5; // Sudden spike in activity
        }

        return false;
    }

    private function isUnusualTime(): bool
    {
        $hour = now()->hour;

        // Flag transactions between 2 AM and 5 AM
        return $hour >= 2 && $hour <= 5;
    }

    private function calculateRiskScore(array $anomalies, float $amount): float
    {
        $baseScore = count($anomalies) * 15;

        // Add score based on amount
        if ($amount > 100000) {
            $baseScore += 30;
        } elseif ($amount > 50000) {
            $baseScore += 20;
        } elseif ($amount > 10000) {
            $baseScore += 10;
        }

        return min($baseScore, 100);
    }
}
