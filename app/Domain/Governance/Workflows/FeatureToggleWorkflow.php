<?php

declare(strict_types=1);

namespace App\Domain\Governance\Workflows;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\ValueObjects\PollResult;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class FeatureToggleWorkflow
{
    #[WorkflowMethod]
    public function execute(Poll $poll, PollResult $result): array
    {
        // Extract feature configuration from poll
        $featureConfig = $this->extractFeatureConfigFromPoll($poll, $result);

        if (! $this->validateFeatureConfig($featureConfig)) {
            return [
                'success'   => false,
                'message'   => 'Invalid feature configuration in poll',
                'poll_uuid' => $poll->uuid,
            ];
        }

        try {
            // Apply feature toggle based on poll result
            $enabled = $this->shouldEnableFeature($poll, $result);

            $this->updateFeatureFlag($featureConfig['feature_key'], $enabled);

            // Log the governance action
            logger()->info(
                'Feature toggled via governance poll',
                [
                    'poll_uuid'          => $poll->uuid,
                    'feature_key'        => $featureConfig['feature_key'],
                    'enabled'            => $enabled,
                    'winning_option'     => $result->winningOption,
                    'participation_rate' => $result->participationRate,
                ]
            );

            return [
                'success'     => true,
                'message'     => "Feature '{$featureConfig['feature_key']}' " . ($enabled ? 'enabled' : 'disabled') . ' via governance',
                'poll_uuid'   => $poll->uuid,
                'feature_key' => $featureConfig['feature_key'],
                'enabled'     => $enabled,
            ];
        } catch (Exception $e) {
            logger()->error(
                'Failed to toggle feature via governance poll',
                [
                    'poll_uuid'      => $poll->uuid,
                    'feature_config' => $featureConfig,
                    'error'          => $e->getMessage(),
                ]
            );

            return [
                'success'   => false,
                'message'   => 'Failed to toggle feature: ' . $e->getMessage(),
                'poll_uuid' => $poll->uuid,
            ];
        }
    }

    private function extractFeatureConfigFromPoll(Poll $poll, PollResult $result): array
    {
        // Try to get feature config from poll metadata
        if (isset($poll->metadata['feature_config'])) {
            return $poll->metadata['feature_config'];
        }

        // Try to extract from winning option metadata
        $winningOption = null;
        foreach ($poll->options as $option) {
            if ($option['id'] === $result->winningOption) {
                $winningOption = $option;
                break;
            }
        }

        if ($winningOption && isset($winningOption['metadata']['feature_config'])) {
            return $winningOption['metadata']['feature_config'];
        }

        // Fallback: try to parse from poll title/description
        return $this->parseFeatureConfigFromText($poll->title, $poll->description);
    }

    private function parseFeatureConfigFromText(string $title, ?string $description): array
    {
        $text = strtolower($title . ' ' . ($description ?? ''));

        // Common feature patterns
        $featurePatterns = [
            'multi.?currency'   => 'multi_currency_support',
            'mobile.?app'       => 'mobile_app_access',
            'api.?rate.?limit'  => 'api_rate_limiting',
            'two.?factor|2fa'   => 'two_factor_auth',
            'webhook'           => 'webhook_notifications',
            'real.?time'        => 'real_time_processing',
            'batch.?processing' => 'batch_processing',
            'audit.?log'        => 'audit_logging',
            'dark.?mode'        => 'dark_mode_ui',
            'maintenance.?mode' => 'maintenance_mode',
        ];

        foreach ($featurePatterns as $pattern => $featureKey) {
            if (preg_match("/$pattern/i", $text)) {
                return ['feature_key' => $featureKey];
            }
        }

        // Default fallback
        return ['feature_key' => 'unknown_feature'];
    }

    private function validateFeatureConfig(array $config): bool
    {
        return isset($config['feature_key']) &&
               is_string($config['feature_key']) &&
               ! empty($config['feature_key']) &&
               $config['feature_key'] !== 'unknown_feature';
    }

    private function shouldEnableFeature(Poll $poll, PollResult $result): bool
    {
        // For yes/no polls, check if "yes" won
        if ($poll->type->value === 'yes_no') {
            return $result->winningOption === 'yes';
        }

        // For other poll types, check winning option text
        $winningOption = null;
        foreach ($poll->options as $option) {
            if ($option['id'] === $result->winningOption) {
                $winningOption = $option;
                break;
            }
        }

        if (! $winningOption) {
            return false;
        }

        $label = strtolower($winningOption['label']);

        // Check for enabling keywords
        $enableKeywords = ['enable', 'activate', 'turn on', 'allow', 'permit', 'yes'];
        $disableKeywords = ['disable', 'deactivate', 'turn off', 'block', 'deny', 'no'];

        foreach ($enableKeywords as $keyword) {
            if (str_contains($label, $keyword)) {
                return true;
            }
        }

        foreach ($disableKeywords as $keyword) {
            if (str_contains($label, $keyword)) {
                return false;
            }
        }

        // Default to enabled if unclear
        return true;
    }

    private function updateFeatureFlag(string $featureKey, bool $enabled): void
    {
        // Store feature flag in cache for immediate effect
        $cacheKey = "feature_flags.{$featureKey}";
        Cache::put($cacheKey, $enabled, now()->addYear());

        // Also store in a persistent configuration store
        // This could be database, file, or external service
        $this->persistFeatureFlag($featureKey, $enabled);
    }

    private function persistFeatureFlag(string $featureKey, bool $enabled): void
    {
        // In a real implementation, this would persist to a configuration store
        // For now, we'll use Laravel's config with a runtime update

        $currentFlags = config('features', []);
        $currentFlags[$featureKey] = $enabled;

        Config::set('features', $currentFlags);

        // Log for audit purposes
        logger()->info(
            'Feature flag persisted',
            [
                'feature_key' => $featureKey,
                'enabled'     => $enabled,
                'timestamp'   => now()->toISOString(),
            ]
        );
    }
}
