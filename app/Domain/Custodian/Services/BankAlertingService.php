<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Events\CustodianHealthChanged;
use App\Models\User;
use App\Notifications\BankHealthAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BankAlertingService
{
    /**
     * Alert channels configuration
     */
    private array $alertChannels = ['mail', 'database'];
    
    /**
     * Cooldown period for alerts (in minutes)
     */
    private const ALERT_COOLDOWN_MINUTES = 30;
    
    /**
     * Alert severity levels
     */
    private const SEVERITY_INFO = 'info';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_CRITICAL = 'critical';
    
    public function __construct(
        private readonly CustodianHealthMonitor $healthMonitor
    ) {}
    
    /**
     * Handle custodian health change event
     */
    public function handleHealthChange(CustodianHealthChanged $event): void
    {
        $severity = $this->determineSeverity($event->previousStatus, $event->newStatus);
        
        if ($severity === self::SEVERITY_INFO) {
            // Don't alert for info level changes
            return;
        }
        
        // Check if we should send an alert (cooldown period)
        if (!$this->shouldSendAlert($event->custodian, $severity)) {
            return;
        }
        
        // Get alert recipients
        $recipients = $this->getAlertRecipients($severity);
        
        if ($recipients->isEmpty()) {
            Log::warning('No alert recipients configured for bank health alerts');
            return;
        }
        
        // Send alert
        $this->sendAlert($recipients, $event, $severity);
        
        // Log the alert
        Log::warning('Bank health alert sent', [
            'custodian' => $event->custodian,
            'previous_status' => $event->previousStatus,
            'new_status' => $event->newStatus,
            'severity' => $severity,
            'recipients_count' => $recipients->count(),
        ]);
    }
    
    /**
     * Check system-wide bank health and alert if necessary
     */
    public function performHealthCheck(): void
    {
        $allHealth = $this->healthMonitor->getAllCustodiansHealth();
        $unhealthyCount = 0;
        $degradedCount = 0;
        $issues = [];
        
        foreach ($allHealth as $custodian => $health) {
            if ($health['status'] === 'unhealthy') {
                $unhealthyCount++;
                $issues[] = "{$custodian}: Unhealthy (Failure rate: {$health['overall_failure_rate']}%)";
            } elseif ($health['status'] === 'degraded') {
                $degradedCount++;
                $issues[] = "{$custodian}: Degraded (Failure rate: {$health['overall_failure_rate']}%)";
            }
        }
        
        // Determine if we need to alert
        if ($unhealthyCount >= 2 || ($unhealthyCount >= 1 && $degradedCount >= 1)) {
            $this->sendSystemAlert('critical', 'Multiple bank connectors experiencing issues', $issues);
        } elseif ($unhealthyCount === 1) {
            $this->sendSystemAlert('warning', 'Bank connector is unhealthy', $issues);
        } elseif ($degradedCount >= 2) {
            $this->sendSystemAlert('warning', 'Multiple bank connectors degraded', $issues);
        }
    }
    
    /**
     * Determine alert severity based on status change
     */
    private function determineSeverity(string $previousStatus, string $newStatus): string
    {
        // Healthy -> Degraded = Warning
        if ($previousStatus === 'healthy' && $newStatus === 'degraded') {
            return self::SEVERITY_WARNING;
        }
        
        // Healthy/Degraded -> Unhealthy = Critical
        if (in_array($previousStatus, ['healthy', 'degraded']) && $newStatus === 'unhealthy') {
            return self::SEVERITY_CRITICAL;
        }
        
        // Unhealthy/Degraded -> Healthy = Info (recovery)
        if (in_array($previousStatus, ['unhealthy', 'degraded']) && $newStatus === 'healthy') {
            return self::SEVERITY_INFO;
        }
        
        // Degraded -> Healthy = Info
        if ($previousStatus === 'degraded' && $newStatus === 'healthy') {
            return self::SEVERITY_INFO;
        }
        
        return self::SEVERITY_INFO;
    }
    
    /**
     * Check if we should send an alert (respecting cooldown)
     */
    private function shouldSendAlert(string $custodian, string $severity): bool
    {
        $cacheKey = "alert:cooldown:{$custodian}:{$severity}";
        
        if (Cache::has($cacheKey)) {
            return false;
        }
        
        // Set cooldown
        Cache::put($cacheKey, true, now()->addMinutes(self::ALERT_COOLDOWN_MINUTES));
        
        return true;
    }
    
    /**
     * Get alert recipients based on severity
     */
    private function getAlertRecipients(string $severity)
    {
        // In production, this would query users with specific roles/permissions
        // For now, get all users (you can add role/permission filtering later)
        return User::all();
    }
    
    /**
     * Send alert to recipients
     */
    private function sendAlert($recipients, CustodianHealthChanged $event, string $severity): void
    {
        $health = $this->healthMonitor->getCustodianHealth($event->custodian);
        
        $alert = new BankHealthAlert(
            custodian: $event->custodian,
            previousStatus: $event->previousStatus,
            newStatus: $event->newStatus,
            severity: $severity,
            healthData: $health,
            timestamp: $event->timestamp
        );
        
        Notification::send($recipients, $alert);
    }
    
    /**
     * Send system-wide alert
     */
    private function sendSystemAlert(string $severity, string $message, array $issues): void
    {
        if (!$this->shouldSendAlert('system', $severity)) {
            return;
        }
        
        $recipients = $this->getAlertRecipients($severity);
        
        if ($recipients->isEmpty()) {
            return;
        }
        
        Log::alert('System-wide bank health alert', [
            'severity' => $severity,
            'message' => $message,
            'issues' => $issues,
        ]);
        
        // In production, send actual notification
        // For now, just log it
    }
    
    /**
     * Get alert history for a custodian
     */
    public function getAlertHistory(string $custodian, int $days = 7): array
    {
        // In production, this would query from database
        // For now, return sample data
        return [
            'custodian' => $custodian,
            'period_days' => $days,
            'alerts' => [
                [
                    'timestamp' => now()->subDays(2),
                    'severity' => 'warning',
                    'message' => 'Status changed from healthy to degraded',
                ],
                [
                    'timestamp' => now()->subDays(1),
                    'severity' => 'info',
                    'message' => 'Status changed from degraded to healthy',
                ],
            ],
        ];
    }
}