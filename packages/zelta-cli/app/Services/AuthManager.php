<?php

declare(strict_types=1);

namespace ZeltaCli\Services;

/**
 * Manages CLI authentication credentials stored in ~/.zelta/credentials.json.
 *
 * Supports multiple profiles for switching between environments.
 */
class AuthManager
{
    private string $credentialsPath;

    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    public function __construct(?string $credentialsPath = null)
    {
        $this->credentialsPath = $credentialsPath ?? ($_SERVER['HOME'] . '/.zelta/credentials.json');
    }

    /**
     * Store an API key for the given profile.
     */
    public function login(string $apiKey, string $profile = 'default', string $baseUrl = 'https://api.zelta.app'): void
    {
        $creds = $this->loadCredentials();
        $creds['profiles'][$profile] = [
            'api_key'  => $apiKey,
            'base_url' => $baseUrl,
            'created'  => date('c'),
        ];
        $creds['active_profile'] = $profile;
        $this->saveCredentials($creds);
    }

    /**
     * Remove credentials for the given profile.
     */
    public function logout(string $profile = 'default'): void
    {
        $creds = $this->loadCredentials();
        unset($creds['profiles'][$profile]);
        if (($creds['active_profile'] ?? '') === $profile) {
            $creds['active_profile'] = array_key_first($creds['profiles'] ?? []) ?? 'default';
        }
        $this->saveCredentials($creds);
    }

    /**
     * Get the API key for the active (or specified) profile.
     */
    public function getApiKey(?string $profile = null): ?string
    {
        $creds = $this->loadCredentials();
        $profile ??= $creds['active_profile'] ?? 'default';

        return $creds['profiles'][$profile]['api_key'] ?? null;
    }

    /**
     * Get the base URL for the active (or specified) profile.
     */
    public function getBaseUrl(?string $profile = null): string
    {
        $creds = $this->loadCredentials();
        $profile ??= $creds['active_profile'] ?? 'default';

        return $creds['profiles'][$profile]['base_url'] ?? 'https://api.zelta.app';
    }

    /**
     * Get the active profile name.
     */
    public function getActiveProfile(): string
    {
        $creds = $this->loadCredentials();

        return $creds['active_profile'] ?? 'default';
    }

    /**
     * List all stored profiles.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listProfiles(): array
    {
        $creds = $this->loadCredentials();

        return $creds['profiles'] ?? [];
    }

    /**
     * Check if the user is authenticated.
     */
    public function isAuthenticated(?string $profile = null): bool
    {
        return $this->getApiKey($profile) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        if (! file_exists($this->credentialsPath)) {
            return $this->credentials = ['profiles' => [], 'active_profile' => 'default'];
        }

        $contents = file_get_contents($this->credentialsPath);
        $decoded = json_decode($contents ?: '{}', true);

        return $this->credentials = is_array($decoded) ? $decoded : ['profiles' => [], 'active_profile' => 'default'];
    }

    /**
     * @param array<string, mixed> $creds
     */
    private function saveCredentials(array $creds): void
    {
        $dir = dirname($this->credentialsPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents(
            $this->credentialsPath,
            json_encode($creds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
        chmod($this->credentialsPath, 0600);

        $this->credentials = $creds;
    }
}
