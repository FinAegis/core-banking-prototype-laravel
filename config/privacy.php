<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Zero-Knowledge Proof Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for zero-knowledge proof generation and verification.
    |
    */

    'zk' => [
        // Default provider for ZK proofs
        'provider' => env('ZK_PROVIDER', 'demo'),

        // Proof validity duration in days
        'proof_validity_days' => env('ZK_PROOF_VALIDITY_DAYS', 90),

        // Circuit versions for different proof types
        'circuit_versions' => [
            'age_verification'    => '1.0.0',
            'residency'           => '1.0.0',
            'kyc_tier'            => '1.0.0',
            'accredited_investor' => '1.0.0',
            'sanctions_clear'     => '1.0.0',
            'income_range'        => '1.0.0',
        ],

        // Verifier contract addresses (for on-chain verification)
        'verifier_addresses' => [
            'age_verification' => env('ZK_VERIFIER_AGE', '0x0000000000000000000000000000000000000000'),
            'residency'        => env('ZK_VERIFIER_RESIDENCY', '0x0000000000000000000000000000000000000000'),
            'kyc_tier'         => env('ZK_VERIFIER_KYC', '0x0000000000000000000000000000000000000000'),
            'sanctions_clear'  => env('ZK_VERIFIER_SANCTIONS', '0x0000000000000000000000000000000000000000'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Selective Disclosure Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for selective disclosure of credentials.
    |
    */

    'selective_disclosure' => [
        // Maximum number of claims per disclosure request
        'max_claims_per_request' => 10,

        // Enable audit logging of disclosures
        'audit_logging' => env('PRIVACY_AUDIT_LOGGING', true),

        // Retention period for disclosure logs (days)
        'log_retention_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Proof of Innocence Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for compliance-friendly privacy proofs.
    |
    */

    'proof_of_innocence' => [
        // Renewal threshold in days (renew if proof expires within this period)
        'renewal_threshold_days' => env('POI_RENEWAL_THRESHOLD', 30),

        // Maximum transaction history depth for proof generation
        'max_transaction_history' => 1000,

        // Sanctions list providers
        'sanctions_providers' => [
            'ofac' => [
                'enabled' => true,
                'api_url' => env('OFAC_API_URL', 'https://api.ofac.treasury.gov'),
            ],
            'eu' => [
                'enabled' => true,
                'api_url' => env('EU_SANCTIONS_API_URL', 'https://webgate.ec.europa.eu/europeaid/fsd'),
            ],
            'un' => [
                'enabled' => true,
                'api_url' => env('UN_SANCTIONS_API_URL', 'https://api.un.org/sanctions'),
            ],
        ],

        // Merkle tree depth for sanctions list
        'merkle_tree_depth' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy Pool Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for privacy-preserving payment pools (RAILGUN-style).
    |
    */

    'privacy_pools' => [
        'enabled' => env('PRIVACY_POOLS_ENABLED', false),

        // Supported chains
        'chains' => [
            'ethereum' => [
                'enabled'      => true,
                'pool_address' => env('PRIVACY_POOL_ETH', null),
            ],
            'polygon' => [
                'enabled'      => true,
                'pool_address' => env('PRIVACY_POOL_POLYGON', null),
            ],
        ],

        // Broadcaster network settings
        'broadcasters' => [
            'enabled' => env('PRIVACY_BROADCASTERS_ENABLED', false),
            'min_fee' => '0.01', // ETH
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regulatory Compliance
    |--------------------------------------------------------------------------
    |
    | Settings for regulatory compliance in privacy features.
    |
    */

    'compliance' => [
        // Require proof of innocence for transactions above this threshold (USD)
        'poi_threshold_usd' => env('POI_THRESHOLD_USD', 10000),

        // Enable human-in-the-loop for high-risk privacy operations
        'human_review_enabled' => env('PRIVACY_HUMAN_REVIEW', true),

        // Risk score threshold for automatic approval (0-1)
        'auto_approval_threshold' => 0.3,
    ],
];
