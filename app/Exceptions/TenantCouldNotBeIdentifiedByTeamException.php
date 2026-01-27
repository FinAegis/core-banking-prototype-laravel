<?php

declare(strict_types=1);

namespace App\Exceptions;

use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Throwable;

/**
 * Exception thrown when a tenant cannot be identified by team.
 *
 * Security considerations:
 * - In production, error messages do not expose internal team IDs
 * - Detailed information is only available via getter methods for logging
 * - The public message is intentionally vague for security
 */
class TenantCouldNotBeIdentifiedByTeamException extends TenantCouldNotBeIdentifiedException
{
    public function __construct(
        private readonly ?int $teamId = null
    ) {
        // Use a generic message that doesn't expose internal IDs in production
        // Detailed information is available via getTeamId() for logging purposes
        $isProduction = $this->isProductionEnvironment();

        $message = $isProduction
            ? 'Tenant context could not be established'
            : ($teamId
                ? "Tenant could not be identified for team ID: {$teamId}"
                : 'Tenant could not be identified: No team context available');

        parent::__construct($message);
    }

    /**
     * Check if running in production environment.
     *
     * Safely checks even when Laravel isn't fully bootstrapped.
     */
    private function isProductionEnvironment(): bool
    {
        try {
            return function_exists('app') && app()->environment('production');
        } catch (Throwable) {
            // If app() fails, assume not production (for unit tests)
            return false;
        }
    }

    /**
     * Get the team ID that failed resolution.
     *
     * This should be used for internal logging, not exposed to users.
     */
    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    /**
     * Get safe context for logging without exposing in responses.
     *
     * @return array<string, mixed>
     */
    public function getLogContext(): array
    {
        return [
            'exception'   => static::class,
            'team_id'     => $this->teamId,
            'has_team_id' => $this->teamId !== null,
        ];
    }
}
