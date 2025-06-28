<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FinancialInstitutionPartner extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    
    protected $fillable = [
        'partner_code',
        'application_id',
        'institution_name',
        'legal_name',
        'institution_type',
        'country',
        'status',
        'api_client_id',
        'api_client_secret',
        'webhook_secret',
        'api_permissions',
        'allowed_ip_addresses',
        'sandbox_enabled',
        'production_enabled',
        'enabled_features',
        'disabled_features',
        'rate_limit_per_minute',
        'rate_limit_per_day',
        'max_transaction_amount',
        'daily_transaction_limit',
        'monthly_transaction_limit',
        'max_accounts_per_user',
        'allowed_currencies',
        'allowed_countries',
        'fee_structure',
        'revenue_share_percentage',
        'billing_cycle',
        'next_billing_date',
        'risk_rating',
        'risk_score',
        'compliance_requirements',
        'last_audit_date',
        'next_audit_date',
        'primary_contact',
        'technical_contact',
        'compliance_contact',
        'billing_contact',
        'total_accounts',
        'active_accounts',
        'total_transactions',
        'total_volume',
        'last_activity_at',
        'webhook_endpoints',
        'webhook_active',
        'webhook_retry_count',
        'metadata',
        'activated_at',
        'suspended_at',
        'terminated_at',
        'suspension_reason',
        'termination_reason',
    ];
    
    protected $casts = [
        'api_permissions' => 'array',
        'allowed_ip_addresses' => 'array',
        'enabled_features' => 'array',
        'disabled_features' => 'array',
        'allowed_currencies' => 'array',
        'allowed_countries' => 'array',
        'fee_structure' => 'array',
        'compliance_requirements' => 'array',
        'primary_contact' => 'array',
        'technical_contact' => 'array',
        'compliance_contact' => 'array',
        'billing_contact' => 'array',
        'webhook_endpoints' => 'array',
        'metadata' => 'array',
        'sandbox_enabled' => 'boolean',
        'production_enabled' => 'boolean',
        'webhook_active' => 'boolean',
        'max_transaction_amount' => 'decimal:2',
        'daily_transaction_limit' => 'decimal:2',
        'monthly_transaction_limit' => 'decimal:2',
        'revenue_share_percentage' => 'decimal:2',
        'risk_score' => 'decimal:2',
        'total_volume' => 'decimal:2',
        'next_billing_date' => 'date',
        'last_audit_date' => 'date',
        'next_audit_date' => 'date',
        'last_activity_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];
    
    /**
     * Partner statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_TERMINATED = 'terminated';
    
    /**
     * Billing cycles
     */
    const BILLING_MONTHLY = 'monthly';
    const BILLING_QUARTERLY = 'quarterly';
    const BILLING_ANNUALLY = 'annually';
    
    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($partner) {
            if (empty($partner->partner_code)) {
                $partner->partner_code = static::generatePartnerCode();
            }
            
            if (empty($partner->api_client_id)) {
                $partner->api_client_id = static::generateApiClientId();
            }
            
            if (empty($partner->api_client_secret)) {
                $partner->api_client_secret = encrypt(Str::random(64));
            }
            
            if (empty($partner->webhook_secret)) {
                $partner->webhook_secret = encrypt(Str::random(32));
            }
        });
    }
    
    /**
     * Generate unique partner code
     */
    public static function generatePartnerCode(): string
    {
        do {
            $code = 'FIP-' . strtoupper(Str::random(5));
        } while (static::where('partner_code', $code)->exists());
        
        return $code;
    }
    
    /**
     * Generate unique API client ID
     */
    public static function generateApiClientId(): string
    {
        do {
            $clientId = 'fip_' . Str::random(32);
        } while (static::where('api_client_id', $clientId)->exists());
        
        return $clientId;
    }
    
    /**
     * Get the application
     */
    public function application()
    {
        return $this->belongsTo(FinancialInstitutionApplication::class, 'application_id');
    }
    
    /**
     * Scope for active partners
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
    
    /**
     * Scope for production enabled partners
     */
    public function scopeProductionEnabled($query)
    {
        return $query->where('production_enabled', true);
    }
    
    /**
     * Check if partner is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    
    /**
     * Check if partner is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }
    
    /**
     * Check if partner is terminated
     */
    public function isTerminated(): bool
    {
        return $this->status === self::STATUS_TERMINATED;
    }
    
    /**
     * Check if partner has production access
     */
    public function hasProductionAccess(): bool
    {
        return $this->isActive() && $this->production_enabled;
    }
    
    /**
     * Check if partner has sandbox access
     */
    public function hasSandboxAccess(): bool
    {
        return $this->isActive() && $this->sandbox_enabled;
    }
    
    /**
     * Suspend partner
     */
    public function suspend(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'production_enabled' => false,
        ]);
    }
    
    /**
     * Reactivate partner
     */
    public function reactivate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }
    
    /**
     * Terminate partner
     */
    public function terminate(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_TERMINATED,
            'terminated_at' => now(),
            'termination_reason' => $reason,
            'production_enabled' => false,
            'sandbox_enabled' => false,
        ]);
    }
    
    /**
     * Update activity metrics
     */
    public function updateActivityMetrics(): void
    {
        // This would be called from transaction processing
        $this->update([
            'last_activity_at' => now(),
        ]);
    }
    
    /**
     * Check if IP address is allowed
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ip_addresses)) {
            return true; // No restriction
        }
        
        return in_array($ip, $this->allowed_ip_addresses);
    }
    
    /**
     * Check if currency is allowed
     */
    public function isCurrencyAllowed(string $currency): bool
    {
        if (empty($this->allowed_currencies)) {
            return true; // No restriction
        }
        
        return in_array($currency, $this->allowed_currencies);
    }
    
    /**
     * Check if country is allowed
     */
    public function isCountryAllowed(string $country): bool
    {
        if (empty($this->allowed_countries)) {
            return true; // No restriction
        }
        
        return in_array($country, $this->allowed_countries);
    }
    
    /**
     * Check if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if (!empty($this->disabled_features) && in_array($feature, $this->disabled_features)) {
            return false;
        }
        
        if (empty($this->enabled_features)) {
            return true; // All features enabled by default
        }
        
        return in_array($feature, $this->enabled_features);
    }
    
    /**
     * Check if transaction amount is within limits
     */
    public function isWithinTransactionLimit(float $amount): bool
    {
        if ($this->max_transaction_amount && $amount > $this->max_transaction_amount) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get decrypted API client secret
     */
    public function getApiClientSecret(): string
    {
        return decrypt($this->api_client_secret);
    }
    
    /**
     * Get decrypted webhook secret
     */
    public function getWebhookSecret(): string
    {
        return decrypt($this->webhook_secret);
    }
    
    /**
     * Regenerate API credentials
     */
    public function regenerateApiCredentials(): array
    {
        $newClientId = static::generateApiClientId();
        $newClientSecret = Str::random(64);
        
        $this->update([
            'api_client_id' => $newClientId,
            'api_client_secret' => encrypt($newClientSecret),
        ]);
        
        return [
            'client_id' => $newClientId,
            'client_secret' => $newClientSecret,
        ];
    }
    
    /**
     * Get status badge color
     */
    public function getStatusBadgeColor(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_SUSPENDED => 'warning',
            self::STATUS_TERMINATED => 'danger',
            default => 'secondary',
        };
    }
}