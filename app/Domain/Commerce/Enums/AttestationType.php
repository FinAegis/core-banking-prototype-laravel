<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Types of attestations that can be issued for commerce transactions.
 */
enum AttestationType: string
{
    case PAYMENT = 'payment';
    case DELIVERY = 'delivery';
    case RECEIPT = 'receipt';
    case IDENTITY = 'identity';
    case WARRANTY = 'warranty';
    case MEMBERSHIP = 'membership';

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT    => 'Payment Attestation',
            self::DELIVERY   => 'Delivery Attestation',
            self::RECEIPT    => 'Receipt Attestation',
            self::IDENTITY   => 'Identity Attestation',
            self::WARRANTY   => 'Warranty Attestation',
            self::MEMBERSHIP => 'Membership Attestation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PAYMENT    => 'Cryptographic proof that a payment was made',
            self::DELIVERY   => 'Proof of goods or service delivery',
            self::RECEIPT    => 'Digital receipt for a transaction',
            self::IDENTITY   => 'Attestation of verified identity',
            self::WARRANTY   => 'Proof of warranty coverage',
            self::MEMBERSHIP => 'Proof of membership or subscription',
        };
    }

    /**
     * Required claims for this attestation type.
     *
     * @return array<string>
     */
    public function requiredClaims(): array
    {
        return match ($this) {
            self::PAYMENT    => ['amount', 'currency', 'payer_id', 'recipient_id', 'timestamp'],
            self::DELIVERY   => ['item_id', 'recipient_id', 'delivery_timestamp', 'location'],
            self::RECEIPT    => ['transaction_id', 'amount', 'merchant_id', 'timestamp'],
            self::IDENTITY   => ['subject_id', 'verified_attributes', 'verification_level'],
            self::WARRANTY   => ['product_id', 'start_date', 'end_date', 'coverage_type'],
            self::MEMBERSHIP => ['member_id', 'tier', 'start_date', 'expiry_date'],
        };
    }

    public function defaultValidityDays(): int
    {
        return match ($this) {
            self::PAYMENT    => 365 * 7, // 7 years for financial records
            self::DELIVERY   => 365,
            self::RECEIPT    => 365 * 7,
            self::IDENTITY   => 365,
            self::WARRANTY   => 0, // Validity is in the attestation itself
            self::MEMBERSHIP => 0, // Validity is in the attestation itself
        };
    }
}
