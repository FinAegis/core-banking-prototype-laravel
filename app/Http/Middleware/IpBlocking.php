<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IpBlocking
{
    /**
     * Configuration for IP blocking.
     */
    private const CONFIG = [
        'max_failed_attempts'       => 10, // Maximum failed attempts before blocking
        'block_duration'            => 3600, // Block duration in seconds (1 hour)
        'permanent_block_threshold' => 50, // Attempts before permanent block
        'whitelist_key'             => 'ip_whitelist',
        'blacklist_key'             => 'ip_blacklist',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check if IP is whitelisted
        if ($this->isWhitelisted($ip)) {
            return $next($request);
        }

        // Check if IP is permanently blacklisted
        if ($this->isPermanentlyBlacklisted($ip)) {
            Log::warning('Permanently blocked IP attempted access', [
                'ip'         => $ip,
                'path'       => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->blockedResponse('Your IP address has been permanently blocked due to suspicious activity.');
        }

        // Check if IP is temporarily blocked
        if ($this->isTemporarilyBlocked($ip)) {
            $remainingTime = $this->getRemainingBlockTime($ip);

            Log::warning('Temporarily blocked IP attempted access', [
                'ip'             => $ip,
                'path'           => $request->path(),
                'remaining_time' => $remainingTime,
            ]);

            return $this->blockedResponse("Your IP address is temporarily blocked. Try again in {$remainingTime} minutes.");
        }

        // Process the request
        $response = $next($request);

        // Track failed authentication attempts
        if ($this->isFailedAuthResponse($response)) {
            $this->recordFailedAttempt($ip);
        }

        return $response;
    }

    /**
     * Check if IP is whitelisted.
     */
    private function isWhitelisted(string $ip): bool
    {
        $whitelist = Cache::get(self::CONFIG['whitelist_key'], []);

        // Add internal IPs to whitelist
        $internalIps = ['127.0.0.1', '::1'];
        $whitelist = array_merge($whitelist, $internalIps);

        // Check if IP or IP range is whitelisted
        foreach ($whitelist as $whitelistedIp) {
            if ($this->ipMatches($ip, $whitelistedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is permanently blacklisted.
     */
    private function isPermanentlyBlacklisted(string $ip): bool
    {
        $blacklist = Cache::get(self::CONFIG['blacklist_key'], []);

        foreach ($blacklist as $blacklistedIp) {
            if ($this->ipMatches($ip, $blacklistedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is temporarily blocked.
     */
    private function isTemporarilyBlocked(string $ip): bool
    {
        return Cache::has("ip_blocked:{$ip}");
    }

    /**
     * Get remaining block time in minutes.
     */
    private function getRemainingBlockTime(string $ip): int
    {
        $blockedUntil = Cache::get("ip_blocked:{$ip}");

        if ($blockedUntil instanceof \Carbon\Carbon) {
            return (int) max(0, ceil($blockedUntil->diffInMinutes(now())));
        }

        return 0;
    }

    /**
     * Record a failed authentication attempt.
     */
    private function recordFailedAttempt(string $ip): void
    {
        $key = "ip_failed_attempts:{$ip}";
        $attempts = Cache::get($key, 0) + 1;

        // Store attempts with 24-hour expiration
        Cache::put($key, $attempts, 86400);

        // Check if IP should be blocked
        if ($attempts >= self::CONFIG['permanent_block_threshold']) {
            $this->addToPermanentBlacklist($ip);

            Log::critical('IP permanently blacklisted due to excessive failed attempts', [
                'ip'       => $ip,
                'attempts' => $attempts,
            ]);
        } elseif ($attempts >= self::CONFIG['max_failed_attempts']) {
            $this->temporarilyBlock($ip);

            Log::warning('IP temporarily blocked due to failed attempts', [
                'ip'             => $ip,
                'attempts'       => $attempts,
                'block_duration' => self::CONFIG['block_duration'],
            ]);
        }
    }

    /**
     * Temporarily block an IP address.
     */
    private function temporarilyBlock(string $ip): void
    {
        $blockedUntil = now()->addSeconds(self::CONFIG['block_duration']);
        Cache::put("ip_blocked:{$ip}", $blockedUntil, self::CONFIG['block_duration']);
    }

    /**
     * Add IP to permanent blacklist.
     */
    private function addToPermanentBlacklist(string $ip): void
    {
        $blacklist = Cache::get(self::CONFIG['blacklist_key'], []);

        if (! in_array($ip, $blacklist)) {
            $blacklist[] = $ip;
            Cache::forever(self::CONFIG['blacklist_key'], $blacklist);
        }
    }

    /**
     * Check if response indicates failed authentication.
     */
    private function isFailedAuthResponse(Response $response): bool
    {
        // Check for authentication failure status codes
        if (in_array($response->getStatusCode(), [401, 403, 422])) {
            // Check if it's an auth-related endpoint
            $request = request();
            $authEndpoints = ['login', 'auth', 'password', 'register'];

            foreach ($authEndpoints as $endpoint) {
                if (str_contains($request->path(), $endpoint)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if IP matches a pattern (supports CIDR notation).
     */
    private function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation support
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);
            $subnet &= $mask;

            return ($ip & $mask) == $subnet;
        }

        return false;
    }

    /**
     * Create blocked response.
     */
    private function blockedResponse(string $message): Response
    {
        return response()->json([
            'error'   => 'Access Denied',
            'message' => $message,
        ], 403);
    }

    /**
     * Add IP to whitelist.
     */
    public static function whitelist(string $ip): void
    {
        $whitelist = Cache::get(self::CONFIG['whitelist_key'], []);

        if (! in_array($ip, $whitelist)) {
            $whitelist[] = $ip;
            Cache::forever(self::CONFIG['whitelist_key'], $whitelist);
        }
    }

    /**
     * Remove IP from blacklist.
     */
    public static function unblock(string $ip): void
    {
        // Remove from permanent blacklist
        $blacklist = Cache::get(self::CONFIG['blacklist_key'], []);
        $blacklist = array_diff($blacklist, [$ip]);
        Cache::forever(self::CONFIG['blacklist_key'], $blacklist);

        // Remove temporary block
        Cache::forget("ip_blocked:{$ip}");
        Cache::forget("ip_failed_attempts:{$ip}");
    }
}
