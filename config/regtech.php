<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RegTech Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the RegTech automation components including
    | jurisdiction rules, regulatory API endpoints, and filing schedules.
    |
    */

    'enabled' => env('REGTECH_ENABLED', true),

    'demo_mode' => env('REGTECH_DEMO_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Jurisdiction Configuration
    |--------------------------------------------------------------------------
    | Rules and settings for different regulatory jurisdictions.
    */
    'jurisdictions' => [
        'US' => [
            'name'       => 'United States',
            'regulators' => [
                'fincen' => [
                    'name'     => 'Financial Crimes Enforcement Network',
                    'api_base' => env('FINCEN_API_URL', 'https://bsaefiling.fincen.treas.gov'),
                    'api_key'  => env('FINCEN_API_KEY'),
                    'reports'  => ['CTR', 'SAR', 'CMIR', 'FBAR'],
                    'timezone' => 'America/New_York',
                ],
                'sec' => [
                    'name'     => 'Securities and Exchange Commission',
                    'api_base' => env('SEC_API_URL', 'https://efiling.sec.gov'),
                    'api_key'  => env('SEC_API_KEY'),
                    'reports'  => ['13F', '10-K', '10-Q'],
                    'timezone' => 'America/New_York',
                ],
                'fdic' => [
                    'name'     => 'Federal Deposit Insurance Corporation',
                    'api_base' => env('FDIC_API_URL', 'https://efiling.fdic.gov'),
                    'api_key'  => env('FDIC_API_KEY'),
                    'reports'  => ['CALL'],
                    'timezone' => 'America/New_York',
                ],
            ],
            'currency'        => 'USD',
            'ctr_threshold'   => 10000, // Currency Transaction Report threshold
            'sar_review_days' => 30,    // SAR review period
        ],
        'EU' => [
            'name'       => 'European Union',
            'regulators' => [
                'esma' => [
                    'name'     => 'European Securities and Markets Authority',
                    'api_base' => env('ESMA_API_URL', 'https://firds.esma.europa.eu'),
                    'api_key'  => env('ESMA_API_KEY'),
                    'reports'  => ['MiFID_Transaction', 'EMIR', 'SFTR'],
                    'timezone' => 'Europe/Paris',
                ],
                'eba' => [
                    'name'     => 'European Banking Authority',
                    'api_base' => env('EBA_API_URL', 'https://euclid.eba.europa.eu'),
                    'api_key'  => env('EBA_API_KEY'),
                    'reports'  => ['COREP', 'FINREP', 'LCR'],
                    'timezone' => 'Europe/Paris',
                ],
            ],
            'currency'               => 'EUR',
            'mifid_t1_deadline'      => 1, // T+1 reporting for MiFID II
            'gdpr_data_request_days' => 30,
        ],
        'UK' => [
            'name'       => 'United Kingdom',
            'regulators' => [
                'fca' => [
                    'name'     => 'Financial Conduct Authority',
                    'api_base' => env('FCA_API_URL', 'https://gabriel.fca.org.uk'),
                    'api_key'  => env('FCA_API_KEY'),
                    'reports'  => ['MiFID_Transaction', 'REP-CRIM', 'SUP16'],
                    'timezone' => 'Europe/London',
                ],
                'pra' => [
                    'name'     => 'Prudential Regulation Authority',
                    'api_base' => env('PRA_API_URL', 'https://beeds.bankofengland.co.uk'),
                    'api_key'  => env('PRA_API_KEY'),
                    'reports'  => ['PRA_Returns'],
                    'timezone' => 'Europe/London',
                ],
            ],
            'currency' => 'GBP',
        ],
        'SG' => [
            'name'       => 'Singapore',
            'regulators' => [
                'mas' => [
                    'name'     => 'Monetary Authority of Singapore',
                    'api_base' => env('MAS_API_URL', 'https://eservices.mas.gov.sg'),
                    'api_key'  => env('MAS_API_KEY'),
                    'reports'  => ['MAS_Returns', 'STR'],
                    'timezone' => 'Asia/Singapore',
                ],
            ],
            'currency'      => 'SGD',
            'str_threshold' => 20000, // Suspicious Transaction Report threshold
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filing Schedules
    |--------------------------------------------------------------------------
    | Default filing schedules for different report types.
    */
    'filing_schedules' => [
        // US Reports
        'CTR' => [
            'frequency'    => 'transaction', // Per transaction over threshold
            'deadline'     => 15, // Days after transaction
            'jurisdiction' => 'US',
        ],
        'SAR' => [
            'frequency'    => 'event',
            'deadline'     => 30, // Days after detection
            'jurisdiction' => 'US',
        ],

        // EU/MiFID Reports
        'MiFID_Transaction' => [
            'frequency'    => 'daily',
            'deadline'     => 1, // T+1
            'jurisdiction' => ['EU', 'UK'],
        ],
        'EMIR' => [
            'frequency'    => 'daily',
            'deadline'     => 1, // T+1
            'jurisdiction' => 'EU',
        ],

        // Periodic Reports
        'COREP' => [
            'frequency'    => 'quarterly',
            'deadline'     => 30, // Days after quarter end
            'jurisdiction' => 'EU',
        ],
        'FINREP' => [
            'frequency'    => 'quarterly',
            'deadline'     => 30,
            'jurisdiction' => 'EU',
        ],
        'CALL' => [
            'frequency'    => 'quarterly',
            'deadline'     => 30,
            'jurisdiction' => 'US',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MiFID II Specific Settings
    |--------------------------------------------------------------------------
    */
    'mifid' => [
        'enabled'                   => env('MIFID_ENABLED', true),
        'arm_provider'              => env('MIFID_ARM_PROVIDER', 'internal'), // Approved Reporting Mechanism
        'best_execution_rts27'      => env('MIFID_RTS27_ENABLED', true),
        'best_execution_rts28'      => env('MIFID_RTS28_ENABLED', true),
        'transaction_threshold'     => 0, // Report all transactions
        'instrument_reference_data' => [
            'firds_enabled'    => true,
            'anna_dsb_enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MiCA (Markets in Crypto-Assets) Settings
    |--------------------------------------------------------------------------
    */
    'mica' => [
        'enabled'                 => env('MICA_ENABLED', true),
        'casp_authorization'      => env('MICA_CASP_AUTHORIZED', false),
        'whitepaper_requirements' => [
            'max_pages'         => 40,
            'required_sections' => [
                'issuer_info',
                'project_description',
                'token_mechanics',
                'rights_obligations',
                'risks',
                'technology',
                'environmental_impact',
            ],
        ],
        'reserve_management' => [
            'art_reserve_ratio'  => 1.0, // Asset-Referenced Tokens
            'emt_reserve_ratio'  => 1.0, // E-Money Tokens
            'audit_frequency'    => 'monthly',
            'custodian_required' => true,
        ],
        'travel_rule' => [
            'enabled'                   => true,
            'threshold_eur'             => 1000,
            'required_originator_info'  => ['name', 'address', 'account_number', 'doc_id'],
            'required_beneficiary_info' => ['name', 'account_number'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ML Model Configuration for Transaction Monitoring
    |--------------------------------------------------------------------------
    */
    'ml_monitoring' => [
        'enabled'           => env('REGTECH_ML_ENABLED', true),
        'model_version'     => env('REGTECH_ML_MODEL_VERSION', '1.0.0'),
        'scoring_threshold' => [
            'low'      => 0.3,
            'medium'   => 0.6,
            'high'     => 0.8,
            'critical' => 0.95,
        ],
        'feature_store' => [
            'enabled'    => true,
            'ttl_hours'  => 24,
            'batch_size' => 1000,
        ],
        'explainability' => [
            'enabled'      => true,
            'shap_enabled' => true,
            'lime_enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regulatory API Endpoints (Demo/Sandbox)
    |--------------------------------------------------------------------------
    */
    'api_endpoints' => [
        'fincen' => [
            'submit'  => env('FINCEN_SUBMIT_URL', 'https://sandbox.fincen.treas.gov/api/v1/reports'),
            'status'  => env('FINCEN_STATUS_URL', 'https://sandbox.fincen.treas.gov/api/v1/status'),
            'sandbox' => env('FINCEN_SANDBOX', true),
        ],
        'esma' => [
            'firds'   => env('ESMA_FIRDS_URL', 'https://sandbox.firds.esma.europa.eu/api/v1'),
            'trem'    => env('ESMA_TREM_URL', 'https://sandbox.trem.esma.europa.eu/api/v1'),
            'sandbox' => env('ESMA_SANDBOX', true),
        ],
        'fca' => [
            'gabriel' => env('FCA_GABRIEL_URL', 'https://sandbox.gabriel.fca.org.uk/api/v1'),
            'sandbox' => env('FCA_SANDBOX', true),
        ],
        'mas' => [
            'eservices' => env('MAS_ESERVICES_URL', 'https://sandbox.eservices.mas.gov.sg/api/v1'),
            'sandbox'   => env('MAS_SANDBOX', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regulatory Calendar
    |--------------------------------------------------------------------------
    | Key regulatory dates and deadlines.
    */
    'calendar' => [
        'holidays' => [
            'US' => ['01-01', '07-04', '12-25'], // Sample US holidays
            'EU' => ['01-01', '12-25', '12-26'],
            'UK' => ['01-01', '12-25', '12-26'],
        ],
        'quarter_ends'    => ['03-31', '06-30', '09-30', '12-31'],
        'fiscal_year_end' => '12-31',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'deadline_warning_days' => [7, 3, 1], // Days before deadline to send warnings
        'channels'              => ['email', 'database'],
        'escalation_contacts'   => env('REGTECH_ESCALATION_EMAILS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-Border Compliance
    |--------------------------------------------------------------------------
    */
    'cross_border' => [
        'enabled'              => true,
        'conflict_resolution'  => 'strictest', // strictest, primary_jurisdiction, manual
        'jurisdiction_mapping' => [
            'currencies' => [
                'USD' => 'US',
                'EUR' => 'EU',
                'GBP' => 'UK',
                'SGD' => 'SG',
            ],
        ],
    ],
];
