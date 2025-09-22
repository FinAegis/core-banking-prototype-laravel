<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use DB;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending notifications to agents.
 */
class AgentNotificationService
{
    /**
     * Send notification to an agent.
     *
     * @param string $agentDid Agent DID
     * @param string $type Notification type
     * @param array $data Notification data
     * @return bool Whether notification was sent successfully
     */
    public function notify(string $agentDid, string $type, array $data): bool
    {
        try {
            // Get agent's notification endpoint
            $endpoint = $this->getAgentNotificationEndpoint($agentDid);

            if (! $endpoint) {
                // Agent has no notification endpoint configured
                // Store for later delivery
                $this->storeOfflineNotification($agentDid, $type, $data);

                return false;
            }

            // Send notification via webhook
            $response = Http::timeout(10)
                ->retry(3, 100)
                ->post($endpoint, [
                    'agent_did' => $agentDid,
                    'type'      => $type,
                    'data'      => $data,
                    'timestamp' => now()->toIso8601String(),
                    'signature' => $this->generateSignature($agentDid, $type, $data),
                ]);

            if ($response->successful()) {
                Log::info('Notification sent successfully', [
                    'agent_did' => $agentDid,
                    'type'      => $type,
                ]);

                return true;
            }

            // Store for retry if webhook failed
            $this->storeOfflineNotification($agentDid, $type, $data);

            return false;
        } catch (Exception $e) {
            Log::error('Failed to send notification', [
                'agent_did' => $agentDid,
                'type'      => $type,
                'error'     => $e->getMessage(),
            ]);

            // Store for later delivery
            $this->storeOfflineNotification($agentDid, $type, $data);

            return false;
        }
    }

    /**
     * Notify system administrators.
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @return bool
     */
    public function notifySystemAdmins(string $type, array $data): bool
    {
        $admins = $this->getSystemAdminDids();
        $success = false;

        foreach ($admins as $adminDid) {
            if ($this->notify($adminDid, $type, $data)) {
                $success = true;
            }
        }

        // Also log to system logs for audit
        Log::channel('admin')->info('System admin notification', [
            'type' => $type,
            'data' => $data,
        ]);

        return $success;
    }

    /**
     * Get agent's notification endpoint.
     */
    private function getAgentNotificationEndpoint(string $agentDid): ?string
    {
        try {
            // TODO: In the future, store notification endpoints in AgentIdentityAggregate
            // For now, return a mock endpoint for testing
            // In production, this would query a notification_endpoints table or
            // be stored as part of the agent's capabilities

            // Mock implementation for development
            if (str_contains($agentDid, 'finaegis')) {
                // Internal agents use internal webhook
                return config('services.agent_protocol.internal_webhook_url', 'http://localhost:8000/webhooks/agent-notifications');
            }

            // External agents would have registered endpoints
            // This would be retrieved from a database or service
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Store notification for offline delivery.
     */
    private function storeOfflineNotification(string $agentDid, string $type, array $data): void
    {
        try {
            DB::table('agent_offline_notifications')->insert([
                'agent_did'  => $agentDid,
                'type'       => $type,
                'data'       => json_encode($data),
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to store offline notification', [
                'agent_did' => $agentDid,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate signature for notification.
     */
    private function generateSignature(string $agentDid, string $type, array $data): string
    {
        $payload = $agentDid . '|' . $type . '|' . json_encode($data);

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * Get system administrator DIDs.
     */
    private function getSystemAdminDids(): array
    {
        return [
            'did:agent:finaegis:admin-1',
            'did:agent:finaegis:admin-2',
        ];
    }
}
