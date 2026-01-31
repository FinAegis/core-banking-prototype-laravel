<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the AI infrastructure components including
    | LLM providers, vector databases, and conversation storage.
    |
    */

    'llm_provider' => env('AI_LLM_PROVIDER', 'openai'),

    'primary_provider' => env('AI_PRIMARY_PROVIDER', 'openai'),

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'anthropic'),

    'demo_mode' => env('AI_DEMO_MODE', true),

    'vector_db_provider' => env('AI_VECTOR_DB_PROVIDER', 'pinecone'),

    'auto_create_index' => env('AI_AUTO_CREATE_INDEX', false),

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    */
    'confidence_threshold' => env('AI_CONFIDENCE_THRESHOLD', 0.7),

    'high_confidence_threshold' => env('AI_HIGH_CONFIDENCE_THRESHOLD', 0.9),

    'low_confidence_threshold' => env('AI_LOW_CONFIDENCE_THRESHOLD', 0.5),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key'     => env('OPENAI_API_KEY'),
        'model'       => env('OPENAI_MODEL', 'gpt-4'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'max_tokens'  => env('OPENAI_MAX_TOKENS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude/Anthropic Configuration
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key'     => env('CLAUDE_API_KEY'),
        'model'       => env('CLAUDE_MODEL', 'claude-3-opus-20240229'),
        'temperature' => env('CLAUDE_TEMPERATURE', 0.7),
        'max_tokens'  => env('CLAUDE_MAX_TOKENS', 4000),
    ],

    'anthropic' => [
        'api_key'     => env('ANTHROPIC_API_KEY'),
        'model'       => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
        'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
        'max_tokens'  => env('ANTHROPIC_MAX_TOKENS', 4000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pinecone Configuration
    |--------------------------------------------------------------------------
    */
    'pinecone' => [
        'api_key'     => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT', 'us-east-1'),
        'index_name'  => env('PINECONE_INDEX_NAME', 'finaegis-ai'),
        'index_host'  => env('PINECONE_INDEX_HOST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Storage Configuration
    |--------------------------------------------------------------------------
    */
    'conversation' => [
        'ttl'          => env('AI_CONVERSATION_TTL', 86400), // 24 hours
        'max_per_user' => env('AI_MAX_CONVERSATIONS_PER_USER', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    */
    'agents' => [
        'customer_service' => [
            'enabled'              => env('AI_AGENT_CUSTOMER_SERVICE_ENABLED', true),
            'confidence_threshold' => 0.7,
        ],
        'compliance' => [
            'enabled'                => env('AI_AGENT_COMPLIANCE_ENABLED', true),
            'auto_approve_threshold' => 0.9,
        ],
        'risk' => [
            'enabled'         => env('AI_AGENT_RISK_ENABLED', true),
            'alert_threshold' => 0.8,
        ],
        'trading' => [
            'enabled'                => env('AI_AGENT_TRADING_ENABLED', true),
            'max_position_size'      => 10000,
            'require_approval_above' => 5000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Natural Language Processing Configuration
    |--------------------------------------------------------------------------
    */
    'nlp' => [
        'use_llm_fallback'            => env('AI_NLP_USE_LLM_FALLBACK', true),
        'confidence_for_llm_fallback' => env('AI_NLP_CONFIDENCE_FOR_LLM_FALLBACK', 0.6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Template Configuration
    |--------------------------------------------------------------------------
    */
    'prompts' => [
        'cache_ttl'     => env('AI_PROMPTS_CACHE_TTL', 3600),
        'seed_defaults' => env('AI_PROMPTS_SEED_DEFAULTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Limits & Quotas
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_tokens_per_request'        => env('AI_MAX_TOKENS_PER_REQUEST', 4000),
        'max_requests_per_user_per_day' => env('AI_MAX_REQUESTS_PER_USER_PER_DAY', 100),
        'max_cost_per_user_per_day_usd' => env('AI_MAX_COST_PER_USER_PER_DAY_USD', 10.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Human-in-the-Loop Configuration
    |--------------------------------------------------------------------------
    */
    'human_in_the_loop' => [
        'enabled'                   => env('AI_HUMAN_IN_THE_LOOP_ENABLED', true),
        'high_value_threshold_usd'  => env('AI_HIGH_VALUE_THRESHOLD_USD', 10000),
        'high_risk_score_threshold' => env('AI_HIGH_RISK_SCORE_THRESHOLD', 0.9),
    ],
];
