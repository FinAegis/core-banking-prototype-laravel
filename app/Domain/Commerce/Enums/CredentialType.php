<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Types of verifiable credentials that can be issued.
 */
enum CredentialType: string
{
    case KYC_VERIFICATION = 'kyc_verification';
    case ACCREDITATION = 'accreditation';
    case PROFESSIONAL = 'professional';
    case EDUCATIONAL = 'educational';
    case MEMBERSHIP = 'membership';
    case PAYMENT_HISTORY = 'payment_history';

    public function label(): string
    {
        return match ($this) {
            self::KYC_VERIFICATION => 'KYC Verification Credential',
            self::ACCREDITATION    => 'Accreditation Credential',
            self::PROFESSIONAL     => 'Professional Credential',
            self::EDUCATIONAL      => 'Educational Credential',
            self::MEMBERSHIP       => 'Membership Credential',
            self::PAYMENT_HISTORY  => 'Payment History Credential',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KYC_VERIFICATION => 'Verifiable credential proving KYC verification status',
            self::ACCREDITATION    => 'Credential for accredited investor or institution status',
            self::PROFESSIONAL     => 'Professional certification or license credential',
            self::EDUCATIONAL      => 'Educational qualification or certification',
            self::MEMBERSHIP       => 'Membership or subscription credential',
            self::PAYMENT_HISTORY  => 'Credential attesting to payment history or creditworthiness',
        };
    }

    /**
     * W3C Verifiable Credential context type.
     */
    public function vcType(): string
    {
        return match ($this) {
            self::KYC_VERIFICATION => 'KYCVerificationCredential',
            self::ACCREDITATION    => 'AccreditedInvestorCredential',
            self::PROFESSIONAL     => 'ProfessionalCredential',
            self::EDUCATIONAL      => 'EducationalCredential',
            self::MEMBERSHIP       => 'MembershipCredential',
            self::PAYMENT_HISTORY  => 'PaymentHistoryCredential',
        };
    }

    /**
     * Default validity period in days.
     */
    public function defaultValidityDays(): int
    {
        return match ($this) {
            self::KYC_VERIFICATION => 365,
            self::ACCREDITATION    => 365,
            self::PROFESSIONAL     => 365 * 2,
            self::EDUCATIONAL      => 0, // No expiry
            self::MEMBERSHIP       => 365,
            self::PAYMENT_HISTORY  => 90,
        };
    }

    /**
     * Whether this credential type supports selective disclosure.
     */
    public function supportsSelectiveDisclosure(): bool
    {
        return match ($this) {
            self::KYC_VERIFICATION => true,
            self::ACCREDITATION    => true,
            self::PROFESSIONAL     => true,
            self::EDUCATIONAL      => false,
            self::MEMBERSHIP       => true,
            self::PAYMENT_HISTORY  => true,
        };
    }
}
