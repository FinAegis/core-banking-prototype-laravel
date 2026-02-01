<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Services;

use App\Domain\FinancialInstitution\Enums\PartnerTier;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerBranding;
use App\Domain\FinancialInstitution\Models\PartnerUsageRecord;
use Illuminate\Support\Facades\Log;

/**
 * Manages partner tier operations including upgrades, downgrades, and feature access.
 */
class PartnerTierService
{
    /**
     * Get the partner's current tier.
     */
    public function getPartnerTier(FinancialInstitutionPartner $partner): PartnerTier
    {
        return PartnerTier::tryFrom($partner->tier ?? 'starter') ?? PartnerTier::STARTER;
    }

    /**
     * Upgrade a partner to a new tier.
     *
     * @param FinancialInstitutionPartner $partner
     * @param PartnerTier $newTier
     * @param bool $prorate
     * @return array{success: bool, message: string, changes: array<string, mixed>}
     */
    public function upgradeTier(
        FinancialInstitutionPartner $partner,
        PartnerTier $newTier,
        bool $prorate = true
    ): array {
        $currentTier = $this->getPartnerTier($partner);

        if ($newTier->value === $currentTier->value) {
            return [
                'success' => false,
                'message' => 'Partner is already on this tier',
                'changes' => [],
            ];
        }

        // Check if this is actually an upgrade
        $tierOrder = ['starter' => 1, 'growth' => 2, 'enterprise' => 3];
        if ($tierOrder[$newTier->value] < $tierOrder[$currentTier->value]) {
            return $this->downgradeTier($partner, $newTier);
        }

        $changes = [
            'previous_tier'       => $currentTier->value,
            'new_tier'            => $newTier->value,
            'previous_rate_limit' => $currentTier->rateLimitPerMinute(),
            'new_rate_limit'      => $newTier->rateLimitPerMinute(),
            'previous_api_limit'  => $currentTier->apiCallLimit(),
            'new_api_limit'       => $newTier->apiCallLimit(),
        ];

        $partner->update([
            'tier'                  => $newTier->value,
            'rate_limit_per_minute' => $newTier->rateLimitPerMinute(),
        ]);

        // Enable white-label if now available
        if ($newTier->hasWhiteLabel() && ! $currentTier->hasWhiteLabel()) {
            $partner->update(['white_label_enabled' => true]);
            $this->createDefaultBranding($partner);
            $changes['white_label_enabled'] = true;
        }

        Log::info('Partner tier upgraded', [
            'partner_id' => $partner->id,
            'changes'    => $changes,
        ]);

        return [
            'success' => true,
            'message' => "Upgraded from {$currentTier->label()} to {$newTier->label()}",
            'changes' => $changes,
        ];
    }

    /**
     * Downgrade a partner to a lower tier.
     *
     * @param FinancialInstitutionPartner $partner
     * @param PartnerTier $newTier
     * @return array{success: bool, message: string, changes: array<string, mixed>}
     */
    public function downgradeTier(FinancialInstitutionPartner $partner, PartnerTier $newTier): array
    {
        $currentTier = $this->getPartnerTier($partner);

        $changes = [
            'previous_tier'       => $currentTier->value,
            'new_tier'            => $newTier->value,
            'previous_rate_limit' => $currentTier->rateLimitPerMinute(),
            'new_rate_limit'      => $newTier->rateLimitPerMinute(),
            'previous_api_limit'  => $currentTier->apiCallLimit(),
            'new_api_limit'       => $newTier->apiCallLimit(),
            'features_removed'    => [],
        ];

        // Check for feature losses
        if ($currentTier->hasWhiteLabel() && ! $newTier->hasWhiteLabel()) {
            $changes['features_removed'][] = 'white_label';
            $partner->update(['white_label_enabled' => false]);
        }

        if ($currentTier->hasCustomDomain() && ! $newTier->hasCustomDomain()) {
            $changes['features_removed'][] = 'custom_domain';
            $partner->update(['custom_domain' => null]);
        }

        if ($currentTier->hasSdkAccess() && ! $newTier->hasSdkAccess()) {
            $changes['features_removed'][] = 'sdk_access';
        }

        if ($currentTier->hasWidgets() && ! $newTier->hasWidgets()) {
            $changes['features_removed'][] = 'widgets';
        }

        $partner->update([
            'tier'                  => $newTier->value,
            'rate_limit_per_minute' => $newTier->rateLimitPerMinute(),
        ]);

        Log::info('Partner tier downgraded', [
            'partner_id' => $partner->id,
            'changes'    => $changes,
        ]);

        return [
            'success' => true,
            'message' => "Downgraded from {$currentTier->label()} to {$newTier->label()}",
            'changes' => $changes,
        ];
    }

    /**
     * Check if a partner has access to a specific feature.
     */
    public function hasFeature(FinancialInstitutionPartner $partner, string $feature): bool
    {
        $tier = $this->getPartnerTier($partner);
        $features = $tier->features();

        return $features[$feature] ?? false;
    }

    /**
     * Get all features for a partner.
     *
     * @return array<string, bool>
     */
    public function getPartnerFeatures(FinancialInstitutionPartner $partner): array
    {
        return $this->getPartnerTier($partner)->features();
    }

    /**
     * Check if partner can use white-label.
     */
    public function canUseWhiteLabel(FinancialInstitutionPartner $partner): bool
    {
        return $this->getPartnerTier($partner)->hasWhiteLabel() &&
            ($partner->white_label_enabled ?? false);
    }

    /**
     * Check if partner can use custom domain.
     */
    public function canUseCustomDomain(FinancialInstitutionPartner $partner): bool
    {
        return $this->getPartnerTier($partner)->hasCustomDomain() &&
            ! empty($partner->custom_domain);
    }

    /**
     * Get the monthly API call limit for a partner.
     */
    public function getApiCallLimit(FinancialInstitutionPartner $partner): int
    {
        return $this->getPartnerTier($partner)->apiCallLimit();
    }

    /**
     * Get the rate limit per minute for a partner.
     */
    public function getRateLimitPerMinute(FinancialInstitutionPartner $partner): int
    {
        return $this->getPartnerTier($partner)->rateLimitPerMinute();
    }

    /**
     * Get monthly usage for a partner.
     *
     * @param FinancialInstitutionPartner $partner
     * @param int|null $year
     * @param int|null $month
     * @return array<string, mixed>
     */
    public function getMonthlyUsage(
        FinancialInstitutionPartner $partner,
        ?int $year = null,
        ?int $month = null
    ): array {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $records = PartnerUsageRecord::where('partner_id', $partner->id)
            ->whereYear('usage_date', $year)
            ->whereMonth('usage_date', $month)
            ->get();

        $tier = $this->getPartnerTier($partner);
        $limit = $tier->apiCallLimit();

        $totalCalls = $records->sum('api_calls');
        $overageCalls = max(0, $totalCalls - $limit);
        $overageAmount = ($overageCalls / 1000) * $tier->overagePricePerThousand();

        return [
            'tier'                => $tier->value,
            'tier_label'          => $tier->label(),
            'year'                => $year,
            'month'               => $month,
            'api_calls_total'     => $totalCalls,
            'api_calls_limit'     => $limit,
            'api_calls_remaining' => max(0, $limit - $totalCalls),
            'usage_percentage'    => $limit > 0 ? round(($totalCalls / $limit) * 100, 2) : 0,
            'overage_calls'       => $overageCalls,
            'overage_amount_usd'  => round($overageAmount, 2),
            'base_price_usd'      => $tier->monthlyPrice(),
            'projected_total_usd' => round($tier->monthlyPrice() + $overageAmount, 2),
        ];
    }

    /**
     * Create default branding for a partner.
     */
    public function createDefaultBranding(FinancialInstitutionPartner $partner): PartnerBranding
    {
        $defaultBranding = config('baas.white_label.default_branding', []);

        return PartnerBranding::create([
            'partner_id'      => $partner->id,
            'company_name'    => $partner->institution_name ?? $defaultBranding['company_name'] ?? 'Partner',
            'primary_color'   => $defaultBranding['primary_color'] ?? '#1a365d',
            'secondary_color' => $defaultBranding['secondary_color'] ?? '#2b6cb0',
        ]);
    }

    /**
     * Get tier comparison for upgrade recommendations.
     *
     * @param PartnerTier $currentTier
     * @return array<string, array<string, mixed>>
     */
    public function getTierComparison(PartnerTier $currentTier): array
    {
        $comparison = [];

        foreach (PartnerTier::cases() as $tier) {
            $comparison[$tier->value] = [
                'label'        => $tier->label(),
                'price'        => $tier->monthlyPrice(),
                'api_limit'    => $tier->apiCallLimit(),
                'rate_limit'   => $tier->rateLimitPerMinute(),
                'features'     => $tier->features(),
                'is_current'   => $tier->value === $currentTier->value,
                'is_upgrade'   => $this->isUpgrade($currentTier, $tier),
                'is_downgrade' => $this->isDowngrade($currentTier, $tier),
            ];
        }

        return $comparison;
    }

    /**
     * Check if moving to a tier is an upgrade.
     */
    private function isUpgrade(PartnerTier $from, PartnerTier $to): bool
    {
        $order = ['starter' => 1, 'growth' => 2, 'enterprise' => 3];

        return $order[$to->value] > $order[$from->value];
    }

    /**
     * Check if moving to a tier is a downgrade.
     */
    private function isDowngrade(PartnerTier $from, PartnerTier $to): bool
    {
        $order = ['starter' => 1, 'growth' => 2, 'enterprise' => 3];

        return $order[$to->value] < $order[$from->value];
    }
}
