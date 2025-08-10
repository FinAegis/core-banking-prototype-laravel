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

    'vector_db_provider' => env('AI_VECTOR_DB_PROVIDER', 'pinecone'),

    'auto_create_index' => env('AI_AUTO_CREATE_INDEX', false),

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
    | Claude Configuration
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key'     => env('CLAUDE_API_KEY'),
        'model'       => env('CLAUDE_MODEL', 'claude-3-opus-20240229'),
        'temperature' => env('CLAUDE_TEMPERATURE', 0.7),
        'max_tokens'  => env('CLAUDE_MAX_TOKENS', 4000),
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
];
