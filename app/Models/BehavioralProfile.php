<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehavioralProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'typical_transaction_times',
        'typical_transaction_days',
        'avg_transaction_amount',
        'median_transaction_amount',
        'max_transaction_amount',
        'transaction_amount_std_dev',
        'avg_daily_transaction_count',
        'avg_monthly_transaction_count',
        'common_locations',
        'location_history',
        'primary_country',
        'primary_city',
        'travels_frequently',
        'travel_patterns',
        'trusted_devices',
        'device_count',
        'uses_multiple_devices',
        'device_switching_pattern',
        'frequent_merchants',
        'frequent_recipients',
        'merchant_categories',
        'has_recurring_payments',
        'recurring_payment_patterns',
        'avg_session_duration',
        'typical_login_times',
        'avg_actions_per_session',
        'common_features_used',
        'profile_change_frequency',
        'password_change_frequency',
        'uses_2fa',
        'failed_login_attempts',
        'last_suspicious_activity',
        'max_daily_volume',
        'max_weekly_volume',
        'max_monthly_volume',
        'max_daily_transactions',
        'days_since_first_transaction',
        'total_transaction_count',
        'total_transaction_volume',
        'profile_established_at',
        'is_established',
        'ml_feature_vector',
        'ml_features_updated_at',
    ];

    protected $casts = [
        'typical_transaction_times' => 'array',
        'typical_transaction_days' => 'array',
        'common_locations' => 'array',
        'location_history' => 'array',
        'travel_patterns' => 'array',
        'trusted_devices' => 'array',
        'device_switching_pattern' => 'array',
        'frequent_merchants' => 'array',
        'frequent_recipients' => 'array',
        'merchant_categories' => 'array',
        'recurring_payment_patterns' => 'array',
        'typical_login_times' => 'array',
        'common_features_used' => 'array',
        'ml_feature_vector' => 'array',
        'avg_transaction_amount' => 'decimal:2',
        'median_transaction_amount' => 'decimal:2',
        'max_transaction_amount' => 'decimal:2',
        'transaction_amount_std_dev' => 'decimal:2',
        'avg_session_duration' => 'decimal:2',
        'max_daily_volume' => 'decimal:2',
        'max_weekly_volume' => 'decimal:2',
        'max_monthly_volume' => 'decimal:2',
        'total_transaction_volume' => 'decimal:2',
        'travels_frequently' => 'boolean',
        'uses_multiple_devices' => 'boolean',
        'has_recurring_payments' => 'boolean',
        'uses_2fa' => 'boolean',
        'is_established' => 'boolean',
        'last_suspicious_activity' => 'datetime',
        'profile_established_at' => 'datetime',
        'ml_features_updated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isEstablished(): bool
    {
        return $this->is_established && 
               $this->days_since_first_transaction >= 30 &&
               $this->total_transaction_count >= 10;
    }

    public function updateTransactionStats(array $transactions): void
    {
        if (empty($transactions)) {
            return;
        }

        $amounts = collect($transactions)->pluck('amount');
        $timestamps = collect($transactions)->pluck('created_at');

        $this->update([
            'avg_transaction_amount' => $amounts->average(),
            'median_transaction_amount' => $amounts->median(),
            'max_transaction_amount' => $amounts->max(),
            'transaction_amount_std_dev' => $amounts->count() > 1 ? $amounts->std() : 0,
            'typical_transaction_times' => $this->calculateTimeDistribution($timestamps),
            'typical_transaction_days' => $this->calculateDayDistribution($timestamps),
        ]);
    }

    protected function calculateTimeDistribution($timestamps): array
    {
        $distribution = array_fill(0, 24, 0);
        
        foreach ($timestamps as $timestamp) {
            $hour = $timestamp->hour;
            $distribution[$hour]++;
        }
        
        // Normalize to percentages
        $total = array_sum($distribution);
        if ($total > 0) {
            $distribution = array_map(fn($count) => round(($count / $total) * 100, 2), $distribution);
        }
        
        return $distribution;
    }

    protected function calculateDayDistribution($timestamps): array
    {
        $distribution = array_fill(0, 7, 0);
        
        foreach ($timestamps as $timestamp) {
            $day = $timestamp->dayOfWeek;
            $distribution[$day]++;
        }
        
        // Normalize to percentages
        $total = array_sum($distribution);
        if ($total > 0) {
            $distribution = array_map(fn($count) => round(($count / $total) * 100, 2), $distribution);
        }
        
        return $distribution;
    }

    public function isTransactionTimeUnusual(int $hour): bool
    {
        if (!$this->is_established || empty($this->typical_transaction_times)) {
            return false;
        }

        $hourPercentage = $this->typical_transaction_times[$hour] ?? 0;
        return $hourPercentage < 5; // Less than 5% of transactions happen at this hour
    }

    public function isTransactionAmountUnusual(float $amount): bool
    {
        if (!$this->is_established || !$this->avg_transaction_amount) {
            return false;
        }

        // Check if amount is more than 3 standard deviations from mean
        if ($this->transaction_amount_std_dev > 0) {
            $zScore = abs($amount - $this->avg_transaction_amount) / $this->transaction_amount_std_dev;
            return $zScore > 3;
        }

        // Fallback: check if amount is 5x larger than average
        return $amount > ($this->avg_transaction_amount * 5);
    }

    public function isLocationUnusual(string $country, string $city = null): bool
    {
        if (!$this->is_established) {
            return false;
        }

        // Check if country is in common locations
        $commonCountries = collect($this->common_locations)
            ->pluck('country')
            ->unique()
            ->toArray();

        if (!in_array($country, $commonCountries)) {
            return true;
        }

        // If city provided, check if it's unusual for this country
        if ($city && $this->primary_country === $country) {
            $commonCities = collect($this->common_locations)
                ->where('country', $country)
                ->pluck('city')
                ->unique()
                ->toArray();

            return !in_array($city, $commonCities);
        }

        return false;
    }

    public function isDeviceUnusual(string $deviceFingerprintId): bool
    {
        if (!$this->is_established) {
            return false;
        }

        return !in_array($deviceFingerprintId, $this->trusted_devices ?? []);
    }

    public function addTrustedDevice(string $deviceFingerprintId): void
    {
        $devices = $this->trusted_devices ?? [];
        if (!in_array($deviceFingerprintId, $devices)) {
            $devices[] = $deviceFingerprintId;
            $this->update([
                'trusted_devices' => $devices,
                'device_count' => count($devices),
                'uses_multiple_devices' => count($devices) > 1,
            ]);
        }
    }

    public function updateLocationHistory(string $country, string $city = null, string $ip = null): void
    {
        $history = $this->location_history ?? [];
        $history[] = [
            'country' => $country,
            'city' => $city,
            'ip' => $ip,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep last 100 locations
        $history = array_slice($history, -100);

        // Update common locations
        $this->updateCommonLocations($history);

        $this->update(['location_history' => $history]);
    }

    protected function updateCommonLocations(array $history): void
    {
        $locationCounts = [];
        
        foreach ($history as $location) {
            $key = $location['country'] . '|' . ($location['city'] ?? 'unknown');
            $locationCounts[$key] = ($locationCounts[$key] ?? 0) + 1;
        }
        
        // Get top 10 locations
        arsort($locationCounts);
        $commonLocations = [];
        
        foreach (array_slice($locationCounts, 0, 10, true) as $key => $count) {
            [$country, $city] = explode('|', $key);
            $commonLocations[] = [
                'country' => $country,
                'city' => $city === 'unknown' ? null : $city,
                'frequency' => $count,
            ];
        }
        
        $this->update(['common_locations' => $commonLocations]);
    }

    public function calculateBehaviorScore(array $currentBehavior): float
    {
        if (!$this->is_established) {
            return 50; // Neutral score for new profiles
        }

        $deviationScore = 0;
        $weights = [
            'time' => 0.15,
            'amount' => 0.25,
            'location' => 0.20,
            'device' => 0.20,
            'velocity' => 0.20,
        ];

        // Time deviation
        if (isset($currentBehavior['hour'])) {
            if ($this->isTransactionTimeUnusual($currentBehavior['hour'])) {
                $deviationScore += $weights['time'] * 100;
            }
        }

        // Amount deviation
        if (isset($currentBehavior['amount'])) {
            if ($this->isTransactionAmountUnusual($currentBehavior['amount'])) {
                $deviationScore += $weights['amount'] * 100;
            }
        }

        // Location deviation
        if (isset($currentBehavior['country'])) {
            if ($this->isLocationUnusual($currentBehavior['country'], $currentBehavior['city'] ?? null)) {
                $deviationScore += $weights['location'] * 100;
            }
        }

        // Device deviation
        if (isset($currentBehavior['device_id'])) {
            if ($this->isDeviceUnusual($currentBehavior['device_id'])) {
                $deviationScore += $weights['device'] * 100;
            }
        }

        // Velocity deviation
        if (isset($currentBehavior['daily_count']) && $this->max_daily_transactions) {
            if ($currentBehavior['daily_count'] > $this->max_daily_transactions * 2) {
                $deviationScore += $weights['velocity'] * 100;
            }
        }

        return min(100, $deviationScore);
    }

    public function generateMLFeatures(): array
    {
        $features = [
            // Transaction features
            'avg_transaction_amount' => $this->avg_transaction_amount ?? 0,
            'transaction_amount_std_dev' => $this->transaction_amount_std_dev ?? 0,
            'max_transaction_ratio' => $this->avg_transaction_amount > 0 ? 
                ($this->max_transaction_amount / $this->avg_transaction_amount) : 0,
            
            // Velocity features
            'avg_daily_transactions' => $this->avg_daily_transaction_count ?? 0,
            'avg_monthly_transactions' => $this->avg_monthly_transaction_count ?? 0,
            
            // Location features
            'location_diversity' => count($this->common_locations ?? []),
            'travels_frequently' => $this->travels_frequently ? 1 : 0,
            
            // Device features
            'device_count' => $this->device_count ?? 0,
            'uses_multiple_devices' => $this->uses_multiple_devices ? 1 : 0,
            
            // Security features
            'uses_2fa' => $this->uses_2fa ? 1 : 0,
            'failed_login_rate' => $this->total_transaction_count > 0 ?
                ($this->failed_login_attempts / $this->total_transaction_count) : 0,
            
            // Account age features
            'account_age_days' => $this->days_since_first_transaction ?? 0,
            'is_established' => $this->is_established ? 1 : 0,
            
            // Activity features
            'profile_change_frequency' => $this->profile_change_frequency ?? 0,
            'password_change_frequency' => $this->password_change_frequency ?? 0,
        ];

        $this->update([
            'ml_feature_vector' => $features,
            'ml_features_updated_at' => now(),
        ]);

        return $features;
    }

    public function getProfileSummary(): array
    {
        return [
            'established' => $this->is_established,
            'account_age_days' => $this->days_since_first_transaction,
            'total_transactions' => $this->total_transaction_count,
            'typical_amount_range' => [
                'min' => max(0, $this->avg_transaction_amount - (2 * $this->transaction_amount_std_dev)),
                'max' => $this->avg_transaction_amount + (2 * $this->transaction_amount_std_dev),
            ],
            'primary_location' => $this->primary_city ? 
                "{$this->primary_city}, {$this->primary_country}" : $this->primary_country,
            'device_count' => $this->device_count,
            'security' => [
                '2fa_enabled' => $this->uses_2fa,
                'recent_suspicious_activity' => $this->last_suspicious_activity?->diffForHumans(),
            ],
        ];
    }
}