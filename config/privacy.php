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

        // snarkjs CLI settings (for production ZK proving)
        'snarkjs_binary'          => env('SNARKJS_BINARY', 'snarkjs'),
        'circom_binary'           => env('CIRCOM_BINARY', 'circom'),
        'circuit_directory'       => env('SNARKJS_CIRCUIT_DIR', storage_path('app/circuits')),
        'snarkjs_timeout_seconds' => (int) env('SNARKJS_TIMEOUT', 120),
        'max_concurrent_proofs'   => (int) env('ZK_MAX_CONCURRENT_PROOFS', 3),

        // ProofType → circuit name mapping
        'circuit_mapping' => [
            'age_verification'    => env('ZK_CIRCUIT_AGE', 'age_check'),
            'residency'           => env('ZK_CIRCUIT_RESIDENCY', 'residency_check'),
            'kyc_tier'            => env('ZK_CIRCUIT_KYC', 'kyc_tier_check'),
            'accredited_investor' => env('ZK_CIRCUIT_ACCREDITED', 'accredited_check'),
            'sanctions_clear'     => env('ZK_CIRCUIT_SANCTIONS', 'sanctions_check'),
            'income_range'        => env('ZK_CIRCUIT_INCOME', 'income_range_check'),
        ],

        // On-chain verifier contract addresses per circuit per network
        'verifier_contracts' => [
            'age_check' => [
                'ethereum' => env('ZK_VERIFIER_AGE_CHECK_ETH'),
                'polygon'  => env('ZK_VERIFIER_AGE_CHECK_POLYGON'),
            ],
            'residency_check' => [
                'ethereum' => env('ZK_VERIFIER_RESIDENCY_CHECK_ETH'),
                'polygon'  => env('ZK_VERIFIER_RESIDENCY_CHECK_POLYGON'),
            ],
            'kyc_tier_check' => [
                'ethereum' => env('ZK_VERIFIER_KYC_TIER_CHECK_ETH'),
                'polygon'  => env('ZK_VERIFIER_KYC_TIER_CHECK_POLYGON'),
            ],
            'sanctions_check' => [
                'ethereum' => env('ZK_VERIFIER_SANCTIONS_CHECK_ETH'),
                'polygon'  => env('ZK_VERIFIER_SANCTIONS_CHECK_POLYGON'),
            ],
            'income_range_check' => [
                'ethereum' => env('ZK_VERIFIER_INCOME_RANGE_CHECK_ETH'),
                'polygon'  => env('ZK_VERIFIER_INCOME_RANGE_CHECK_POLYGON'),
            ],
        ],

        // Trusted setup ceremony configuration
        'ceremony' => [
            'ptau_power' => env('ZK_PTAU_POWER', 14),
            'ptau_url'   => env('ZK_PTAU_URL', 'https://hermez.s3-eu-west-1.amazonaws.com/powersOfTau28_hez_final_14.ptau'),
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

    /*
    |--------------------------------------------------------------------------
    | Merkle Tree Settings (v2.6.0)
    |--------------------------------------------------------------------------
    |
    | Configuration for privacy pool Merkle tree synchronization.
    |
    */

    'merkle' => [
        // Default provider for Merkle tree operations
        'provider' => env('MERKLE_PROVIDER', 'demo'),

        // Hash algorithm for Merkle tree: 'sha3-256' or 'poseidon'
        'hash_algorithm' => env('MERKLE_HASH_ALGORITHM', 'sha3-256'),

        // Sync interval in seconds
        'sync_interval_seconds' => (int) env('MERKLE_SYNC_INTERVAL', 30),

        // Maximum tree depth (32 = ~4 billion leaves)
        'max_tree_depth' => (int) env('MERKLE_TREE_DEPTH', 32),

        // Supported networks for privacy pools
        'networks' => ['ethereum', 'polygon', 'arbitrum', 'bsc'],

        // Contract addresses per network
        'pool_addresses' => [
            'ethereum' => env('MERKLE_POOL_ETHEREUM'),
            'polygon'  => env('MERKLE_POOL_POLYGON'),
            'arbitrum' => env('MERKLE_POOL_ARBITRUM'),
            'bsc'      => env('MERKLE_POOL_BSC'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delegated Proof Generation (v2.6.0)
    |--------------------------------------------------------------------------
    |
    | Server-side ZK proof generation for low-end mobile devices.
    |
    */

    'delegated_proving' => [
        // Enable delegated proof generation
        'enabled' => env('DELEGATED_PROVING_ENABLED', true),

        // Maximum pending jobs per user
        'max_queue_size' => env('DELEGATED_PROVING_MAX_QUEUE', 100),

        // Job timeout in seconds
        'timeout_seconds' => env('DELEGATED_PROVING_TIMEOUT', 300),

        // Minimum device RAM to recommend client-side proving (MB)
        'min_device_ram_mb' => 2048,

        // Queue name for proof generation jobs
        'queue_name' => env('DELEGATED_PROVING_QUEUE', 'proofs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SRS (Structured Reference String) Settings (v2.6.0)
    |--------------------------------------------------------------------------
    |
    | Configuration for ZK circuit SRS files required for mobile proof generation.
    | SRS files are cryptographic parameters needed to generate ZK proofs.
    |
    */

    'srs' => [
        // CDN base URL for SRS file downloads
        'cdn_base_url' => env('SRS_CDN_URL', 'https://cdn.finaegis.com/srs'),

        // Current SRS version (used for cache invalidation)
        'version' => '1.0.0',

        // Available circuits and their metadata
        'circuits' => [
            'shield_1_1' => [
                'size'     => 15000000,  // ~15 MB
                'required' => true,
                'checksum' => null,  // Will be computed from version if not set
            ],
            'unshield_2_1' => [
                'size'     => 12000000,  // ~12 MB
                'required' => true,
                'checksum' => null,
            ],
            'transfer_2_2' => [
                'size'     => 20000000,  // ~20 MB
                'required' => false,  // Optional for basic operations
                'checksum' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RAILGUN Privacy Protocol Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for the RAILGUN SDK bridge service.
    | The bridge is a Node.js process wrapping @railgun-community/wallet.
    | Supported chains: Ethereum, Polygon, Arbitrum, BSC (NOT Base).
    |
    */

    'railgun' => [
        'bridge_url'     => env('RAILGUN_BRIDGE_URL', 'http://127.0.0.1:3100'),
        'bridge_secret'  => env('RAILGUN_BRIDGE_SECRET'),
        'bridge_timeout' => (int) env('RAILGUN_BRIDGE_TIMEOUT', 30),
        'networks'       => ['ethereum', 'polygon', 'arbitrum', 'bsc'],

        /*
        | Non-custodial on-device engine bootstrap (Phase 1).
        |
        | Served verbatim to the mobile app by GET /api/v1/privacy/engine-config
        | so the device can configure startRailgunEngine + loadProvider against
        | OUR self-hosted infra (POI node, artifact mirror, RPC). The device holds
        | all keys; these are only public infra endpoints. RPC URLs MUST be
        | key-safe (a proxy or keyless endpoint) — never embed a provider API key.
        */
        'engine' => [
            // walletSource: <=16 chars, lowercase (RAILGUN constraint).
            'wallet_source'        => env('RAILGUN_WALLET_SOURCE', 'zelta'),
            'txid_version'         => 'V2_PoseidonMerkle',
            'use_native_artifacts' => true, // TRUE for mobile (native Groth16 prover)
            'artifact_base_url'    => (string) env('RAILGUN_ARTIFACT_BASE_URL', ''),
            'poi_node_urls'        => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('RAILGUN_POI_NODE_URLS', '')),
            ))),
            'broadcaster_enabled' => (bool) env('RAILGUN_BROADCASTER_ENABLED', true),
            // Device-facing per-network RPC URLs (key-safe: proxy or keyless).
            // Used only when no rpc_upstream proxy is configured for the network.
            'rpc_urls' => [
                'ethereum' => (string) env('RAILGUN_RPC_ETHEREUM', ''),
                'polygon'  => (string) env('RAILGUN_RPC_POLYGON', ''),
                'arbitrum' => (string) env('RAILGUN_RPC_ARBITRUM', ''),
                'bsc'      => (string) env('RAILGUN_RPC_BSC', ''),
            ],

            /*
            | Built-in RPC proxy (preferred over rpc_urls). When an upstream is set
            | for a network, engine-config returns a short-lived SIGNED proxy URL
            | (POST /api/v1/privacy/rpc/{network}) instead of a client RPC URL, and
            | the proxy injects the key server-side — so a provider API key NEVER
            | reaches the device. These upstream URLs are server-side only and may
            | safely contain the key. The SDK's loadProvider takes a URL string
            | (no header injection), which is why we use signed URLs for auth.
            */
            'rpc_upstream' => [
                'ethereum' => (string) env('RAILGUN_RPC_UPSTREAM_ETHEREUM', ''),
                'polygon'  => (string) env('RAILGUN_RPC_UPSTREAM_POLYGON', ''),
                'arbitrum' => (string) env('RAILGUN_RPC_UPSTREAM_ARBITRUM', ''),
                'bsc'      => (string) env('RAILGUN_RPC_UPSTREAM_BSC', ''),
            ],
            // TTL (seconds) for the signed proxy URL; the app refetches engine-config
            // when a proxied RPC call returns 403 (expired/invalid signature). The
            // URL is a replayable capability token within its TTL, so keep it short
            // — engine-config hard-caps it to [60, 900].
            'rpc_proxy_ttl' => (int) env('RAILGUN_RPC_PROXY_TTL', 300),
            // Per-user (signed `u`) request budget for the RPC proxy, per minute.
            'rpc_proxy_per_minute' => (int) env('RAILGUN_RPC_PROXY_PER_MINUTE', 120),
        ],
    ],
];
