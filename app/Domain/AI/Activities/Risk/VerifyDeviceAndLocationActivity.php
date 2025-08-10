<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use App\Models\User;
use Workflow\Activity;

/**
 * Verify Device and Location Activity.
 *
 * Verifies device fingerprint and location for fraud detection.
 */
class VerifyDeviceAndLocationActivity extends Activity
{
    /**
     * Execute device and location verification.
     *
     * @param array{user_id: string, request: ?array} $input
     *
     * @return array{device_trusted: bool, location_verified: bool, details: array}
     */
    public function execute(array $input): array
    {
        $userId = $input['user_id'] ?? '';
        $request = $input['request'] ?? [];

        $user = User::find($userId);
        if (! $user) {
            return [
                'device_trusted'    => false,
                'location_verified' => false,
                'details'           => ['error' => 'User not found'],
            ];
        }

        $deviceInfo = $this->extractDeviceInfo($request);
        $locationInfo = $this->extractLocationInfo($request);

        $deviceTrusted = $this->verifyDevice($user, $deviceInfo);
        $locationVerified = $this->verifyLocation($user, $locationInfo);

        return [
            'device_trusted'    => $deviceTrusted,
            'location_verified' => $locationVerified,
            'details'           => [
                'device'   => $deviceInfo,
                'location' => $locationInfo,
            ],
        ];
    }

    private function extractDeviceInfo(array $request): array
    {
        return [
            'user_agent'  => $request['user_agent'] ?? 'Unknown',
            'ip_address'  => $request['ip_address'] ?? '0.0.0.0',
            'fingerprint' => $request['fingerprint'] ?? null,
        ];
    }

    private function extractLocationInfo(array $request): array
    {
        $ipAddress = $request['ip_address'] ?? '0.0.0.0';

        // In production, use GeoIP service
        return [
            'ip'      => $ipAddress,
            'country' => $this->getCountryFromIp($ipAddress),
            'city'    => 'Unknown',
            'is_vpn'  => $this->isVpn($ipAddress),
        ];
    }

    private function verifyDevice(User $user, array $deviceInfo): bool
    {
        // Check if device fingerprint matches known devices
        if (! $deviceInfo['fingerprint']) {
            return false; // No fingerprint available
        }

        // In production, check against stored trusted devices
        // For now, trust devices from private IP ranges
        $privateIpRanges = [
            '10.',
            '172.',
            '192.168.',
            '127.',
        ];

        foreach ($privateIpRanges as $range) {
            if (str_starts_with($deviceInfo['ip_address'], $range)) {
                return true;
            }
        }

        return false;
    }

    private function verifyLocation(User $user, array $locationInfo): bool
    {
        // Reject if VPN detected
        if ($locationInfo['is_vpn']) {
            return false;
        }

        // In production, check against user's typical locations
        // For now, approve known countries
        $trustedCountries = ['US', 'GB', 'CA', 'AU', 'Unknown'];

        return in_array($locationInfo['country'], $trustedCountries);
    }

    private function getCountryFromIp(string $ipAddress): string
    {
        // In production, use GeoIP database
        // Simple mock based on IP patterns
        if (str_starts_with($ipAddress, '8.8.')) {
            return 'US';
        }

        return 'Unknown';
    }

    private function isVpn(string $ipAddress): bool
    {
        // In production, check against VPN provider databases
        // Simple check for known VPN providers
        $vpnRanges = [
            '104.200.', // Example VPN range
            '45.32.',   // Example VPN range
        ];

        foreach ($vpnRanges as $range) {
            if (str_starts_with($ipAddress, $range)) {
                return true;
            }
        }

        return false;
    }
}
