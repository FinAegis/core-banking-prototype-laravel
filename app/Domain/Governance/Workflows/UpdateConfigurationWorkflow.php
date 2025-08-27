<?php

declare(strict_types=1);

namespace App\Domain\Governance\Workflows;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\ValueObjects\PollResult;
use Exception;
use Illuminate\Support\Facades\Cache;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class UpdateConfigurationWorkflow
{
    #[WorkflowMethod]
    public function execute(Poll $poll, PollResult $result): array
    {
        // Extract configuration changes from poll
        $configChanges = $this->extractConfigChangesFromPoll($poll, $result);

        if (empty($configChanges) || ! $this->validateConfigChanges($configChanges)) {
            return [
                'success'   => false,
                'message'   => 'Invalid configuration changes in poll',
                'poll_uuid' => $poll->uuid,
            ];
        }

        try {
            $appliedChanges = [];

            foreach ($configChanges as $configKey => $newValue) {
                if ($this->isConfigurationUpdateAllowed($configKey)) {
                    $oldValue = $this->getCurrentConfigValue($configKey);

                    $this->updateConfiguration($configKey, $newValue);

                    $appliedChanges[] = [
                        'key'       => $configKey,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                    ];
                } else {
                    logger()->warning(
                        'Governance poll attempted to update restricted configuration',
                        [
                            'poll_uuid'       => $poll->uuid,
                            'config_key'      => $configKey,
                            'attempted_value' => $newValue,
                        ]
                    );
                }
            }

            if (empty($appliedChanges)) {
                return [
                    'success'   => false,
                    'message'   => 'No configuration changes were allowed',
                    'poll_uuid' => $poll->uuid,
                ];
            }

            // Log the governance action
            logger()->info(
                'Configuration updated via governance poll',
                [
                    'poll_uuid'          => $poll->uuid,
                    'changes'            => $appliedChanges,
                    'winning_option'     => $result->winningOption,
                    'participation_rate' => $result->participationRate,
                ]
            );

            return [
                'success'         => true,
                'message'         => 'Configuration successfully updated via governance',
                'poll_uuid'       => $poll->uuid,
                'applied_changes' => $appliedChanges,
                'changes_count'   => count($appliedChanges),
            ];
        } catch (Exception $e) {
            logger()->error(
                'Failed to update configuration via governance poll',
                [
                    'poll_uuid'      => $poll->uuid,
                    'config_changes' => $configChanges,
                    'error'          => $e->getMessage(),
                ]
            );

            return [
                'success'   => false,
                'message'   => 'Failed to update configuration: ' . $e->getMessage(),
                'poll_uuid' => $poll->uuid,
            ];
        }
    }

    private function extractConfigChangesFromPoll(Poll $poll, PollResult $result): array
    {
        // Try to get config changes from poll metadata
        if (isset($poll->metadata['config_changes'])) {
            return $poll->metadata['config_changes'];
        }

        // Try to extract from winning option metadata
        $winningOption = null;
        foreach ($poll->options as $option) {
            if ($option['id'] === $result->winningOption) {
                $winningOption = $option;
                break;
            }
        }

        if ($winningOption && isset($winningOption['metadata']['config_changes'])) {
            return $winningOption['metadata']['config_changes'];
        }

        // Try to parse common configuration changes from text
        return $this->parseConfigChangesFromText($poll, $result);
    }

    private function parseConfigChangesFromText(Poll $poll, PollResult $result): array
    {
        $changes = [];
        $text = strtolower($poll->title . ' ' . ($poll->description ?? ''));

        // Parse common configuration patterns
        if (preg_match('/transaction.*limit.*?(\d+)/i', $text, $matches)) {
            $changes['transaction_limit'] = (int) $matches[1];
        }

        if (preg_match('/api.*rate.*limit.*?(\d+)/i', $text, $matches)) {
            $changes['api_rate_limit'] = (int) $matches[1];
        }

        if (preg_match('/session.*timeout.*?(\d+)/i', $text, $matches)) {
            $changes['session_timeout'] = (int) $matches[1];
        }

        if (preg_match('/minimum.*balance.*?(\d+)/i', $text, $matches)) {
            $changes['minimum_balance'] = (int) $matches[1] * 100; // Convert to cents
        }

        if (preg_match('/maintenance.*window.*?(\d{1,2}):(\d{2})/i', $text, $matches)) {
            $changes['maintenance_window_start'] = sprintf('%02d:%02d', $matches[1], $matches[2]);
        }

        // For yes/no polls, toggle boolean configurations
        if ($poll->type->value === 'yes_no') {
            $enabled = $result->winningOption === 'yes';

            if (str_contains($text, 'two factor') || str_contains($text, '2fa')) {
                $changes['require_2fa'] = $enabled;
            }

            if (str_contains($text, 'email notification')) {
                $changes['email_notifications'] = $enabled;
            }

            if (str_contains($text, 'audit log')) {
                $changes['audit_logging'] = $enabled;
            }
        }

        return $changes;
    }

    private function validateConfigChanges(array $changes): bool
    {
        foreach ($changes as $key => $value) {
            // Validate key format
            if (! is_string($key) || empty($key)) {
                return false;
            }

            // Validate that we're not trying to set sensitive configurations
            $forbiddenKeys = [
                'app_key',
                'db_password',
                'redis_password',
                'mail_password',
                'jwt_secret',
                'stripe_secret',
            ];

            foreach ($forbiddenKeys as $forbidden) {
                if (str_contains(strtolower($key), $forbidden)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isConfigurationUpdateAllowed(string $configKey): bool
    {
        // Define which configurations can be updated via governance
        $allowedConfigurations = [
            'transaction_limit',
            'api_rate_limit',
            'session_timeout',
            'minimum_balance',
            'maintenance_window_start',
            'require_2fa',
            'email_notifications',
            'audit_logging',
            'max_accounts_per_user',
            'withdrawal_daily_limit',
            'transfer_fee_percentage',
            'inactive_account_threshold_days',
        ];

        return in_array($configKey, $allowedConfigurations, true);
    }

    private function getCurrentConfigValue(string $configKey): mixed
    {
        // Try to get from cache first
        $cacheKey = "config.{$configKey}";
        $cachedValue = Cache::get($cacheKey);

        if ($cachedValue !== null) {
            return $cachedValue;
        }

        // Get from Laravel config
        return config($configKey);
    }

    private function updateConfiguration(string $configKey, mixed $newValue): void
    {
        // Store in cache for immediate effect
        $cacheKey = "config.{$configKey}";
        Cache::put($cacheKey, $newValue, now()->addYear());

        // In a real implementation, this would also update the persistent configuration store
        // This could be database, configuration management system, or external service

        logger()->info(
            'Configuration updated via governance',
            [
                'config_key' => $configKey,
                'new_value'  => $newValue,
                'timestamp'  => now()->toISOString(),
            ]
        );
    }
}
