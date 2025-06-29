<?php

namespace App\Domain\Fraud\Services;

use App\Models\DeviceFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceFingerprintService
{
    /**
     * Process and store device fingerprint
     */
    public function processFingerprint(array $deviceData, ?User $user = null): DeviceFingerprint
    {
        $fingerprintHash = DeviceFingerprint::generateFingerprint($deviceData);
        
        $fingerprint = DeviceFingerprint::firstOrNew(
            ['fingerprint_hash' => $fingerprintHash],
            $this->prepareDeviceData($deviceData)
        );
        
        if ($fingerprint->exists) {
            // Update existing fingerprint
            $fingerprint->recordUsage(true);
            
            // Associate with user if provided
            if ($user) {
                $fingerprint->associateUser($user);
            }
        } else {
            // New device
            $fingerprint->fill([
                'user_id' => $user?->id,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
            $fingerprint->save();
        }
        
        // Enrich with IP data
        if (isset($deviceData['ip_address'])) {
            $this->enrichWithIpData($fingerprint, $deviceData['ip_address']);
        }
        
        return $fingerprint;
    }
    
    /**
     * Analyze device for fraud risk
     */
    public function analyzeDevice(array $deviceData): array
    {
        if (empty($deviceData['fingerprint_id'])) {
            return [
                'risk_score' => 50,
                'risk_factors' => ['no_device_fingerprint'],
                'recommendation' => 'require_device_verification',
            ];
        }
        
        $fingerprint = DeviceFingerprint::find($deviceData['fingerprint_id']);
        
        if (!$fingerprint) {
            return [
                'risk_score' => 60,
                'risk_factors' => ['unknown_device'],
                'recommendation' => 'monitor_closely',
            ];
        }
        
        $riskScore = $fingerprint->getDeviceRiskScore();
        $riskFactors = [];
        
        // Analyze risk factors
        if ($fingerprint->is_vpn) $riskFactors[] = 'vpn_detected';
        if ($fingerprint->is_proxy) $riskFactors[] = 'proxy_detected';
        if ($fingerprint->is_tor) $riskFactors[] = 'tor_detected';
        if ($fingerprint->isBlocked()) $riskFactors[] = 'blocked_device';
        if ($fingerprint->isNew()) $riskFactors[] = 'new_device';
        if ($fingerprint->isSuspicious()) $riskFactors[] = 'suspicious_device';
        if (count($fingerprint->associated_users ?? []) > 5) $riskFactors[] = 'shared_device';
        
        // Check for device spoofing
        $spoofingIndicators = $this->detectSpoofing($deviceData, $fingerprint);
        if (!empty($spoofingIndicators)) {
            $riskFactors = array_merge($riskFactors, $spoofingIndicators);
            $riskScore = min(100, $riskScore + 30);
        }
        
        // Check device consistency
        $inconsistencies = $this->checkDeviceConsistency($deviceData, $fingerprint);
        if (!empty($inconsistencies)) {
            $riskFactors = array_merge($riskFactors, $inconsistencies);
            $riskScore = min(100, $riskScore + 20);
        }
        
        return [
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'device_profile' => $fingerprint->getDeviceProfile(),
            'trust_score' => $fingerprint->trust_score,
            'is_trusted' => $fingerprint->isTrusted(),
            'recommendation' => $this->getRecommendation($riskScore, $riskFactors),
        ];
    }
    
    /**
     * Prepare device data for storage
     */
    protected function prepareDeviceData(array $deviceData): array
    {
        return [
            'device_type' => $this->detectDeviceType($deviceData),
            'operating_system' => $deviceData['os'] ?? null,
            'os_version' => $deviceData['os_version'] ?? null,
            'browser' => $deviceData['browser'] ?? null,
            'browser_version' => $deviceData['browser_version'] ?? null,
            'user_agent' => $deviceData['user_agent'] ?? '',
            'screen_resolution' => $deviceData['screen_resolution'] ?? null,
            'screen_color_depth' => $deviceData['color_depth'] ?? null,
            'timezone' => $deviceData['timezone'] ?? null,
            'language' => $deviceData['language'] ?? null,
            'installed_plugins' => $deviceData['plugins'] ?? [],
            'installed_fonts' => array_slice($deviceData['fonts'] ?? [], 0, 50), // Limit fonts
            'canvas_fingerprint' => $deviceData['canvas_fingerprint'] ?? null,
            'webgl_fingerprint' => $deviceData['webgl_fingerprint'] ?? null,
            'audio_fingerprint' => $deviceData['audio_fingerprint'] ?? null,
            'ip_address' => $deviceData['ip_address'] ?? request()->ip(),
        ];
    }
    
    /**
     * Detect device type from user agent
     */
    protected function detectDeviceType(array $deviceData): string
    {
        $userAgent = strtolower($deviceData['user_agent'] ?? '');
        
        if (str_contains($userAgent, 'mobile')) {
            return DeviceFingerprint::DEVICE_TYPE_MOBILE;
        } elseif (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return DeviceFingerprint::DEVICE_TYPE_TABLET;
        } else {
            return DeviceFingerprint::DEVICE_TYPE_DESKTOP;
        }
    }
    
    /**
     * Enrich device data with IP information
     */
    protected function enrichWithIpData(DeviceFingerprint $fingerprint, string $ip): void
    {
        $ipData = $this->getIpData($ip);
        
        if ($ipData) {
            $fingerprint->update([
                'ip_country' => $ipData['country'] ?? null,
                'ip_region' => $ipData['region'] ?? null,
                'ip_city' => $ipData['city'] ?? null,
                'isp' => $ipData['isp'] ?? null,
                'is_vpn' => $ipData['is_vpn'] ?? false,
                'is_proxy' => $ipData['is_proxy'] ?? false,
                'is_tor' => $ipData['is_tor'] ?? false,
            ]);
        }
    }
    
    /**
     * Get IP data from service
     */
    protected function getIpData(string $ip): ?array
    {
        return Cache::remember("ip_data_{$ip}", 86400, function () use ($ip) {
            try {
                // In production, use services like:
                // - IPQualityScore
                // - MaxMind
                // - IP2Proxy
                
                // Simulated response
                return [
                    'country' => 'US',
                    'region' => 'California',
                    'city' => 'San Francisco',
                    'isp' => 'Example ISP',
                    'is_vpn' => false,
                    'is_proxy' => false,
                    'is_tor' => false,
                    'risk_score' => 10,
                ];
            } catch (\Exception $e) {
                Log::error('IP data lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }
    
    /**
     * Detect device spoofing attempts
     */
    protected function detectSpoofing(array $currentData, DeviceFingerprint $storedFingerprint): array
    {
        $indicators = [];
        
        // Check for user agent mismatch
        if (isset($currentData['user_agent']) && 
            $currentData['user_agent'] !== $storedFingerprint->user_agent) {
            $indicators[] = 'user_agent_changed';
        }
        
        // Check for screen resolution mismatch
        if (isset($currentData['screen_resolution']) && 
            $currentData['screen_resolution'] !== $storedFingerprint->screen_resolution) {
            $indicators[] = 'screen_resolution_changed';
        }
        
        // Check for timezone mismatch
        if (isset($currentData['timezone']) && 
            $currentData['timezone'] !== $storedFingerprint->timezone) {
            $indicators[] = 'timezone_changed';
        }
        
        // Check for canvas fingerprint mismatch
        if (isset($currentData['canvas_fingerprint']) && 
            $storedFingerprint->canvas_fingerprint &&
            $currentData['canvas_fingerprint'] !== $storedFingerprint->canvas_fingerprint) {
            $indicators[] = 'canvas_fingerprint_mismatch';
        }
        
        // Multiple changes indicate possible spoofing
        if (count($indicators) >= 3) {
            $indicators[] = 'possible_device_spoofing';
        }
        
        return $indicators;
    }
    
    /**
     * Check device consistency
     */
    protected function checkDeviceConsistency(array $currentData, DeviceFingerprint $fingerprint): array
    {
        $inconsistencies = [];
        
        // Check if plugins match OS/browser
        if (isset($currentData['plugins']) && isset($currentData['os'])) {
            if ($currentData['os'] === 'iOS' && !empty($currentData['plugins'])) {
                $inconsistencies[] = 'ios_with_plugins'; // iOS doesn't support plugins
            }
        }
        
        // Check for headless browser indicators
        if ($this->detectHeadlessBrowser($currentData)) {
            $inconsistencies[] = 'headless_browser_detected';
        }
        
        // Check for automation tools
        if ($this->detectAutomationTools($currentData)) {
            $inconsistencies[] = 'automation_tools_detected';
        }
        
        return $inconsistencies;
    }
    
    /**
     * Detect headless browser
     */
    protected function detectHeadlessBrowser(array $deviceData): bool
    {
        $indicators = 0;
        
        // Check for missing features common in headless browsers
        if (empty($deviceData['plugins'])) $indicators++;
        if (empty($deviceData['languages'])) $indicators++;
        if (($deviceData['color_depth'] ?? 0) < 24) $indicators++;
        
        // Check user agent
        $userAgent = strtolower($deviceData['user_agent'] ?? '');
        if (str_contains($userAgent, 'headless') || 
            str_contains($userAgent, 'phantom') ||
            str_contains($userAgent, 'selenium')) {
            return true;
        }
        
        return $indicators >= 2;
    }
    
    /**
     * Detect automation tools
     */
    protected function detectAutomationTools(array $deviceData): bool
    {
        // Check for WebDriver
        if (isset($deviceData['webdriver']) && $deviceData['webdriver']) {
            return true;
        }
        
        // Check for specific automation properties
        $automationProperties = [
            'selenium',
            'webdriver',
            'phantomjs',
            'nightmare',
            'puppeteer',
        ];
        
        foreach ($automationProperties as $prop) {
            if (isset($deviceData[$prop])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get recommendation based on risk
     */
    protected function getRecommendation(int $riskScore, array $riskFactors): string
    {
        if ($riskScore >= 80 || in_array('blocked_device', $riskFactors)) {
            return 'block_transaction';
        } elseif ($riskScore >= 60) {
            return 'require_additional_verification';
        } elseif ($riskScore >= 40 || in_array('new_device', $riskFactors)) {
            return 'monitor_closely';
        } else {
            return 'proceed_normally';
        }
    }
    
    /**
     * Update device behavioral biometrics
     */
    public function updateBehavioralBiometrics(string $deviceId, array $biometrics): void
    {
        $device = DeviceFingerprint::find($deviceId);
        
        if (!$device) {
            return;
        }
        
        $device->updateBehavioralBiometrics($biometrics);
        
        // Analyze biometrics for anomalies
        $anomalies = $this->analyzeBiometricAnomalies($device, $biometrics);
        
        if (!empty($anomalies)) {
            $device->recordSuspiciousActivity('biometric_anomaly');
        }
    }
    
    /**
     * Analyze biometric anomalies
     */
    protected function analyzeBiometricAnomalies(DeviceFingerprint $device, array $newBiometrics): array
    {
        $anomalies = [];
        
        // Analyze typing patterns
        if (isset($newBiometrics['typing_patterns'])) {
            $typingAnomaly = $this->analyzeTypingPattern(
                $device->typing_patterns ?? [],
                $newBiometrics['typing_patterns']
            );
            
            if ($typingAnomaly) {
                $anomalies[] = 'typing_pattern_anomaly';
            }
        }
        
        // Analyze mouse patterns
        if (isset($newBiometrics['mouse_patterns'])) {
            $mouseAnomaly = $this->analyzeMousePattern(
                $device->mouse_patterns ?? [],
                $newBiometrics['mouse_patterns']
            );
            
            if ($mouseAnomaly) {
                $anomalies[] = 'mouse_pattern_anomaly';
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Analyze typing pattern for anomalies
     */
    protected function analyzeTypingPattern(array $historical, array $current): bool
    {
        if (empty($historical) || empty($current)) {
            return false;
        }
        
        // Calculate average keystroke intervals
        $historicalAvg = $this->calculateAverageInterval($historical);
        $currentAvg = $this->calculateAverageInterval($current);
        
        // Significant deviation indicates possible different user
        $deviation = abs($historicalAvg - $currentAvg) / $historicalAvg;
        
        return $deviation > 0.5; // 50% deviation threshold
    }
    
    /**
     * Analyze mouse pattern for anomalies
     */
    protected function analyzeMousePattern(array $historical, array $current): bool
    {
        if (empty($historical) || empty($current)) {
            return false;
        }
        
        // Analyze movement speed and acceleration patterns
        // In production, use more sophisticated analysis
        
        return false;
    }
    
    /**
     * Calculate average interval from pattern data
     */
    protected function calculateAverageInterval(array $patterns): float
    {
        if (empty($patterns)) {
            return 0;
        }
        
        $intervals = array_column($patterns, 'interval');
        return array_sum($intervals) / count($intervals);
    }
    
    /**
     * Trust device after successful verifications
     */
    public function trustDevice(string $deviceId, User $user): void
    {
        $device = DeviceFingerprint::find($deviceId);
        
        if (!$device || $device->isBlocked()) {
            return;
        }
        
        $device->trust();
        $device->associateUser($user);
        
        // Also add to user's behavioral profile
        $profile = $user->behavioralProfile;
        if ($profile) {
            $profile->addTrustedDevice($deviceId);
        }
    }
    
    /**
     * Get device trust network
     */
    public function getDeviceTrustNetwork(string $deviceId): array
    {
        $device = DeviceFingerprint::find($deviceId);
        
        if (!$device) {
            return [];
        }
        
        $network = [
            'device' => $device->getDeviceProfile(),
            'associated_users' => count($device->associated_users ?? []),
            'trust_score' => $device->trust_score,
            'related_devices' => [],
        ];
        
        // Find devices used by same users
        if (!empty($device->associated_users)) {
            $relatedDevices = DeviceFingerprint::where('id', '!=', $deviceId)
                ->where(function ($query) use ($device) {
                    foreach ($device->associated_users as $userId) {
                        $query->orWhereJsonContains('associated_users', $userId);
                    }
                })
                ->limit(10)
                ->get();
            
            foreach ($relatedDevices as $related) {
                $network['related_devices'][] = [
                    'device_id' => $related->id,
                    'trust_score' => $related->trust_score,
                    'is_trusted' => $related->isTrusted(),
                    'shared_users' => count(array_intersect(
                        $device->associated_users ?? [],
                        $related->associated_users ?? []
                    )),
                ];
            }
        }
        
        return $network;
    }
}