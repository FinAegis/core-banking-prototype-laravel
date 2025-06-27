<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsService
{
    protected array $settingsConfig = [
        'platform' => [
            'label' => 'Platform Settings',
            'settings' => [
                'platform_name' => [
                    'label' => 'Platform Name',
                    'type' => 'string',
                    'default' => 'FinAegis',
                    'validation' => 'required|string|max:100',
                    'description' => 'The name of the platform displayed throughout the application',
                ],
                'maintenance_mode' => [
                    'label' => 'Maintenance Mode',
                    'type' => 'boolean',
                    'default' => false,
                    'validation' => 'required|boolean',
                    'description' => 'Enable maintenance mode to prevent user access',
                ],
                'maintenance_message' => [
                    'label' => 'Maintenance Message',
                    'type' => 'string',
                    'default' => 'The platform is currently under maintenance. Please try again later.',
                    'validation' => 'required|string|max:500',
                    'description' => 'Message displayed to users during maintenance mode',
                ],
                'session_timeout' => [
                    'label' => 'Session Timeout (minutes)',
                    'type' => 'integer',
                    'default' => 60,
                    'validation' => 'required|integer|min:5|max:1440',
                    'description' => 'Number of minutes before user sessions expire',
                ],
                'require_2fa' => [
                    'label' => 'Require Two-Factor Authentication',
                    'type' => 'boolean',
                    'default' => false,
                    'validation' => 'required|boolean',
                    'description' => 'Require all users to enable two-factor authentication',
                ],
            ],
        ],
        'fees' => [
            'label' => 'Fee Management',
            'settings' => [
                'transaction_fee_percentage' => [
                    'label' => 'Transaction Fee (%)',
                    'type' => 'float',
                    'default' => 0.01,
                    'validation' => 'required|numeric|min:0|max:10',
                    'description' => 'Percentage fee charged on all transactions',
                ],
                'conversion_fee_percentage' => [
                    'label' => 'Currency Conversion Fee (%)',
                    'type' => 'float',
                    'default' => 0.01,
                    'validation' => 'required|numeric|min:0|max:10',
                    'description' => 'Fee charged on currency conversions',
                ],
                'withdrawal_fee_fixed' => [
                    'label' => 'Withdrawal Fee (Fixed)',
                    'type' => 'float',
                    'default' => 1.00,
                    'validation' => 'required|numeric|min:0|max:100',
                    'description' => 'Fixed fee charged on withdrawals',
                ],
                'platform_fee_percentage' => [
                    'label' => 'Platform Fee (%)',
                    'type' => 'float',
                    'default' => 0.1,
                    'validation' => 'required|numeric|min:0|max:10',
                    'description' => 'Platform fee charged to financial institutions',
                ],
            ],
        ],
        'limits' => [
            'label' => 'Limits and Thresholds',
            'settings' => [
                'transaction_limit_daily' => [
                    'label' => 'Daily Transaction Limit',
                    'type' => 'float',
                    'default' => 10000,
                    'validation' => 'required|numeric|min:100|max:1000000',
                    'description' => 'Maximum daily transaction amount per user',
                ],
                'withdrawal_limit_daily' => [
                    'label' => 'Daily Withdrawal Limit',
                    'type' => 'float',
                    'default' => 5000,
                    'validation' => 'required|numeric|min:100|max:500000',
                    'description' => 'Maximum daily withdrawal amount per user',
                ],
                'minimum_balance' => [
                    'label' => 'Minimum Account Balance',
                    'type' => 'float',
                    'default' => 0,
                    'validation' => 'required|numeric|min:0|max:1000',
                    'description' => 'Minimum balance required in user accounts',
                ],
                'max_accounts_per_user' => [
                    'label' => 'Maximum Accounts per User',
                    'type' => 'integer',
                    'default' => 10,
                    'validation' => 'required|integer|min:1|max:100',
                    'description' => 'Maximum number of accounts a user can create',
                ],
                'inactive_account_threshold_days' => [
                    'label' => 'Inactive Account Threshold (days)',
                    'type' => 'integer',
                    'default' => 365,
                    'validation' => 'required|integer|min:30|max:730',
                    'description' => 'Days before an account is marked as inactive',
                ],
            ],
        ],
        'api' => [
            'label' => 'API Configuration',
            'settings' => [
                'api_rate_limit' => [
                    'label' => 'API Rate Limit (requests/minute)',
                    'type' => 'integer',
                    'default' => 60,
                    'validation' => 'required|integer|min:1|max:1000',
                    'description' => 'Maximum API requests per minute per user',
                ],
                'api_burst_limit' => [
                    'label' => 'API Burst Limit',
                    'type' => 'integer',
                    'default' => 100,
                    'validation' => 'required|integer|min:1|max:2000',
                    'description' => 'Maximum burst API requests allowed',
                ],
                'webhook_timeout' => [
                    'label' => 'Webhook Timeout (seconds)',
                    'type' => 'integer',
                    'default' => 30,
                    'validation' => 'required|integer|min:5|max:300',
                    'description' => 'Timeout for webhook delivery attempts',
                ],
                'webhook_retry_attempts' => [
                    'label' => 'Webhook Retry Attempts',
                    'type' => 'integer',
                    'default' => 3,
                    'validation' => 'required|integer|min:0|max:10',
                    'description' => 'Number of retry attempts for failed webhooks',
                ],
            ],
        ],
        'notifications' => [
            'label' => 'Notification Settings',
            'settings' => [
                'email_notifications' => [
                    'label' => 'Enable Email Notifications',
                    'type' => 'boolean',
                    'default' => true,
                    'validation' => 'required|boolean',
                    'description' => 'Enable email notifications for important events',
                ],
                'sms_notifications' => [
                    'label' => 'Enable SMS Notifications',
                    'type' => 'boolean',
                    'default' => false,
                    'validation' => 'required|boolean',
                    'description' => 'Enable SMS notifications for critical events',
                ],
                'push_notifications' => [
                    'label' => 'Enable Push Notifications',
                    'type' => 'boolean',
                    'default' => true,
                    'validation' => 'required|boolean',
                    'description' => 'Enable push notifications for mobile apps',
                ],
            ],
        ],
        'security' => [
            'label' => 'Security Settings',
            'settings' => [
                'audit_logging' => [
                    'label' => 'Enable Audit Logging',
                    'type' => 'boolean',
                    'default' => true,
                    'validation' => 'required|boolean',
                    'description' => 'Enable comprehensive audit logging for all actions',
                ],
                'password_min_length' => [
                    'label' => 'Minimum Password Length',
                    'type' => 'integer',
                    'default' => 8,
                    'validation' => 'required|integer|min:6|max:32',
                    'description' => 'Minimum length for user passwords',
                ],
                'password_expiry_days' => [
                    'label' => 'Password Expiry (days)',
                    'type' => 'integer',
                    'default' => 90,
                    'validation' => 'required|integer|min:0|max:365',
                    'description' => 'Days before passwords expire (0 = never)',
                ],
                'max_login_attempts' => [
                    'label' => 'Maximum Login Attempts',
                    'type' => 'integer',
                    'default' => 5,
                    'validation' => 'required|integer|min:3|max:10',
                    'description' => 'Maximum failed login attempts before lockout',
                ],
                'lockout_duration_minutes' => [
                    'label' => 'Lockout Duration (minutes)',
                    'type' => 'integer',
                    'default' => 30,
                    'validation' => 'required|integer|min:5|max:1440',
                    'description' => 'Duration of account lockout after failed attempts',
                ],
            ],
        ],
    ];

    public function getConfig(): array
    {
        return $this->settingsConfig;
    }

    public function getGroups(): array
    {
        return array_keys($this->settingsConfig);
    }

    public function getGroupConfig(string $group): array
    {
        return $this->settingsConfig[$group] ?? [];
    }

    public function getSettingConfig(string $key): array
    {
        foreach ($this->settingsConfig as $group => $groupConfig) {
            if (isset($groupConfig['settings'][$key])) {
                return array_merge(
                    $groupConfig['settings'][$key],
                    ['group' => $group]
                );
            }
        }
        
        return [];
    }

    public function initializeSettings(): void
    {
        foreach ($this->settingsConfig as $group => $groupConfig) {
            foreach ($groupConfig['settings'] as $key => $config) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'value' => $config['default'],
                        'type' => $config['type'],
                        'label' => $config['label'],
                        'description' => $config['description'],
                        'validation_rules' => explode('|', $config['validation']),
                        'is_public' => false,
                        'is_encrypted' => in_array($key, ['api_key', 'secret_key']),
                    ]
                );
            }
        }
    }

    public function validateSetting(string $key, $value): array
    {
        $config = $this->getSettingConfig($key);
        
        if (empty($config)) {
            return ['valid' => false, 'errors' => ['Setting configuration not found']];
        }
        
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $config['validation']]
        );
        
        if ($validator->fails()) {
            return ['valid' => false, 'errors' => $validator->errors()->all()];
        }
        
        return ['valid' => true, 'errors' => []];
    }

    public function updateSetting(string $key, $value, ?string $updatedBy = null): bool
    {
        $validation = $this->validateSetting($key, $value);
        
        if (!$validation['valid']) {
            Log::warning('Invalid setting update attempt', [
                'key' => $key,
                'errors' => $validation['errors'],
                'updated_by' => $updatedBy,
            ]);
            return false;
        }
        
        $config = $this->getSettingConfig($key);
        $oldValue = Setting::get($key);
        
        Setting::set($key, $value, [
            'type' => $config['type'],
            'label' => $config['label'],
            'description' => $config['description'],
            'group' => $config['group'],
        ]);
        
        Log::info('Setting updated', [
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $value,
            'updated_by' => $updatedBy,
        ]);
        
        return true;
    }

    public function exportSettings(): array
    {
        return Setting::all()
            ->reject(fn ($setting) => $setting->is_encrypted)
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->toArray();
    }

    public function importSettings(array $settings, ?string $importedBy = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];
        
        foreach ($settings as $key => $value) {
            if ($this->updateSetting($key, $value, $importedBy)) {
                $results['success'][] = $key;
            } else {
                $results['failed'][] = $key;
            }
        }
        
        Log::info('Settings imported', [
            'imported_by' => $importedBy,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
        ]);
        
        return $results;
    }
}