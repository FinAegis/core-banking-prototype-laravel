<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Soulbound Token Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for soulbound token (SBT) issuance and management.
    |
    */

    'soulbound_tokens' => [
        // Default issuer ID for tokens
        'issuer_id' => env('SBT_ISSUER_ID', 'finaegis-issuer'),

        // Default validity period in days
        'default_validity_days' => env('SBT_DEFAULT_VALIDITY_DAYS', 365),

        // Token types that are enabled
        'enabled_types' => [
            'soulbound'     => true,
            'transferable'  => true,
            'semi_fungible' => true,
            'fungible'      => true,
        ],

        // Revocation settings
        'revocation' => [
            'enabled'   => true,
            'log_audit' => env('SBT_LOG_AUDIT', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant Onboarding Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for merchant onboarding and lifecycle management.
    |
    */

    'merchant' => [
        // Auto-approve merchants below this risk score
        'auto_approval_threshold' => env('MERCHANT_AUTO_APPROVAL_THRESHOLD', 0.3),

        // High-risk business categories requiring enhanced review
        'high_risk_categories' => [
            'gambling',
            'crypto',
            'adult',
            'weapons',
            'tobacco',
            'pharmaceuticals',
        ],

        // High-risk jurisdictions
        'high_risk_jurisdictions' => ['AF', 'KP', 'IR', 'SY', 'CU', 'VE'],

        // KYB verification requirements
        'kyb_requirements' => [
            'business_registration'  => true,
            'ownership_verification' => true,
            'beneficial_owners'      => true,
            'bank_account'           => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Attestation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment attestation services.
    |
    */

    'attestation' => [
        // Default issuer ID
        'issuer_id' => env('ATTESTATION_ISSUER_ID', 'finaegis-attestor'),

        // Enable on-chain anchoring
        'on_chain_anchoring' => env('ATTESTATION_ON_CHAIN', false),

        // Supported attestation types
        'enabled_types' => [
            'payment'    => true,
            'delivery'   => true,
            'receipt'    => true,
            'identity'   => true,
            'warranty'   => true,
            'membership' => true,
        ],

        // Validity periods (in days) by type
        'validity_days' => [
            'payment'    => 365 * 7, // 7 years for financial records
            'delivery'   => 365,
            'receipt'    => 365 * 7,
            'identity'   => 365,
            'warranty'   => 0, // Determined by attestation
            'membership' => 0, // Determined by attestation
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verifiable Credential Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for W3C Verifiable Credential issuance.
    |
    */

    'credentials' => [
        // Issuer DID
        'issuer_did' => env('VC_ISSUER_DID', 'did:finaegis:issuer'),

        // Revocation list URL
        'revocation_list_url' => env('VC_REVOCATION_URL', 'https://finaegis.example/revocation'),

        // Supported credential types
        'enabled_types' => [
            'kyc_verification' => true,
            'accreditation'    => true,
            'professional'     => true,
            'educational'      => true,
            'membership'       => true,
            'payment_history'  => true,
        ],

        // Default validity periods (in days) by type
        'validity_days' => [
            'kyc_verification' => 365,
            'accreditation'    => 365,
            'professional'     => 365 * 2,
            'educational'      => 0, // No expiry
            'membership'       => 365,
            'payment_history'  => 90,
        ],

        // Additional W3C contexts
        'additional_contexts' => [
            'https://w3id.org/citizenship/v1',
            'https://w3id.org/security/suites/ed25519-2020/v1',
        ],
    ],
];
