<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class DeviceFingerprint extends Model
{
    use HasUuids;

    protected $fillable = [
        'fingerprint_hash',
        'user_id',
        'device_type',
        'operating_system',
        'os_version',
        'browser',
        'browser_version',
        'user_agent',
        'screen_resolution',
        'screen_color_depth',
        'timezone',
        'language',
        'installed_plugins',
        'installed_fonts',
        'canvas_fingerprint',
        'webgl_fingerprint',
        'audio_fingerprint',
        'ip_address',
        'ip_country',
        'ip_region',
        'ip_city',
        'isp',
        'is_vpn',
        'is_proxy',
        'is_tor',
        'typing_patterns',
        'mouse_patterns',
        'touch_patterns',
        'trust_score',
        'usage_count',
        'is_trusted',
        'is_blocked',
        'block_reason',
        'first_seen_at',
        'last_seen_at',
        'successful_logins',
        'failed_logins',
        'suspicious_activities',
        'associated_users',
        'associated_accounts',
    ];

    protected $casts = [
        'installed_plugins' => 'array',
        'installed_fonts' => 'array',
        'typing_patterns' => 'array',
        'mouse_patterns' => 'array',
        'touch_patterns' => 'array',
        'associated_users' => 'array',
        'associated_accounts' => 'array',
        'is_vpn' => 'boolean',
        'is_proxy' => 'boolean',
        'is_tor' => 'boolean',
        'is_trusted' => 'boolean',
        'is_blocked' => 'boolean',
        'screen_color_depth' => 'integer',
        'trust_score' => 'integer',
        'usage_count' => 'integer',
        'successful_logins' => 'integer',
        'failed_logins' => 'integer',
        'suspicious_activities' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    const DEVICE_TYPE_DESKTOP = 'desktop';
    const DEVICE_TYPE_MOBILE = 'mobile';
    const DEVICE_TYPE_TABLET = 'tablet';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public static function generateFingerprint(array $deviceData): string
    {
        $fingerprintData = [
            'user_agent' => $deviceData['user_agent'] ?? '',
            'screen_resolution' => $deviceData['screen_resolution'] ?? '',
            'timezone' => $deviceData['timezone'] ?? '',
            'language' => $deviceData['language'] ?? '',
            'canvas_fingerprint' => $deviceData['canvas_fingerprint'] ?? '',
            'webgl_fingerprint' => $deviceData['webgl_fingerprint'] ?? '',
            'audio_fingerprint' => $deviceData['audio_fingerprint'] ?? '',
            'plugins' => implode(',', $deviceData['installed_plugins'] ?? []),
            'fonts' => implode(',', array_slice($deviceData['installed_fonts'] ?? [], 0, 10)), // Top 10 fonts
        ];

        $fingerprintString = implode('|', array_values($fingerprintData));
        return hash('sha256', $fingerprintString);
    }

    public function isTrusted(): bool
    {
        return $this->is_trusted && !$this->is_blocked;
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    public function isSuspicious(): bool
    {
        return $this->is_vpn || $this->is_proxy || $this->is_tor || 
               $this->trust_score < 30 || $this->suspicious_activities > 5;
    }

    public function isNew(): bool
    {
        return $this->usage_count <= 1 || $this->first_seen_at->diffInHours(now()) < 24;
    }

    public function isStale(): bool
    {
        return $this->last_seen_at->diffInDays(now()) > 90;
    }

    public function recordUsage(bool $successful = true): void
    {
        $this->increment('usage_count');
        $this->update(['last_seen_at' => now()]);

        if ($successful) {
            $this->increment('successful_logins');
        } else {
            $this->increment('failed_logins');
            $this->decrementTrustScore(5);
        }
    }

    public function recordSuspiciousActivity(string $activity = null): void
    {
        $this->increment('suspicious_activities');
        $this->decrementTrustScore(10);

        if ($this->suspicious_activities >= 10) {
            $this->block('Too many suspicious activities');
        }
    }

    public function updateTrustScore(int $change): void
    {
        $newScore = max(0, min(100, $this->trust_score + $change));
        $this->update(['trust_score' => $newScore]);

        // Auto-trust if score is high enough and device is not new
        if ($newScore >= 80 && !$this->isNew() && !$this->is_blocked) {
            $this->update(['is_trusted' => true]);
        }

        // Auto-block if score is too low
        if ($newScore < 10) {
            $this->block('Trust score too low');
        }
    }

    public function incrementTrustScore(int $amount = 1): void
    {
        $this->updateTrustScore($amount);
    }

    public function decrementTrustScore(int $amount = 1): void
    {
        $this->updateTrustScore(-$amount);
    }

    public function block(string $reason): void
    {
        $this->update([
            'is_blocked' => true,
            'is_trusted' => false,
            'block_reason' => $reason,
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'is_blocked' => false,
            'block_reason' => null,
        ]);
    }

    public function trust(): void
    {
        if (!$this->is_blocked) {
            $this->update([
                'is_trusted' => true,
                'trust_score' => max($this->trust_score, 80),
            ]);
        }
    }

    public function associateUser(User $user): void
    {
        $users = $this->associated_users ?? [];
        if (!in_array($user->id, $users)) {
            $users[] = $user->id;
            $this->update(['associated_users' => $users]);
        }
    }

    public function associateAccount(string $accountId): void
    {
        $accounts = $this->associated_accounts ?? [];
        if (!in_array($accountId, $accounts)) {
            $accounts[] = $accountId;
            $this->update(['associated_accounts' => $accounts]);
        }
    }

    public function updateBehavioralBiometrics(array $biometrics): void
    {
        $updates = [];

        if (isset($biometrics['typing_patterns'])) {
            $updates['typing_patterns'] = $this->mergePatterns(
                $this->typing_patterns ?? [],
                $biometrics['typing_patterns']
            );
        }

        if (isset($biometrics['mouse_patterns'])) {
            $updates['mouse_patterns'] = $this->mergePatterns(
                $this->mouse_patterns ?? [],
                $biometrics['mouse_patterns']
            );
        }

        if (isset($biometrics['touch_patterns'])) {
            $updates['touch_patterns'] = $this->mergePatterns(
                $this->touch_patterns ?? [],
                $biometrics['touch_patterns']
            );
        }

        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    protected function mergePatterns(array $existing, array $new): array
    {
        // Keep last 100 patterns for analysis
        $merged = array_merge($existing, $new);
        return array_slice($merged, -100);
    }

    public function getDeviceRiskScore(): int
    {
        $riskScore = 0;

        // Network risks
        if ($this->is_vpn) $riskScore += 20;
        if ($this->is_proxy) $riskScore += 20;
        if ($this->is_tor) $riskScore += 30;

        // Trust factors
        if ($this->is_blocked) $riskScore += 50;
        if ($this->suspicious_activities > 0) {
            $riskScore += min(30, $this->suspicious_activities * 5);
        }

        // Failed login attempts
        if ($this->failed_logins > 5) {
            $riskScore += min(20, $this->failed_logins * 2);
        }

        // New device
        if ($this->isNew()) {
            $riskScore += 15;
        }

        // Multiple users on same device
        if (count($this->associated_users ?? []) > 3) {
            $riskScore += 15;
        }

        // Trust score inverse
        $riskScore += (100 - $this->trust_score) / 2;

        return min(100, $riskScore);
    }

    public function getDeviceProfile(): array
    {
        return [
            'fingerprint' => substr($this->fingerprint_hash, 0, 8) . '...',
            'device_type' => $this->device_type,
            'os' => $this->operating_system . ' ' . $this->os_version,
            'browser' => $this->browser . ' ' . $this->browser_version,
            'location' => implode(', ', array_filter([
                $this->ip_city,
                $this->ip_region,
                $this->ip_country,
            ])),
            'risk_indicators' => array_filter([
                $this->is_vpn ? 'VPN' : null,
                $this->is_proxy ? 'Proxy' : null,
                $this->is_tor ? 'Tor' : null,
                $this->isNew() ? 'New Device' : null,
                count($this->associated_users ?? []) > 3 ? 'Shared Device' : null,
            ]),
            'trust_score' => $this->trust_score,
            'risk_score' => $this->getDeviceRiskScore(),
            'last_seen' => $this->last_seen_at->diffForHumans(),
        ];
    }
}