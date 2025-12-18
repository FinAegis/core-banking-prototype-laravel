<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

/**
 * Agent OAuth2-style scopes for API authorization.
 *
 * Scopes control what operations an agent's API key or session can perform.
 * Use wildcards (*) for category-wide or universal access.
 */
enum AgentScope: string
{
    // Payment scopes
    case PAYMENTS_READ = 'payments:read';
    case PAYMENTS_CREATE = 'payments:create';
    case PAYMENTS_CANCEL = 'payments:cancel';
    case PAYMENTS_ALL = 'payments:*';

    // Wallet scopes
    case WALLET_READ = 'wallet:read';
    case WALLET_TRANSFER = 'wallet:transfer';
    case WALLET_WITHDRAW = 'wallet:withdraw';
    case WALLET_ALL = 'wallet:*';

    // Escrow scopes
    case ESCROW_READ = 'escrow:read';
    case ESCROW_CREATE = 'escrow:create';
    case ESCROW_RELEASE = 'escrow:release';
    case ESCROW_DISPUTE = 'escrow:dispute';
    case ESCROW_ALL = 'escrow:*';

    // Messaging scopes
    case MESSAGES_READ = 'messages:read';
    case MESSAGES_SEND = 'messages:send';
    case MESSAGES_WRITE = 'messages:write';
    case MESSAGES_ALL = 'messages:*';

    // Agent management scopes
    case AGENT_READ = 'agent:read';
    case AGENT_UPDATE = 'agent:update';
    case AGENT_KEYS = 'agent:keys';
    case AGENT_SESSIONS = 'agent:sessions';
    case AGENT_ALL = 'agent:*';

    // Reputation scopes
    case REPUTATION_READ = 'reputation:read';
    case REPUTATION_REVIEW = 'reputation:review';
    case REPUTATION_WRITE = 'reputation:write';
    case REPUTATION_ALL = 'reputation:*';

    // Compliance scopes
    case COMPLIANCE_READ = 'compliance:read';
    case COMPLIANCE_VERIFY = 'compliance:verify';
    case COMPLIANCE_ALL = 'compliance:*';

    // Administrative scopes (restricted)
    case ADMIN_READ = 'admin:read';
    case ADMIN_MANAGE = 'admin:manage';
    case ADMIN_ALL = 'admin:*';

    // Universal scope
    case UNIVERSAL = '*';

    /**
     * Get a human-readable description for the scope.
     */
    public function description(): string
    {
        return match ($this) {
            self::PAYMENTS_READ   => 'View payment transactions and history',
            self::PAYMENTS_CREATE => 'Create new payment transactions',
            self::PAYMENTS_CANCEL => 'Cancel pending payment transactions',
            self::PAYMENTS_ALL    => 'Full access to payment operations',

            self::WALLET_READ     => 'View wallet balance and transactions',
            self::WALLET_TRANSFER => 'Transfer funds between wallets',
            self::WALLET_WITHDRAW => 'Withdraw funds from wallet',
            self::WALLET_ALL      => 'Full access to wallet operations',

            self::ESCROW_READ    => 'View escrow transactions',
            self::ESCROW_CREATE  => 'Create new escrow transactions',
            self::ESCROW_RELEASE => 'Release escrow funds',
            self::ESCROW_DISPUTE => 'Dispute escrow transactions',
            self::ESCROW_ALL     => 'Full access to escrow operations',

            self::MESSAGES_READ  => 'View A2A messages and conversations',
            self::MESSAGES_SEND  => 'Send A2A messages to other agents',
            self::MESSAGES_WRITE => 'Create and manage A2A messages',
            self::MESSAGES_ALL   => 'Full access to messaging operations',

            self::AGENT_READ     => 'View agent profile and capabilities',
            self::AGENT_UPDATE   => 'Update agent profile',
            self::AGENT_KEYS     => 'Manage API keys',
            self::AGENT_SESSIONS => 'Manage sessions',
            self::AGENT_ALL      => 'Full access to agent management',

            self::REPUTATION_READ   => 'View reputation scores',
            self::REPUTATION_REVIEW => 'Submit reviews and ratings',
            self::REPUTATION_WRITE  => 'Modify reputation data',
            self::REPUTATION_ALL    => 'Full access to reputation system',

            self::COMPLIANCE_READ   => 'View compliance status',
            self::COMPLIANCE_VERIFY => 'Submit verification documents',
            self::COMPLIANCE_ALL    => 'Full access to compliance operations',

            self::ADMIN_READ   => 'View administrative data',
            self::ADMIN_MANAGE => 'Manage agents and transactions',
            self::ADMIN_ALL    => 'Full administrative access',

            self::UNIVERSAL => 'Full access to all operations',
        };
    }

    /**
     * Get the category (prefix) for this scope.
     */
    public function category(): string
    {
        if ($this === self::UNIVERSAL) {
            return '*';
        }

        $parts = explode(':', $this->value);

        return $parts[0];
    }

    /**
     * Check if this is a wildcard scope.
     */
    public function isWildcard(): bool
    {
        return $this === self::UNIVERSAL || str_ends_with($this->value, ':*');
    }

    /**
     * Check if this scope covers another scope.
     *
     * @param AgentScope|string $other The scope to check coverage for
     */
    public function covers(AgentScope|string $other): bool
    {
        $otherValue = $other instanceof AgentScope ? $other->value : $other;

        // Universal scope covers everything
        if ($this === self::UNIVERSAL) {
            return true;
        }

        // Exact match
        if ($this->value === $otherValue) {
            return true;
        }

        // Category wildcard (e.g., payments:* covers payments:read)
        if ($this->isWildcard()) {
            $thisCategory = $this->category();
            $otherParts = explode(':', $otherValue);
            $otherCategory = $otherParts[0];

            return $thisCategory === $otherCategory;
        }

        return false;
    }

    /**
     * Get all scopes in a category.
     *
     * @return array<AgentScope>
     */
    public static function inCategory(string $category): array
    {
        $results = [];

        foreach (self::cases() as $scope) {
            if ($scope->category() === $category) {
                $results[] = $scope;
            }
        }

        return $results;
    }

    /**
     * Get the default scopes for a new agent.
     *
     * @return array<AgentScope>
     */
    public static function defaults(): array
    {
        return [
            self::PAYMENTS_READ,
            self::WALLET_READ,
            self::AGENT_READ,
            self::REPUTATION_READ,
            self::MESSAGES_READ,
        ];
    }

    /**
     * Get all read-only scopes.
     *
     * @return array<AgentScope>
     */
    public static function readOnly(): array
    {
        return [
            self::PAYMENTS_READ,
            self::WALLET_READ,
            self::ESCROW_READ,
            self::MESSAGES_READ,
            self::AGENT_READ,
            self::REPUTATION_READ,
            self::COMPLIANCE_READ,
            self::ADMIN_READ,
        ];
    }

    /**
     * Get all write/modify scopes.
     *
     * @return array<AgentScope>
     */
    public static function writeScopes(): array
    {
        return [
            self::PAYMENTS_CREATE,
            self::PAYMENTS_CANCEL,
            self::WALLET_TRANSFER,
            self::WALLET_WITHDRAW,
            self::ESCROW_CREATE,
            self::ESCROW_RELEASE,
            self::ESCROW_DISPUTE,
            self::MESSAGES_SEND,
            self::MESSAGES_WRITE,
            self::AGENT_UPDATE,
            self::AGENT_KEYS,
            self::AGENT_SESSIONS,
            self::REPUTATION_REVIEW,
            self::REPUTATION_WRITE,
            self::COMPLIANCE_VERIFY,
            self::ADMIN_MANAGE,
        ];
    }

    /**
     * Get all wildcard scopes.
     *
     * @return array<AgentScope>
     */
    public static function wildcards(): array
    {
        return [
            self::PAYMENTS_ALL,
            self::WALLET_ALL,
            self::ESCROW_ALL,
            self::MESSAGES_ALL,
            self::AGENT_ALL,
            self::REPUTATION_ALL,
            self::COMPLIANCE_ALL,
            self::ADMIN_ALL,
            self::UNIVERSAL,
        ];
    }

    /**
     * Convert scope string values to enum instances.
     *
     * @param array<string> $scopeValues
     * @return array<AgentScope>
     */
    public static function fromValues(array $scopeValues): array
    {
        $results = [];

        foreach ($scopeValues as $value) {
            $scope = self::tryFrom($value);
            if ($scope !== null) {
                $results[] = $scope;
            }
        }

        return $results;
    }

    /**
     * Check if a set of scopes includes the required scope.
     *
     * @param array<AgentScope|string> $available Available scopes
     * @param AgentScope|string $required Required scope
     */
    public static function hasScope(array $available, AgentScope|string $required): bool
    {
        // Empty scopes means all allowed (backward compatibility)
        if (empty($available)) {
            return true;
        }

        $requiredValue = $required instanceof AgentScope ? $required->value : $required;

        foreach ($available as $scope) {
            $scopeValue = $scope instanceof AgentScope ? $scope->value : $scope;

            // Universal access
            if ($scopeValue === '*') {
                return true;
            }

            // Exact match
            if ($scopeValue === $requiredValue) {
                return true;
            }

            // Category wildcard
            if (str_ends_with($scopeValue, ':*')) {
                $category = substr($scopeValue, 0, -2);
                if (str_starts_with($requiredValue, $category . ':')) {
                    return true;
                }
            }
        }

        return false;
    }
}
