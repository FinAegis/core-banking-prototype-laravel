<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

class BankCapabilities
{
    public function __construct(
        public readonly array $supportedCurrencies,
        public readonly array $supportedTransferTypes,
        public readonly array $features,
        public readonly array $limits,
        public readonly array $fees,
        public readonly bool $supportsInstantTransfers,
        public readonly bool $supportsScheduledTransfers,
        public readonly bool $supportsBulkTransfers,
        public readonly bool $supportsDirectDebits,
        public readonly bool $supportsStandingOrders,
        public readonly bool $supportsVirtualAccounts,
        public readonly bool $supportsMultiCurrency,
        public readonly bool $supportsWebhooks,
        public readonly bool $supportsStatements,
        public readonly bool $supportsCardIssuance,
        public readonly int $maxAccountsPerUser,
        public readonly array $requiredDocuments,
        public readonly array $availableCountries,
    ) {}
    
    /**
     * Check if a specific feature is supported
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features);
    }
    
    /**
     * Check if a currency is supported
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, $this->supportedCurrencies);
    }
    
    /**
     * Check if a transfer type is supported
     */
    public function supportsTransferType(string $type): bool
    {
        return in_array($type, $this->supportedTransferTypes);
    }
    
    /**
     * Get transfer limit for a specific type and currency
     */
    public function getTransferLimit(string $type, string $currency): ?array
    {
        return $this->limits[$type][$currency] ?? null;
    }
    
    /**
     * Get fee structure for a specific operation
     */
    public function getFee(string $operation, string $currency): ?array
    {
        return $this->fees[$operation][$currency] ?? null;
    }
    
    /**
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'supported_currencies' => $this->supportedCurrencies,
            'supported_transfer_types' => $this->supportedTransferTypes,
            'features' => $this->features,
            'limits' => $this->limits,
            'fees' => $this->fees,
            'supports_instant_transfers' => $this->supportsInstantTransfers,
            'supports_scheduled_transfers' => $this->supportsScheduledTransfers,
            'supports_bulk_transfers' => $this->supportsBulkTransfers,
            'supports_direct_debits' => $this->supportsDirectDebits,
            'supports_standing_orders' => $this->supportsStandingOrders,
            'supports_virtual_accounts' => $this->supportsVirtualAccounts,
            'supports_multi_currency' => $this->supportsMultiCurrency,
            'supports_webhooks' => $this->supportsWebhooks,
            'supports_statements' => $this->supportsStatements,
            'supports_card_issuance' => $this->supportsCardIssuance,
            'max_accounts_per_user' => $this->maxAccountsPerUser,
            'required_documents' => $this->requiredDocuments,
            'available_countries' => $this->availableCountries,
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            supportedCurrencies: $data['supported_currencies'] ?? [],
            supportedTransferTypes: $data['supported_transfer_types'] ?? [],
            features: $data['features'] ?? [],
            limits: $data['limits'] ?? [],
            fees: $data['fees'] ?? [],
            supportsInstantTransfers: $data['supports_instant_transfers'] ?? false,
            supportsScheduledTransfers: $data['supports_scheduled_transfers'] ?? false,
            supportsBulkTransfers: $data['supports_bulk_transfers'] ?? false,
            supportsDirectDebits: $data['supports_direct_debits'] ?? false,
            supportsStandingOrders: $data['supports_standing_orders'] ?? false,
            supportsVirtualAccounts: $data['supports_virtual_accounts'] ?? false,
            supportsMultiCurrency: $data['supports_multi_currency'] ?? false,
            supportsWebhooks: $data['supports_webhooks'] ?? false,
            supportsStatements: $data['supports_statements'] ?? false,
            supportsCardIssuance: $data['supports_card_issuance'] ?? false,
            maxAccountsPerUser: $data['max_accounts_per_user'] ?? 1,
            requiredDocuments: $data['required_documents'] ?? [],
            availableCountries: $data['available_countries'] ?? [],
        );
    }
}