<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;

/**
 * The onboarding step the app shows for a cardholder: cardholder, kyc,
 * kyc_review, kyc_action_required, kyc_rejected, funding_pending or ready.
 */
final class CardOnboardingSteps
{
    /** Steps in which the questionnaire answers can still be corrected. */
    public const CORRECTABLE = ['cardholder', 'kyc', 'kyc_action_required'];

    public static function for(?Cardholder $cardholder): string
    {
        if ($cardholder === null) {
            return 'cardholder';
        }

        return match ($cardholder->kyc_status) {
            'verified'        => self::fundingReady($cardholder->fundingAccount()->first()) ? 'ready' : 'funding_pending',
            'in_review'       => 'kyc_review',
            'action_required' => 'kyc_action_required',
            'rejected'        => 'kyc_rejected',
            default           => 'kyc',
        };
    }

    private static function fundingReady(?CardFundingAccount $account): bool
    {
        return $account !== null
            && $account->status === 'active'
            && $account->depositAddresses()->where('is_active', true)->exists();
    }
}
