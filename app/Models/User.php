<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Cashier\Billable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Values\UserRoles;
use App\Models\Account;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use Billable;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'oauth_provider',
        'oauth_id',
        'avatar',
        'kyc_status',
        'kyc_submitted_at',
        'kyc_approved_at',
        'kyc_expires_at',
        'kyc_level',
        'pep_status',
        'risk_rating',
        'kyc_data',
        'privacy_policy_accepted_at',
        'terms_accepted_at',
        'marketing_consent_at',
        'data_retention_consent',
        'has_completed_onboarding',
        'onboarding_completed_at',
        'country_code', // Added for testing KYC/AML
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'kyc_submitted_at' => 'datetime',
            'kyc_approved_at' => 'datetime',
            'kyc_expires_at' => 'datetime',
            'pep_status' => 'boolean',
            'kyc_data' => 'encrypted:array',
            'privacy_policy_accepted_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'data_retention_consent' => 'boolean',
            'has_completed_onboarding' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }


    /**
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(UserRoles::ADMIN->value);
    }

    /**
     * Get the accounts for the user.
     */
    public function accounts()
    {
        return $this->hasMany(Account::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the primary account for the user.
     * This returns the first account which is typically the default one created on registration.
     */
    public function account()
    {
        return $this->hasOne(Account::class, 'user_uuid', 'uuid')->orderBy('created_at', 'asc');
    }

    /**
     * Get the bank preferences for the user.
     */
    public function bankPreferences()
    {
        return $this->hasMany(UserBankPreference::class, 'user_uuid', 'uuid');
    }
    
    /**
     * Get the bank accounts for the user.
     */
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class, 'user_uuid', 'uuid');
    }

    /**
     * Get active bank preferences for the user.
     */
    public function activeBankPreferences()
    {
        return $this->bankPreferences()->active();
    }

    /**
     * Get the KYC documents for the user.
     */
    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class, 'user_uuid', 'uuid');
    }

    /**
     * Check if user has completed KYC
     */
    public function hasCompletedKyc(): bool
    {
        return $this->kyc_status === 'approved' && 
               ($this->kyc_expires_at === null || $this->kyc_expires_at->isFuture());
    }

    /**
     * Check if user needs KYC
     */
    public function needsKyc(): bool
    {
        return in_array($this->kyc_status, ['not_started', 'rejected', 'expired']) ||
               ($this->kyc_status === 'approved' && $this->kyc_expires_at && $this->kyc_expires_at->isPast());
    }

    /**
     * Check if user has completed onboarding
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->has_completed_onboarding === true;
    }

    /**
     * Mark onboarding as completed
     */
    public function completeOnboarding(): void
    {
        $this->update([
            'has_completed_onboarding' => true,
            'onboarding_completed_at' => now(),
        ]);
    }
    
    /**
     * Get the CGO investments for the user.
     */
    public function cgoInvestments(): HasMany
    {
        return $this->hasMany(CgoInvestment::class);
    }
    
    /**
     * Get the API keys for the user.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'user_uuid', 'uuid');
    }
}
