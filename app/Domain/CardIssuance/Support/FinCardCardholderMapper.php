<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Support;

/**
 * Maps validated mobile onboarding input (snake_case) into FinCard's
 * Cardholder-V2 request shape (camelCase).
 *
 * Isolated here because FinCard's exact field names come from their docs
 * (docs.finhub.cloud/paas/fincard-virtual/card-holders) and are pending
 * confirmation against a live sandbox response — when they change, this is
 * the ONE place to adjust. `idType` values are FinCard's enum
 * (PASSPORT | DLN | GOVERNMENT_ISSUED_ID_CARD).
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §2.3
 */
final class FinCardCardholderMapper
{
    /** ID document types FinCard accepts (non-HK regions). */
    public const ID_TYPES = ['PASSPORT', 'DLN', 'GOVERNMENT_ISSUED_ID_CARD'];

    /**
     * ISO-3166 alpha-2 countries FinCard does not support for issuance (per
     * their card-holders docs). We fail fast on these before calling FinCard.
     */
    public const RESTRICTED_COUNTRIES = [
        'CU', 'KP', 'EG', 'IR', 'MM', 'NG', 'RU', 'BY', 'ZA', 'SY', 'UA',
        'VE', 'SD', 'SS', 'LY', 'BI', 'CF', 'SO', 'ZW', 'AF',
    ];

    public static function isRestrictedCountry(string $iso2): bool
    {
        return in_array(strtoupper($iso2), self::RESTRICTED_COUNTRIES, true);
    }

    /** The subset of KYC fields persisted (encrypted) locally for support/audit. */
    public const PERSISTED_KEYS = [
        'gender', 'birthday', 'nationality', 'occupation', 'account_purpose',
        'id_type', 'issue_date', 'id_expiry_date',
    ];

    /**
     * @param  array<string, mixed>  $data  validated request data (snake_case)
     * @return array<string, mixed>  FinCard createCardholder payload (camelCase)
     */
    public static function toPayload(array $data, string $ipAddress): array
    {
        return array_filter([
            'firstName'             => $data['first_name'] ?? null,
            'lastName'              => $data['last_name'] ?? null,
            'gender'                => $data['gender'] ?? null,
            'birthday'              => $data['birthday'] ?? null,
            'nationality'           => $data['nationality'] ?? null,
            'occupation'            => $data['occupation'] ?? null,
            'annualSalary'          => $data['annual_salary'] ?? null,
            'expectedMonthlyVolume' => $data['expected_monthly_volume'] ?? null,
            'accountPurpose'        => $data['account_purpose'] ?? null,
            'phone'                 => $data['phone'] ?? null,
            'phoneCountryCode'      => $data['phone_country_code'] ?? null,
            'email'                 => $data['email'] ?? null,
            'country'               => $data['country'] ?? null,
            'state'                 => $data['state'] ?? null,
            'city'                  => $data['city'] ?? null,
            'address'               => $data['address'] ?? null,
            'zipCode'               => $data['zip_code'] ?? null,
            'idType'                => $data['id_type'] ?? null,
            'idNumber'              => $data['id_number'] ?? null,
            'issueDate'             => $data['issue_date'] ?? null,
            'idNoExpiryDate'        => $data['id_expiry_date'] ?? null,
            'idFrontId'             => $data['id_front_file_id'] ?? null,
            'idBackId'              => $data['id_back_file_id'] ?? null,
            'idHoldId'              => $data['id_selfie_file_id'] ?? null,
            'ipAddress'             => $ipAddress,
        ], static fn ($v): bool => $v !== null && $v !== '');
    }

    /**
     * The non-sensitive subset kept in the cardholder's encrypted
     * verification_data blob (never ID numbers or file ids).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function persistedAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(self::PERSISTED_KEYS));
    }
}
