<?php

declare(strict_types=1);

namespace Plugins\Zelta\ComplianceNotifier;

use App\Infrastructure\Plugins\PluginHookInterface;
use App\Infrastructure\Plugins\PluginHookManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Compliance Notifier Plugin — Example demonstrating api:external permission.
 *
 * Sends webhook notifications (Slack, Discord, custom) when compliance
 * events fire. Shows multi-hook registration and external HTTP calls.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        /** @var PluginHookManager $hookManager */
        $hookManager = $this->app->make(PluginHookManager::class);

        $hookManager->register(new ComplianceAlertListener());
        $hookManager->register(new KycStatusListener());
    }
}

/**
 * Sends webhook notification on compliance alerts (AML, sanctions, etc.).
 */
class ComplianceAlertListener implements PluginHookInterface
{
    public function getHookName(): string
    {
        return 'compliance.alert';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function handle(array $payload): void
    {
        $webhookUrl = config('plugins.compliance_notifier.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $alertType = $payload['type'] ?? 'unknown';
        $severity = $payload['severity'] ?? 'info';

        try {
            Http::timeout(5)->post($webhookUrl, [
                'text' => "[{$severity}] Compliance alert: {$alertType}",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Compliance Alert*\n"
                                . "Type: `{$alertType}`\n"
                                . "Severity: `{$severity}`\n"
                                . "Time: " . now()->toIso8601String(),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ComplianceNotifier: webhook failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

/**
 * Sends webhook notification on KYC verification status changes.
 */
class KycStatusListener implements PluginHookInterface
{
    public function getHookName(): string
    {
        return 'compliance.kyc';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function handle(array $payload): void
    {
        $webhookUrl = config('plugins.compliance_notifier.webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $status = $payload['status'] ?? 'unknown';
        $userId = $payload['user_id'] ?? 'unknown';

        try {
            Http::timeout(5)->post($webhookUrl, [
                'text' => "KYC status changed: user {$userId} → {$status}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('ComplianceNotifier: KYC webhook failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
