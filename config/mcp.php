<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('MCP_ENABLED', true),
    'host'    => env('MCP_HOST', 'mcp.zelta.app'),

    /*
    |--------------------------------------------------------------------------
    | Wire Protocol
    |--------------------------------------------------------------------------
    */
    'protocol_version' => '2025-11-25',
    'server_info'      => [
        'name'    => env('MCP_SERVER_NAME', 'Zelta'),
        'version' => env('MCP_SERVER_VERSION', '0.1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth & Discovery
    |--------------------------------------------------------------------------
    */
    'authorization_server' => env('MCP_AUTH_SERVER', 'https://zelta.app'),
    'resource_uri'         => env('MCP_RESOURCE_URI', 'https://mcp.zelta.app'),

    'scopes' => [
        'accounts:read'     => 'Read account profile and balances',
        'accounts:write'    => 'Create new accounts',
        'payments:read'     => 'Read payment status',
        'payments:write'    => 'Send payments (subject to spending limit)',
        'transactions:read' => 'Read transaction history and spending analysis',
        'exchange:read'     => 'Get exchange rate quotes',
        'exchange:write'    => 'Execute exchange trades (subject to spending limit)',
        'ramp:read'         => 'Read on/offramp session status',
        'ramp:write'        => 'Start on/offramp sessions (subject to spending limit)',
        'sms:send'          => 'Send SMS messages (paid per-message via x402)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Catalog (v1)
    |--------------------------------------------------------------------------
    | Maps public MCP tool names to internal MCPToolInterface tool names.
    | Disabled tools are omitted from tools/list AND return -32004 if invoked.
    */
    'tools' => [
        // `internal` values must match the registered tool's getName() (dotted format).
        // Ramp tools (ramp.start / ramp.status) are wired in Phase 2 Tasks 19-20; the
        // catalog references the names they will register as so tools/list lights up
        // automatically once those tools land.
        'account.balance'    => ['internal' => 'account.balance',                'scope' => 'accounts:read',     'enabled' => env('MCP_TOOL_ACCOUNT_BALANCE', true),     'is_write' => false],
        'account.create'     => ['internal' => 'account.create',                 'scope' => 'accounts:write',    'enabled' => env('MCP_TOOL_ACCOUNT_CREATE', true),      'is_write' => true],
        'payment.status'     => ['internal' => 'payment.status',                 'scope' => 'payments:read',     'enabled' => env('MCP_TOOL_PAYMENT_STATUS', true),      'is_write' => false],
        'payment.transfer'   => ['internal' => 'payment.transfer',               'scope' => 'payments:write',    'enabled' => env('MCP_TOOL_PAYMENT_TRANSFER', true),    'is_write' => true,  'is_payment' => true,  'amount_arg' => 'amount_minor',  'currency_arg' => 'currency'],
        'transactions.query' => ['internal' => 'transactions.query',             'scope' => 'transactions:read', 'enabled' => env('MCP_TOOL_TRANSACTIONS_QUERY', true),  'is_write' => false],
        'spending.analysis'  => ['internal' => 'transactions.spending_analysis', 'scope' => 'transactions:read', 'enabled' => env('MCP_TOOL_SPENDING_ANALYSIS', true),   'is_write' => false],
        'exchange.quote'     => ['internal' => 'exchange.quote',                 'scope' => 'exchange:read',     'enabled' => env('MCP_TOOL_EXCHANGE_QUOTE', true),      'is_write' => false],
        'exchange.trade'     => ['internal' => 'exchange.trade',                 'scope' => 'exchange:write',    'enabled' => env('MCP_TOOL_EXCHANGE_TRADE', true),      'is_write' => true,  'is_payment' => true,  'amount_arg' => 'amount_minor',  'currency_arg' => 'from_currency'],
        'ramp.start'         => ['internal' => 'ramp.start',                     'scope' => 'ramp:write',        'enabled' => env('MCP_TOOL_RAMP_START', true),          'is_write' => true],
        'ramp.status'        => ['internal' => 'ramp.status',                    'scope' => 'ramp:read',         'enabled' => env('MCP_TOOL_RAMP_STATUS', true),         'is_write' => false],
        'mpp.discovery'      => ['internal' => 'mpp.discovery',                  'scope' => null,                'enabled' => env('MCP_TOOL_MPP_DISCOVERY', true),       'is_write' => false],
        'sms.send'           => ['internal' => 'sms.send',                       'scope' => 'sms:send',          'enabled' => env('MCP_TOOL_SMS_SEND', true),            'is_write' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spending Limits (per-token defaults)
    |--------------------------------------------------------------------------
    */
    'spending' => [
        'default_daily_limit_minor'    => (int) env('MCP_DEFAULT_DAILY_LIMIT_MINOR', 50000),     // $500.00
        'default_daily_limit_currency' => (string) env('MCP_DEFAULT_DAILY_LIMIT_CURRENCY', 'USD'),
        'consent_options_minor'        => [5000, 50000, 200000, 1000000, null],                  // null = no limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'cache_store' => (string) env('MCP_IDEMPOTENCY_STORE', 'redis'),
        'ttl_seconds' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (per-token unless noted)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'aggregate'                   => ['per_minute' => 60, 'per_hour' => 600, 'per_day' => 5000],
        'reads_per_minute'            => 120,
        'writes_per_minute'           => 30,
        'sms_per_minute'              => 10,
        'discovery_per_minute_per_ip' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources (read-context primitives)
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'account://profile'            => ['scope' => 'accounts:read',     'enabled' => true],
        'account://balance/{currency}' => ['scope' => 'accounts:read',     'enabled' => true],
        'transactions://recent'        => ['scope' => 'transactions:read', 'enabled' => true],
        'transaction://{id}'           => ['scope' => 'transactions:read', 'enabled' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streamable HTTP transport
    |--------------------------------------------------------------------------
    | The MCP spec lets the server be POST-only and return 405 on GET when
    | SSE is unsupported. We default to that posture because a long-lived SSE
    | connection inside PHP-FPM pins a worker for the entire connection
    | lifetime — under load that exhausts the pool. Set MCP_SSE_ENABLED=true
    | only when running under Octane/Swoole or a dedicated SSE FPM pool.
    */
    'sse' => [
        'enabled'           => (bool) env('MCP_SSE_ENABLED', false),
        'heartbeat_seconds' => (int) env('MCP_SSE_HEARTBEAT_SECONDS', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Client Registration — branding policy
    |--------------------------------------------------------------------------
    | The DCR endpoint is open-by-throttle (RFC 7591 §1.1 allows this). To
    | block the most obvious phishing path — registering a client_name like
    | "Zelta Official" or "Stripe" to spoof a trusted brand on the consent
    | screen — we reject any client_name whose lowercase form contains any
    | of these reserved substrings. Operators can add brand keywords via
    | MCP_DCR_RESERVED_NAMES (comma-separated, case-insensitive).
    */
    'dcr' => [
        'reserved_name_substrings' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'MCP_DCR_RESERVED_NAMES',
                'zelta,finaegis,anthropic,claude,openai,gpt,stripe,paysera,visa,mastercard,official,admin,system,support,security',
            )),
        ))),
    ],
];
