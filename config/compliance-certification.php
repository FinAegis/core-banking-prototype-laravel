<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Compliance Certification Configuration (v3.5.0)
    |--------------------------------------------------------------------------
    |
    | Master configuration for compliance certification across SOC 2 Type II,
    | PCI DSS, data residency, GDPR, and security scanning. Each section
    | governs a distinct compliance domain and its operational parameters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | SOC 2 Type II Settings
    |--------------------------------------------------------------------------
    |
    | Service Organization Control 2 Type II audit configuration. Controls
    | evidence collection, review cadence, and privileged access governance.
    |
    */

    'soc2' => [
        // When enabled, SOC 2 checks run in demonstration mode (fabricated audit
        // data). Defaults to FALSE — must never be on in production; the evidence
        // collector throws in prod and ops:verify-env blocks the deploy.
        'demo_mode' => env('SOC2_DEMO_MODE', false),

        // Number of days to retain audit evidence artifacts
        'evidence_retention_days' => (int) env('SOC2_EVIDENCE_RETENTION_DAYS', 365),

        // Periodic review cadence: daily, weekly, monthly, quarterly, annually
        'review_period' => env('SOC2_REVIEW_PERIOD', 'quarterly'),

        // Roles considered privileged for SOC 2 access-control trust criteria
        'privileged_roles' => [
            'super_admin',
            'compliance_officer',
            'security_admin',
            'database_admin',
            'infrastructure_admin',
        ],

        // Trust service criteria to evaluate
        'trust_criteria' => [
            'security',
            'availability',
            'processing_integrity',
            'confidentiality',
            'privacy',
        ],

        // Continuous monitoring toggle
        'continuous_monitoring' => env('SOC2_CONTINUOUS_MONITORING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PCI DSS Settings
    |--------------------------------------------------------------------------
    |
    | Payment Card Industry Data Security Standard configuration. Manages
    | data classification, cryptographic key rotation, and network controls.
    |
    */

    'pci_dss' => [
        // Data classification levels ordered from least to most sensitive
        'classification_levels' => [
            'public' => [
                'label'          => 'Public',
                'encryption'     => false,
                'access_logging' => false,
                'retention_days' => null,
            ],
            'internal' => [
                'label'          => 'Internal',
                'encryption'     => false,
                'access_logging' => true,
                'retention_days' => 730, // 2 years
            ],
            'confidential' => [
                'label'          => 'Confidential',
                'encryption'     => true,
                'access_logging' => true,
                'retention_days' => 365,
            ],
            'restricted' => [
                'label'          => 'Restricted',
                'encryption'     => true,
                'access_logging' => true,
                'retention_days' => 90,
            ],
        ],

        // Cryptographic key rotation policy
        'key_rotation' => [
            'interval_days'      => (int) env('PCI_KEY_ROTATION_INTERVAL_DAYS', 90),
            'auto_rotate'        => env('PCI_KEY_AUTO_ROTATE', true),
            'algorithm'          => env('PCI_KEY_ALGORITHM', 'AES-256-GCM'),
            'notify_before_days' => (int) env('PCI_KEY_ROTATION_NOTIFY_DAYS', 14),
        ],

        // Network segmentation controls
        'network_segmentation' => [
            'enabled'             => env('PCI_NETWORK_SEGMENTATION_ENABLED', true),
            'cde_network'         => env('PCI_CDE_NETWORK', '10.0.1.0/24'), // Cardholder data environment
            'dmz_network'         => env('PCI_DMZ_NETWORK', '10.0.2.0/24'),
            'internal_network'    => env('PCI_INTERNAL_NETWORK', '10.0.3.0/24'),
            'firewall_rules_path' => env('PCI_FIREWALL_RULES_PATH', storage_path('compliance/pci/firewall-rules.json')),
            'verify_segmentation' => env('PCI_VERIFY_SEGMENTATION', true),
        ],

        // PCI DSS compliance level (1-4)
        'compliance_level' => (int) env('PCI_COMPLIANCE_LEVEL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Residency (Multi-Region)
    |--------------------------------------------------------------------------
    |
    | Controls where data is stored geographically to meet sovereignty
    | requirements. Each region specifies its storage backend, database
    | connection, and timezone for audit timestamps.
    |
    */

    'data_residency' => [
        'enabled' => env('DATA_RESIDENCY_ENABLED', false),

        // Available data residency regions
        'regions' => [
            'EU' => [
                'display_name'  => 'European Union',
                'storage_disk'  => env('DATA_RESIDENCY_EU_DISK', 'eu-s3'),
                'db_connection' => env('DATA_RESIDENCY_EU_DB', 'mysql-eu'),
                'timezone'      => 'Europe/Frankfurt',
            ],
            'US' => [
                'display_name'  => 'United States',
                'storage_disk'  => env('DATA_RESIDENCY_US_DISK', 'us-s3'),
                'db_connection' => env('DATA_RESIDENCY_US_DB', 'mysql-us'),
                'timezone'      => 'America/New_York',
            ],
            'APAC' => [
                'display_name'  => 'Asia-Pacific',
                'storage_disk'  => env('DATA_RESIDENCY_APAC_DISK', 'apac-s3'),
                'db_connection' => env('DATA_RESIDENCY_APAC_DB', 'mysql-apac'),
                'timezone'      => 'Asia/Singapore',
            ],
            'UK' => [
                'display_name'  => 'United Kingdom',
                'storage_disk'  => env('DATA_RESIDENCY_UK_DISK', 'uk-s3'),
                'db_connection' => env('DATA_RESIDENCY_UK_DB', 'mysql-uk'),
                'timezone'      => 'Europe/London',
            ],
        ],

        // Fallback region when no region is specified or matched
        'default_region' => env('DATA_RESIDENCY_DEFAULT_REGION', 'EU'),

        // Cross-region data transfer controls
        'cross_region_transfer' => [
            'require_approval' => env('DATA_RESIDENCY_REQUIRE_TRANSFER_APPROVAL', true),
            'log_transfers'    => env('DATA_RESIDENCY_LOG_TRANSFERS', true),
            'allowed_pairs'    => [
                // Pairs of regions that may transfer data between each other
                ['EU', 'UK'],
                ['US', 'APAC'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Settings
    |--------------------------------------------------------------------------
    |
    | General Data Protection Regulation configuration. Governs breach
    | notification timelines, consent management, data retention defaults,
    | and the format for data-subject register exports.
    |
    */

    'gdpr' => [
        // Maximum hours allowed to notify the supervisory authority after a breach
        'breach_notification_deadline_hours' => (int) env('GDPR_BREACH_NOTIFICATION_HOURS', 72),

        // Consent purposes that require explicit opt-in from data subjects
        'consent_purposes' => [
            'marketing_emails' => [
                'label'       => 'Marketing Communications',
                'description' => 'Receive promotional emails and product updates',
                'required'    => false,
            ],
            'analytics' => [
                'label'       => 'Analytics & Tracking',
                'description' => 'Allow usage analytics to improve our services',
                'required'    => false,
            ],
            'third_party_sharing' => [
                'label'       => 'Third-Party Data Sharing',
                'description' => 'Share anonymized data with trusted partners',
                'required'    => false,
            ],
            'transaction_processing' => [
                'label'       => 'Transaction Processing',
                'description' => 'Process financial transactions on your behalf',
                'required'    => true,
            ],
            'kyc_aml' => [
                'label'       => 'KYC/AML Verification',
                'description' => 'Verify identity for regulatory compliance',
                'required'    => true,
            ],
        ],

        // Default data retention periods by data category (in days)
        'retention_defaults' => [
            'personal_data'    => (int) env('GDPR_RETENTION_PERSONAL_DAYS', 1095),      // 3 years
            'transaction_data' => (int) env('GDPR_RETENTION_TRANSACTION_DAYS', 2555),    // 7 years
            'audit_logs'       => (int) env('GDPR_RETENTION_AUDIT_DAYS', 2555),          // 7 years
            'session_data'     => (int) env('GDPR_RETENTION_SESSION_DAYS', 90),
            'consent_records'  => (int) env('GDPR_RETENTION_CONSENT_DAYS', 1825),        // 5 years
            'marketing_data'   => (int) env('GDPR_RETENTION_MARKETING_DAYS', 365),
        ],

        // Format for ROPA (Records of Processing Activities) and DSAR exports
        'register_export_format' => env('GDPR_EXPORT_FORMAT', 'json'),

        // Data subject request handling
        'dsar_response_days' => (int) env('GDPR_DSAR_RESPONSE_DAYS', 30),

        // Data Protection Officer contact
        'dpo_email' => env('GDPR_DPO_EMAIL', 'dpo@finaegis.org'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Scanning
    |--------------------------------------------------------------------------
    |
    | Configuration for automated security scanning pipelines including SAST,
    | DAST, container image scanning, dependency auditing, and scheduling.
    |
    */

    'scanning' => [
        // Static Application Security Testing (SAST)
        'sast' => [
            'enabled' => env('SCANNING_SAST_ENABLED', true),

            // PHP functions considered unsafe — flagged during static analysis
            'unsafe_functions' => [
                'eval',
                'exec',
                'passthru',
                'shell_exec',
                'system',
                'proc_open',
                'popen',
                'assert',
                'preg_replace',  // with /e modifier
                'create_function',
                'call_user_func',
                'call_user_func_array',
                'unserialize',
                'extract',
                'parse_str',
            ],

            // Paths to include in SAST scanning
            'include_paths' => [
                'app/',
                'config/',
                'routes/',
                'database/',
            ],

            // Paths to exclude from SAST scanning
            'exclude_paths' => [
                'vendor/',
                'node_modules/',
                'storage/',
            ],
        ],

        // Dynamic Application Security Testing (DAST)
        'dast' => [
            'enabled'              => env('SCANNING_DAST_ENABLED', true),
            'target_url'           => env('SCANNING_DAST_TARGET_URL', 'http://localhost:8000'),
            'profile'              => env('SCANNING_DAST_PROFILE', 'full'), // full, api-only, quick
            'auth_token'           => env('SCANNING_DAST_AUTH_TOKEN'),
            'max_duration_minutes' => (int) env('SCANNING_DAST_MAX_DURATION', 120),
        ],

        // Container image scanning
        'container' => [
            'enabled'            => env('SCANNING_CONTAINER_ENABLED', true),
            'dockerfile_path'    => env('SCANNING_CONTAINER_DOCKERFILE', 'Dockerfile'),
            'severity_threshold' => env('SCANNING_CONTAINER_SEVERITY', 'high'), // low, medium, high, critical
            'registries'         => [
                env('SCANNING_CONTAINER_REGISTRY', 'ghcr.io/finaegis'),
            ],
        ],

        // Dependency / Software Composition Analysis (SCA)
        'dependency' => [
            'enabled'    => env('SCANNING_DEPENDENCY_ENABLED', true),
            'auto_audit' => env('SCANNING_DEPENDENCY_AUTO_AUDIT', true),
            'lock_files' => [
                'composer.lock',
                'package-lock.json',
            ],
            'vulnerability_db' => env('SCANNING_VULN_DB', 'https://github.com/advisories'),
            'fail_on_severity' => env('SCANNING_DEPENDENCY_FAIL_SEVERITY', 'high'), // low, medium, high, critical
        ],

        // Scan scheduling
        'scheduling' => [
            'frequency'             => env('SCANNING_FREQUENCY', 'weekly'), // daily, weekly, biweekly, monthly
            'day_of_week'           => env('SCANNING_DAY_OF_WEEK', 'sunday'),
            'time'                  => env('SCANNING_TIME', '02:00'),
            'timezone'              => env('SCANNING_TIMEZONE', 'UTC'),
            'notify_on_findings'    => env('SCANNING_NOTIFY_ON_FINDINGS', true),
            'notification_channels' => ['email', 'slack'],
        ],
    ],

];
