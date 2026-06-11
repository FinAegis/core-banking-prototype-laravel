<?php

declare(strict_types=1);

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

namespace App\Infrastructure\Domain;

use App\Infrastructure\Domain\DataObjects\DependencyNode;
use App\Infrastructure\Domain\DataObjects\DomainInfo;
use App\Infrastructure\Domain\DataObjects\InstallResult;
use App\Infrastructure\Domain\DataObjects\ModuleManifest;
use App\Infrastructure\Domain\DataObjects\RemoveResult;
use App\Infrastructure\Domain\DataObjects\VerificationResult;
use App\Infrastructure\Domain\Enums\DomainStatus;
use App\Infrastructure\Domain\Enums\DomainType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Manages domain installation, removal, and verification.
 *
 * Central service for the modular architecture that handles:
 * - Domain discovery and manifest parsing
 * - Installation with dependency resolution
 * - Safe removal with dependent checking
 * - Health verification
 */
class DomainManager
{
    private const CACHE_KEY = 'domain_manager_manifests';

    private const CACHE_TTL = 3600; // 1 hour

    private const INSTALLED_DOMAINS_KEY = 'domain_manager_installed';

    private const DISABLED_DOMAINS_KEY = 'domain_manager_disabled';

    /**
     * @var array<string, ModuleManifest>|null
     */
    private ?array $manifestCache = null;

    public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly string $domainBasePath = 'app/Domain',
    ) {
    }

    /**
     * Get all available domains with their info.
     *
     * @return Collection<int, DomainInfo>
     */
    public function getAvailableDomains(): Collection
    {
        $manifests = $this->loadAllManifests();
        $installed = $this->getInstalledDomains();

        return collect($manifests)->map(function (ModuleManifest $manifest) use ($installed, $manifests) {
            $domainId = $manifest->getDomainId();
            $fullName = $manifest->name;

            // Check disabled status first
            if ($this->isDisabled($fullName)) {
                $status = DomainStatus::DISABLED;
            } elseif (in_array($fullName, $installed, true)) {
                $status = DomainStatus::INSTALLED;
            } else {
                $status = DomainStatus::AVAILABLE;
            }

            // Check for missing dependencies
            if ($status === DomainStatus::AVAILABLE) {
                $this->dependencyResolver->setManifests($manifests);
                $missing = $this->dependencyResolver->getMissingDependencies($fullName, $installed);
                if (! empty($missing)) {
                    $status = DomainStatus::MISSING_DEPS;
                }
            }

            // Get dependents (other domains that require this one)
            $dependents = $this->getDependents($fullName, $manifests, $installed);

            return new DomainInfo(
                name: $fullName,
                displayName: ucfirst($domainId),
                description: $manifest->description,
                type: $manifest->type,
                status: $status,
                version: $manifest->version,
                dependencies: array_keys($manifest->requiredDependencies),
                dependents: $dependents,
            );
        })->values();
    }

    /**
     * Get currently installed domains.
     *
     * @return array<string>
     */
    public function getInstalledDomains(): array
    {
        // In this implementation, we consider all domains with manifests as installed
        // A real implementation would track installation state separately
        $installed = Cache::get(self::INSTALLED_DOMAINS_KEY);

        if ($installed !== null) {
            return $installed;
        }

        // Default: all core domains are installed, plus any with existing migrations
        $manifests = $this->loadAllManifests();
        $installed = [];

        foreach ($manifests as $name => $manifest) {
            if ($manifest->type === DomainType::CORE) {
                $installed[] = $name;
            } elseif ($this->hasRunMigrations($manifest)) {
                $installed[] = $name;
            }
        }

        Cache::put(self::INSTALLED_DOMAINS_KEY, $installed, self::CACHE_TTL);

        return $installed;
    }

    /**
     * Install a domain.
     */
    public function install(string $domain, bool $withDependencies = true): InstallResult
    {
        $normalizedName = $this->normalizeDomainName($domain);
        $manifests = $this->loadAllManifests();

        if (! isset($manifests[$normalizedName])) {
            return InstallResult::failure($domain, ["Domain not found: {$domain}"]);
        }

        $manifest = $manifests[$normalizedName];
        $installed = $this->getInstalledDomains();

        // Check if already installed
        if (in_array($normalizedName, $installed, true)) {
            return InstallResult::success($domain, [], [], [], ['Already installed']);
        }

        $this->dependencyResolver->setManifests($manifests);

        // Check for circular dependencies
        $cycle = $this->dependencyResolver->detectCircularDependencies($normalizedName);
        if ($cycle !== null) {
            return InstallResult::failure($domain, [
                'Circular dependency detected: ' . implode(' -> ', $cycle),
            ]);
        }

        // Get missing dependencies
        $missing = $this->dependencyResolver->getMissingDependencies($normalizedName, $installed);

        if (! empty($missing) && ! $withDependencies) {
            return InstallResult::failure($domain, [
                'Missing dependencies: ' . implode(', ', $missing),
                'Use --with-dependencies to install them automatically',
            ]);
        }

        $installedDeps = [];
        $allMigrations = [];
        $allConfigs = [];
        $warnings = [];

        // Install dependencies first
        if ($withDependencies && ! empty($missing)) {
            $installOrder = $this->dependencyResolver->getInstallationOrder($normalizedName);

            foreach ($installOrder as $depName) {
                if ($depName === $normalizedName) {
                    continue;
                }

                if (in_array($depName, $installed, true)) {
                    continue;
                }

                $depResult = $this->installSingle($depName, $manifests[$depName]);
                if (! $depResult->success) {
                    return InstallResult::failure($domain, [
                        "Failed to install dependency {$depName}: " . implode(', ', $depResult->errors),
                    ]);
                }

                $installedDeps[] = $depName;
                $allMigrations = array_merge($allMigrations, $depResult->migrationsRun);
                $allConfigs = array_merge($allConfigs, $depResult->configsPublished);
                $warnings = array_merge($warnings, $depResult->warnings);
            }
        }

        // Install the domain itself
        $result = $this->installSingle($normalizedName, $manifest);

        if (! $result->success) {
            return $result;
        }

        // Update installed domains cache
        $installed[] = $normalizedName;
        Cache::put(self::INSTALLED_DOMAINS_KEY, array_unique($installed), self::CACHE_TTL);

        Log::info('Domain installed', [
            'domain'       => $normalizedName,
            'dependencies' => $installedDeps,
        ]);

        return InstallResult::success(
            domain: $domain,
            installedDependencies: $installedDeps,
            migrationsRun: array_merge($allMigrations, $result->migrationsRun),
            configsPublished: array_merge($allConfigs, $result->configsPublished),
            warnings: array_merge($warnings, $result->warnings),
        );
    }

    /**
     * Remove a domain.
     */
    public function remove(string $domain, bool $force = false): RemoveResult
    {
        $normalizedName = $this->normalizeDomainName($domain);
        $manifests = $this->loadAllManifests();

        if (! isset($manifests[$normalizedName])) {
            return RemoveResult::failure($domain, ["Domain not found: {$domain}"]);
        }

        $manifest = $manifests[$normalizedName];
        $installed = $this->getInstalledDomains();

        // Check if installed
        if (! in_array($normalizedName, $installed, true)) {
            return RemoveResult::success($domain, [], [], ['Not installed']);
        }

        // Cannot remove core domains
        if ($manifest->type === DomainType::CORE) {
            return RemoveResult::failure($domain, [
                'Cannot remove core domain. Core domains are required for the system to function.',
            ]);
        }

        // Check for dependents
        $this->dependencyResolver->setManifests($manifests);
        $dependents = $this->dependencyResolver->getDependentDomains($normalizedName, $installed);

        if (! empty($dependents) && ! $force) {
            return RemoveResult::failure($domain, [
                'Domain is required by: ' . implode(', ', $dependents),
                'Use --force to remove anyway (may break dependent domains)',
            ]);
        }

        $warnings = [];
        if (! empty($dependents)) {
            $warnings[] = 'Force-removed despite dependents: ' . implode(', ', $dependents);
        }

        // Revert migrations
        $migrationsReverted = [];
        $migrationPath = $manifest->getPath('migrations');

        if ($migrationPath !== null && File::isDirectory($migrationPath)) {
            try {
                Artisan::call('migrate:rollback', [
                    '--path'  => $this->getRelativePath($migrationPath),
                    '--force' => true,
                ]);
                $migrationsReverted[] = $migrationPath;
            } catch (Exception $e) {
                $warnings[] = "Failed to rollback migrations: {$e->getMessage()}";
            }
        }

        // Remove from installed list
        $installed = array_filter($installed, fn ($d) => $d !== $normalizedName);
        Cache::put(self::INSTALLED_DOMAINS_KEY, array_values($installed), self::CACHE_TTL);

        Log::info('Domain removed', [
            'domain'   => $normalizedName,
            'forced'   => $force,
            'warnings' => $warnings,
        ]);

        return RemoveResult::success(
            domain: $domain,
            migrationsReverted: $migrationsReverted,
            warnings: $warnings,
        );
    }

    /**
     * Get the dependency tree for a domain.
     */
    public function getDependencies(string $domain): DependencyNode
    {
        $manifests = $this->loadAllManifests();
        $this->dependencyResolver->setManifests($manifests);

        return $this->dependencyResolver->buildDependencyTree(
            $this->normalizeDomainName($domain),
            includeOptional: true
        );
    }

    /**
     * Verify a domain's health.
     */
    public function verify(string $domain): VerificationResult
    {
        $normalizedName = $this->normalizeDomainName($domain);
        $manifests = $this->loadAllManifests();

        if (! isset($manifests[$normalizedName])) {
            return VerificationResult::fromChecks($domain, [], ["Domain not found: {$domain}"]);
        }

        $manifest = $manifests[$normalizedName];
        $installed = $this->getInstalledDomains();
        $checks = [];
        $errors = [];
        $warnings = [];

        // Check 1: Manifest exists and is valid
        $checks['manifest_valid'] = true;

        // Check 2: Domain is installed
        $checks['is_installed'] = in_array($normalizedName, $installed, true);
        if (! $checks['is_installed']) {
            $errors[] = 'Domain is not installed';
        }

        // Check 3: All required dependencies are installed
        $this->dependencyResolver->setManifests($manifests);
        $missing = $this->dependencyResolver->getMissingDependencies($normalizedName, $installed);
        $checks['dependencies_satisfied'] = empty($missing);
        if (! $checks['dependencies_satisfied']) {
            $errors[] = 'Missing dependencies: ' . implode(', ', $missing);
        }

        // Check 4: Service provider exists
        $serviceProviderPath = $manifest->basePath . '/Providers';
        $checks['service_provider_exists'] = File::isDirectory($serviceProviderPath)
            || File::exists($manifest->basePath . '/DomainServiceProvider.php');

        // Check 5: Declared interfaces are implemented
        foreach ($manifest->providesInterfaces as $interface) {
            $interfaceExists = interface_exists($interface) || class_exists($interface);
            $checks["interface_{$interface}"] = $interfaceExists;
            if (! $interfaceExists) {
                $warnings[] = "Declared interface not found: {$interface}";
            }
        }

        // Check 6: Routes file exists if declared
        $routesPath = $manifest->getPath('routes');
        if ($routesPath !== null) {
            $checks['routes_exist'] = File::exists($routesPath);
            if (! $checks['routes_exist']) {
                $warnings[] = "Declared routes file not found: {$routesPath}";
            }
        }

        // Check 7: Config file exists if declared
        $configPath = $manifest->getPath('config');
        if ($configPath !== null) {
            $checks['config_exists'] = File::exists($configPath);
            if (! $checks['config_exists']) {
                $warnings[] = "Declared config file not found: {$configPath}";
            }
        }

        return VerificationResult::fromChecks($domain, $checks, $errors, $warnings);
    }

    /**
     * Load all domain manifests.
     *
     * @return array<string, ModuleManifest>
     */
    public function loadAllManifests(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            $this->manifestCache = $cached;

            return $cached;
        }

        $manifests = [];
        $basePath = base_path($this->domainBasePath);

        if (! File::isDirectory($basePath)) {
            return [];
        }

        $directories = File::directories($basePath);

        foreach ($directories as $domainDir) {
            $manifestPath = $domainDir . '/module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromFile($manifestPath);
                $manifests[$manifest->name] = $manifest;
            } catch (Exception $e) {
                Log::warning('Failed to parse module manifest', [
                    'path'  => $manifestPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Cache::put(self::CACHE_KEY, $manifests, self::CACHE_TTL);
        $this->manifestCache = $manifests;

        return $manifests;
    }

    /**
     * Clear the manifest cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::INSTALLED_DOMAINS_KEY);
        Cache::forget(self::DISABLED_DOMAINS_KEY);
        $this->manifestCache = null;
    }

    /**
     * Enable a previously disabled domain.
     */
    public function enable(string $domain): InstallResult
    {
        $normalizedName = $this->normalizeDomainName($domain);
        $manifests = $this->loadAllManifests();

        if (! isset($manifests[$normalizedName])) {
            return InstallResult::failure($domain, ["Domain not found: {$domain}"]);
        }

        if (! $this->isDisabled($normalizedName)) {
            return InstallResult::success($domain, [], [], [], ['Domain is already enabled']);
        }

        // Remove from disabled list
        $disabled = $this->getDisabledDomains();
        $disabled = array_values(array_filter($disabled, fn ($d) => $d !== $normalizedName));
        Cache::put(self::DISABLED_DOMAINS_KEY, $disabled, self::CACHE_TTL);

        // Mark as installed
        $installed = $this->getInstalledDomains();
        if (! in_array($normalizedName, $installed, true)) {
            $installed[] = $normalizedName;
            Cache::put(self::INSTALLED_DOMAINS_KEY, array_unique($installed), self::CACHE_TTL);
        }

        Log::info('Domain enabled', ['domain' => $normalizedName]);

        return InstallResult::success($domain, [], [], [], ['Domain enabled successfully']);
    }

    /**
     * Disable a domain without reverting migrations.
     */
    public function disable(string $domain): RemoveResult
    {
        $normalizedName = $this->normalizeDomainName($domain);
        $manifests = $this->loadAllManifests();

        if (! isset($manifests[$normalizedName])) {
            return RemoveResult::failure($domain, ["Domain not found: {$domain}"]);
        }

        $manifest = $manifests[$normalizedName];

        // Cannot disable core domains
        if ($manifest->type === DomainType::CORE) {
            return RemoveResult::failure($domain, [
                'Cannot disable core domain. Core domains are required for the system to function.',
            ]);
        }

        if ($this->isDisabled($normalizedName)) {
            return RemoveResult::success($domain, [], [], ['Domain is already disabled']);
        }

        // Add to disabled list
        $disabled = $this->getDisabledDomains();
        $disabled[] = $normalizedName;
        Cache::put(self::DISABLED_DOMAINS_KEY, array_unique($disabled), self::CACHE_TTL);

        // Remove from installed list
        $installed = $this->getInstalledDomains();
        $installed = array_values(array_filter($installed, fn ($d) => $d !== $normalizedName));
        Cache::put(self::INSTALLED_DOMAINS_KEY, $installed, self::CACHE_TTL);

        Log::info('Domain disabled', ['domain' => $normalizedName]);

        return RemoveResult::success($domain, [], [], ['Domain disabled (migrations preserved)']);
    }

    /**
     * Check if a domain is disabled.
     *
     * Comparison is prefix- and case-insensitive so MODULES_DISABLED accepts
     * either bare directory names (Lending) or full manifest names
     * (finaegis/lending).
     */
    public function isDisabled(string $domain): bool
    {
        $needle = self::canonicalizeDomainName($domain);

        foreach ($this->getDisabledDomains() as $entry) {
            if (self::canonicalizeDomainName($entry) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Config-only disabled check, safe to call at boot time.
     *
     * Used by routes/console.php to gate demo-domain scheduled jobs without
     * touching the cache store (which may not be reachable while the
     * scheduler is bootstrapping). Reads modules.disabled (MODULES_DISABLED)
     * directly and applies the same canonical matching as isDisabled().
     */
    public static function isDisabledByConfig(string $domain): bool
    {
        /** @var array<int|string, string> $disabled */
        $disabled = config('modules.disabled', []);
        $needle = self::canonicalizeDomainName($domain);

        foreach ($disabled as $entry) {
            if (self::canonicalizeDomainName($entry) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of disabled domains.
     *
     * @return array<string>
     */
    public function getDisabledDomains(): array
    {
        /** @var array<string> $cached */
        $cached = Cache::get(self::DISABLED_DOMAINS_KEY, []);
        if (! empty($cached)) {
            return $cached;
        }

        // Fall back to config
        /** @var array<string> $configDisabled */
        $configDisabled = config('modules.disabled', []);

        return array_filter($configDisabled);
    }

    /**
     * Install a single domain.
     */
    private function installSingle(string $name, ModuleManifest $manifest): InstallResult
    {
        $migrationsRun = [];
        $configsPublished = [];
        $warnings = [];

        // Run migrations if declared
        $migrationPath = $manifest->getPath('migrations');

        if ($migrationPath !== null && File::isDirectory($migrationPath)) {
            try {
                Artisan::call('migrate', [
                    '--path'  => $this->getRelativePath($migrationPath),
                    '--force' => true,
                ]);
                $migrationsRun[] = $migrationPath;
            } catch (Exception $e) {
                return InstallResult::failure($name, ["Migration failed: {$e->getMessage()}"]);
            }
        }

        // Publish config if declared
        $configPath = $manifest->getPath('config');

        if ($configPath !== null && File::exists($configPath)) {
            $targetPath = config_path(basename($configPath));

            if (! File::exists($targetPath)) {
                try {
                    File::copy($configPath, $targetPath);
                    $configsPublished[] = $targetPath;
                } catch (Exception $e) {
                    $warnings[] = "Failed to publish config: {$e->getMessage()}";
                }
            }
        }

        return InstallResult::success(
            domain: $name,
            migrationsRun: $migrationsRun,
            configsPublished: $configsPublished,
            warnings: $warnings,
        );
    }

    /**
     * Check if a domain has migrations that have been run.
     */
    private function hasRunMigrations(ModuleManifest $manifest): bool
    {
        $migrationPath = $manifest->getPath('migrations');

        if ($migrationPath === null || ! File::isDirectory($migrationPath)) {
            // No migrations = considered installed (doesn't need migrations)
            return true;
        }

        // Check if migration files exist
        $migrations = File::files($migrationPath);

        return ! empty($migrations);
    }

    /**
     * Get domains that depend on the given domain.
     *
     * @param array<string, ModuleManifest> $manifests
     * @param array<string> $installed
     * @return array<string>
     */
    private function getDependents(string $domainName, array $manifests, array $installed): array
    {
        $dependents = [];

        foreach ($manifests as $name => $manifest) {
            if (! in_array($name, $installed, true)) {
                continue;
            }

            if ($manifest->requires($domainName)) {
                $dependents[] = $name;
            }
        }

        return $dependents;
    }

    /**
     * Normalize domain name.
     */
    private function normalizeDomainName(string $name): string
    {
        if (! str_contains($name, '/')) {
            return "finaegis/{$name}";
        }

        return $name;
    }

    /**
     * Canonical form used for disabled-list comparisons: trimmed,
     * vendor-prefixed, lowercase. Makes matching tolerant of how the
     * value was written in MODULES_DISABLED (Lending vs finaegis/Lending
     * vs finaegis/lending).
     */
    private static function canonicalizeDomainName(string $name): string
    {
        $name = trim($name);

        if (! str_contains($name, '/')) {
            $name = "finaegis/{$name}";
        }

        return strtolower($name);
    }

    /**
     * Get relative path from base_path.
     */
    private function getRelativePath(string $absolutePath): string
    {
        return str_replace(base_path() . '/', '', $absolutePath);
    }
}
