@extends('layouts.public')

@section('title', 'API Documentation - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'API Documentation - ' . config('brand.name', 'Zelta'),
        'description' => 'Complete reference documentation for the ' . config('brand.name', 'Zelta') . ' REST API with interactive examples and code samples.',
        'keywords' => config('brand.name', 'Zelta') . ' API, REST API, API documentation, developer reference',
    ])
@endsection

@section('content')
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        API Documentation
                    </h1>
                    <p class="mt-6 text-xl text-gray-300 max-w-3xl mx-auto">
                        Complete reference documentation for the {{ config('brand.name', 'Zelta') }} REST API with interactive examples and code samples.
                    </p>
                </div>
            </div>
        </div>

        <!-- API Navigation -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex flex-wrap gap-x-6 gap-y-2 py-4">
                    <a href="#getting-started" class="text-blue-600 font-medium">Getting Started</a>
                    <a href="#authentication" class="text-gray-600 hover:text-gray-900">Authentication</a>
                    <a href="#accounts" class="text-gray-600 hover:text-gray-900">Accounts</a>
                    <a href="#transactions" class="text-gray-600 hover:text-gray-900">Transactions</a>
                    <a href="#transfers" class="text-gray-600 hover:text-gray-900">Transfers</a>
                    <a href="#gcu" class="text-gray-600 hover:text-gray-900">GCU</a>
                    <a href="#baskets" class="text-gray-600 hover:text-gray-900">Baskets</a>
                    <a href="#crosschain" class="text-cyan-600 hover:text-cyan-800">CrossChain</a>
                    <a href="#defi" class="text-emerald-600 hover:text-emerald-800">DeFi</a>
                    <a href="#regtech" class="text-amber-600 hover:text-amber-800">RegTech</a>
                    <a href="#mobile-payment" class="text-violet-600 hover:text-violet-800">Mobile Payment</a>
                    <a href="#partner-baas" class="text-rose-600 hover:text-rose-800">Partner BaaS</a>
                    <a href="#sms" class="text-orange-600 hover:text-orange-800">SMS</a>
                    <a href="#ai" class="text-gray-600 hover:text-gray-900">AI</a>
                    <a href="#graphql" class="text-sky-600 hover:text-sky-800">GraphQL</a>
                    <a href="#event-streaming" class="text-lime-600 hover:text-lime-800">Event Streaming</a>
                    <a href="#x402" class="text-emerald-600 hover:text-emerald-800">x402 Protocol</a>
                    <a href="#iso20022" class="text-blue-600 hover:text-blue-800">ISO 20022</a>
                    <a href="#open-banking" class="text-teal-600 hover:text-teal-800">Open Banking</a>
                    <a href="#payment-rails" class="text-orange-600 hover:text-orange-800">Payment Rails</a>
                    <a href="#interledger" class="text-purple-600 hover:text-purple-800">Interledger</a>
                    <a href="#ledger" class="text-slate-600 hover:text-slate-900">Ledger</a>
                    <a href="#microfinance" class="text-green-600 hover:text-green-800">Microfinance</a>
                    <a href="#webhooks" class="text-gray-600 hover:text-gray-900">Webhooks</a>
                    <a href="#rate-limits" class="text-gray-600 hover:text-gray-900">Rate Limits</a>
                    <a href="#errors" class="text-gray-600 hover:text-gray-900">Errors</a>
                </nav>
            </div>
        </div>

        <!-- OpenAPI Spec Banner -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-200 rounded-lg mb-8">
                <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <div>
                    <p class="font-semibold text-blue-900">OpenAPI 3.0 Specification</p>
                    <p class="text-sm text-blue-700">Import into Postman, Insomnia, or any OpenAPI-compatible tool.</p>
                </div>
                <a href="/api/documentation" target="_blank" class="ml-auto px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                    View Swagger UI &rarr;
                </a>
            </div>
        </div>

        <!-- API Documentation Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <section id="getting-started" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Getting Started</h2>
                        
                        <div class="prose prose-lg max-w-none">
                            <p>The {{ config('brand.name', 'Zelta') }} API provides programmatic access to our multi-asset banking platform spanning 56 DDD domains with over 1,400 routes. Our API is organized around REST principles with predictable, resource-oriented URLs. Domains include core banking, CrossChain bridging, DeFi protocols, RegTech compliance, Mobile Payment, Partner BaaS, ISO 20022, Open Banking, US Payment Rails, and AI-powered queries.</p>
                            
                            <h3>Base URL</h3>
                            <x-code-block language="plaintext">
{{ config('app.url') }}/api/v2
                            </x-code-block>
                            
                            <h3>Response Format</h3>
                            <p>All API responses are returned in JSON format with a consistent structure:</p>
                            
                            <x-code-block language="json">
{
  "data": { ... },          // Main response data
  "meta": { ... },          // Metadata (pagination, etc.)
  "links": { ... },         // Related links
  "errors": [ ... ]         // Error details (if any)
}
                            </x-code-block>
                        </div>
                    </section>

                    <section id="authentication" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Authentication</h2>
                        
                        <div class="prose prose-lg max-w-none">
                            <p>The {{ config('brand.name', 'Zelta') }} API uses API keys to authenticate requests. You can generate and manage your API keys in your dashboard.</p>
                            
                            <h3>Creating API Keys</h3>
                            <ol>
                                <li>Log in to your {{ config('brand.name', 'Zelta') }} account</li>
                                <li>Navigate to <a href="{{ route('api-keys.index') }}" class="text-blue-600 hover:text-blue-800">API Keys</a> in your dashboard</li>
                                <li>Click "Create New Key" and configure permissions</li>
                                <li>Copy the generated key immediately (it won't be shown again)</li>
                            </ol>
                            
                            <h3>API Key Authentication</h3>
                            <p>Include your API key in the Authorization header:</p>
                            
                            <x-code-block language="bash">
curl -H "Authorization: Bearer fak_your_api_key_here" \
     -H "Content-Type: application/json" \
     {{ config('app.url') }}/api/v2/accounts
                            </x-code-block>
                            
                            <h3>API Key Security</h3>
                            <ul>
                                <li><strong>Permissions:</strong> Grant only the minimum required permissions (read, write, delete)</li>
                                <li><strong>IP Whitelist:</strong> Restrict API key usage to specific IP addresses</li>
                                <li><strong>Expiration:</strong> Set expiration dates for temporary keys</li>
                                <li><strong>Rotation:</strong> Regularly rotate your API keys</li>
                                <li><strong>Storage:</strong> Never commit API keys to version control</li>
                            </ul>
                            
                            <h3>Sandbox vs Production</h3>
                            <div class="mb-2">
                                <span class="font-semibold text-slate-700">Base URL:</span>
                                <code class="bg-slate-100 px-2 py-1 rounded text-sm">{{ config('app.url') }}/api/v2</code>
                            </div>
                            <p class="text-sm text-slate-500">Sandbox and production use the same base URL. Your API key determines the environment. Sandbox keys start with <code>sk_sandbox_</code>, production keys start with <code>sk_live_</code>.</p>
                        </div>
                    </section>

                    <section id="accounts" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Accounts</h2>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Accounts</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/accounts</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Retrieve a list of all accounts for the authenticated user.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/accounts
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Account Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Retrieve detailed information about a specific account.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/accounts/acct_1234567890
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Account Balances</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/balances</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get current balances for all assets in an account.</p>
                                
                                <x-code-block language="json">
{
  "data": {
    "account_uuid": "acct_1234567890",
    "balances": [
      {
        "asset_code": "USD",
        "available_balance": "1500.00",
        "reserved_balance": "0.00",
        "total_balance": "1500.00"
      },
      {
        "asset_code": "EUR", 
        "available_balance": "1200.50",
        "reserved_balance": "50.00",
        "total_balance": "1250.50"
      }
    ],
    "summary": {
      "total_assets": 2,
      "total_usd_equivalent": "2,850.75"
    }
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="transactions" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Transactions</h2>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Transactions</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/transactions</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get a paginated list of all transactions.</p>
                                
                                <h4 class="font-semibold mb-2">Query Parameters:</h4>
                                <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                                    <li><code class="bg-gray-100 px-1">page</code> - Page number (default: 1)</li>
                                    <li><code class="bg-gray-100 px-1">per_page</code> - Items per page (default: 20, max: 100)</li>
                                    <li><code class="bg-gray-100 px-1">asset_code</code> - Filter by asset code</li>
                                    <li><code class="bg-gray-100 px-1">status</code> - Filter by status</li>
                                </ul>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/transactions?page=1&per_page=20"
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Deposit Funds</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/deposit</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Deposit funds into an account.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "500.00",
    "asset_code": "USD",
    "reference": "Initial deposit"
  }' \
  {{ config('app.url') }}/api/v2/accounts/acct_1234567890/deposit
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Withdraw Funds</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/withdraw</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Withdraw funds from an account.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "100.00",
    "asset_code": "USD",
    "reference": "ATM withdrawal"
  }' \
  {{ config('app.url') }}/api/v2/accounts/acct_1234567890/withdraw
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="transfers" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Transfers</h2>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Transfer</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/transfers</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Create a new transfer between accounts.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_account": "acct_1234567890",
    "to_account": "acct_0987654321", 
    "amount": "100.00",
    "asset_code": "USD",
    "reference": "Payment for services",
    "workflow_enabled": true
  }' \
  {{ config('app.url') }}/api/v2/transfers
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Transfer History</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/transfers</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get transfer history for a specific account.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/accounts/acct_1234567890/transfers
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="gcu" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Global Currency Unit (GCU)</h2>
                        
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The GCU endpoints provide access to real-time data about the Global Currency Unit, including its composition, value history, and governance information.</p>
                        </div>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get GCU Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get current information about the Global Currency Unit including composition and value.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/gcu
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Real-time GCU Composition</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/composition</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get detailed real-time composition data including current weights, values, and recent changes for each component asset.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/gcu/composition
                                </x-code-block>
                                
                                <h4 class="font-semibold mb-2">Response Example:</h4>
                                <x-code-block language="json">
{
  "data": {
    "basket_code": "GCU",
    "last_updated": "2024-01-15T10:30:00Z",
    "total_value_usd": 1.0975,
    "composition": [
      {
        "asset_code": "USD",
        "asset_name": "US Dollar",
        "asset_type": "fiat",
        "weight": 0.35,
        "current_price_usd": 1.0000,
        "value_contribution_usd": 0.3500,
        "percentage_of_basket": 31.89,
        "24h_change": 0.00,
        "7d_change": 0.00
      },
      {
        "asset_code": "EUR",
        "asset_name": "Euro", 
        "asset_type": "fiat",
        "weight": 0.30,
        "current_price_usd": 1.0850,
        "value_contribution_usd": 0.3255,
        "percentage_of_basket": 29.68,
        "24h_change": 0.15,
        "7d_change": -0.23
      }
    ],
    "rebalancing": {
      "frequency": "quarterly",
      "last_rebalanced": "2024-01-01T00:00:00Z",
      "next_rebalance": "2024-04-01T00:00:00Z",
      "automatic": true
    },
    "performance": {
      "24h_change_usd": 0.0025,
      "24h_change_percent": 0.23,
      "7d_change_usd": -0.0050,
      "7d_change_percent": -0.45,
      "30d_change_usd": 0.0175,
      "30d_change_percent": 1.62
    }
  }
}
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get GCU Value History</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/value-history</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get historical value data for the Global Currency Unit.</p>
                                
                                <h4 class="font-semibold mb-2">Query Parameters:</h4>
                                <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                                    <li><code class="bg-gray-100 px-1">period</code> - Time period: 24h, 7d, 30d, 90d, 1y, all (default: 30d)</li>
                                    <li><code class="bg-gray-100 px-1">interval</code> - Data interval: hourly, daily, weekly, monthly (default: daily)</li>
                                </ul>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/gcu/value-history?period=7d&interval=hourly"
                                </x-code-block>
                            </div>

                            <!-- Voting Endpoints -->
                            <div class="mt-12 mb-6">
                                <h3 class="text-2xl font-semibold text-gray-900">Democratic Voting System</h3>
                                <p class="text-gray-600 mt-2">The GCU voting system allows token holders to participate in monthly governance votes to optimize the currency basket composition.</p>
                            </div>

                            <div class="border rounded-lg p-6 mb-6">
                                <h3 class="text-xl font-semibold mb-4">List Voting Proposals</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/voting/proposals</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get all voting proposals with optional status filtering.</p>
                                
                                <h4 class="font-semibold mb-2">Query Parameters:</h4>
                                <ul class="list-disc list-inside text-gray-600 mb-4 space-y-1">
                                    <li><code class="bg-gray-100 px-1">status</code> - Filter by status: active, upcoming, past (optional)</li>
                                </ul>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/gcu/voting/proposals?status=active"
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 mb-6">
                                <h3 class="text-xl font-semibold mb-4">Get Proposal Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/voting/proposals/{id}</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get detailed information about a specific voting proposal.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/gcu/voting/proposals/123
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 mb-6">
                                <h3 class="text-xl font-semibold mb-4">Cast Vote</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/voting/proposals/{id}/vote</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">Requires Authentication</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Cast your vote on a proposal. Voting power is determined by your GCU balance.</p>
                                
                                <h4 class="font-semibold mb-2">Request Body:</h4>
                                <x-code-block language="json">
{
  "vote": "for"  // Options: "for", "against", "abstain"
}
                                </x-code-block>
                                
                                <x-code-block language="bash">
curl -X POST \
     -H "Authorization: Bearer your_api_key" \
     -H "Content-Type: application/json" \
     -d '{"vote": "for"}' \
     {{ config('app.url') }}/api/v2/gcu/voting/proposals/123/vote
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get My Voting History</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/voting/my-votes</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">Requires Authentication</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get your voting history across all proposals.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/gcu/voting/my-votes
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="baskets" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Baskets</h2>
                        
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Baskets are multi-asset currency units that can be composed and decomposed. The GCU is our primary basket.</p>
                        </div>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Baskets</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/baskets</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get a list of all available baskets.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/baskets
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Basket Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/baskets/{code}</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get detailed information about a specific basket.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/baskets/GCU
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Compose Basket</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/baskets/compose</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Convert individual assets into basket units.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "basket_code": "GCU",
    "amount": "100.00"
  }' \
  {{ config('app.url') }}/api/v2/accounts/acct_123/baskets/compose
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Decompose Basket</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/accounts/{uuid}/baskets/decompose</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Convert basket units back to individual assets.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "basket_code": "GCU",
    "amount": "50.00"
  }' \
  {{ config('app.url') }}/api/v2/accounts/acct_123/baskets/decompose
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="webhooks" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Webhooks</h2>
                        
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Webhooks allow you to receive real-time notifications when events occur in your {{ config('brand.name', 'Zelta') }} account.</p>
                        </div>
                        
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Webhook Events</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/webhooks/events</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get a list of all available webhook event types.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/webhooks/events
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Webhook</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/webhooks</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Create a new webhook endpoint.</p>
                                
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com/webhook",
    "events": ["transaction.created", "transfer.completed"],
    "description": "Main webhook endpoint"
  }' \
  {{ config('app.url') }}/api/v2/webhooks
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Webhooks</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/webhooks</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get all webhook endpoints for your account.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/webhooks
                                </x-code-block>
                            </div>
                            
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Webhook Deliveries</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/webhooks/{id}/deliveries</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">Get delivery history for a specific webhook.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/webhooks/webhook_123/deliveries
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- CrossChain API -->
                    <section id="crosschain" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">CrossChain API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The CrossChain API enables multi-chain operations including bridge transfers via Wormhole, LayerZero, and Axelar protocols. Compare bridge fees, execute cross-chain swaps, and track portfolios across multiple blockchains.</p>
                            <p class="text-sm text-gray-500">7 routes &middot; <a href="/api/documentation#/CrossChain" target="_blank" class="text-cyan-600 hover:text-cyan-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Initiate Bridge Transfer</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/crosschain/bridge</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Initiate a cross-chain bridge transfer through Wormhole, LayerZero, or Axelar.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source_chain": "ethereum",
    "destination_chain": "polygon",
    "token": "USDC",
    "amount": "1000.00",
    "protocol": "wormhole"
  }' \
  {{ config('app.url') }}/api/v2/crosschain/bridge
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Compare Bridge Fees</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/crosschain/bridge/fees</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Compare fees and estimated times across all supported bridge protocols for a given route.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/crosschain/bridge/fees?from=ethereum&to=arbitrum&token=USDC&amount=5000"
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Cross-Chain Swap</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/crosschain/swap</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Execute a token swap that bridges and swaps in a single atomic operation.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source_chain": "ethereum",
    "destination_chain": "polygon",
    "from_token": "ETH",
    "to_token": "MATIC",
    "amount": "1.5",
    "slippage_bps": 50
  }' \
  {{ config('app.url') }}/api/v2/crosschain/swap
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Multi-Chain Portfolio</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/crosschain/portfolio</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve an aggregated portfolio view across all supported chains.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/crosschain/portfolio
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- DeFi API -->
                    <section id="defi" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">DeFi API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The DeFi API provides access to decentralized finance protocols including DEX aggregation (Uniswap, Curve), lending (Aave), staking (Lido), yield optimization, and flash loan execution.</p>
                            <p class="text-sm text-gray-500">8 routes &middot; <a href="/api/documentation#/DeFi" target="_blank" class="text-emerald-600 hover:text-emerald-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Swap Quote</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/defi/swap/quote</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Get the best swap quote aggregated across Uniswap, Curve, and other supported DEXes.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/defi/swap/quote?from=ETH&to=USDC&amount=2.0&chain=ethereum"
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Execute Swap</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/defi/swap/execute</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Execute a token swap through the optimal DEX route.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_token": "ETH",
    "to_token": "USDC",
    "amount": "2.0",
    "slippage_bps": 50,
    "chain": "ethereum"
  }' \
  {{ config('app.url') }}/api/v2/defi/swap/execute
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">DeFi Portfolio Positions</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/defi/portfolio</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve all DeFi positions including lending deposits, staking, liquidity pools, and yield farming.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/defi/portfolio
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Flash Loan</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/defi/flash-loan</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Execute a flash loan with a sequence of operations that must complete atomically.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "USDC",
    "amount": "100000.00",
    "operations": [
      {"type": "swap", "from": "USDC", "to": "ETH", "dex": "uniswap"},
      {"type": "swap", "from": "ETH", "to": "USDC", "dex": "curve"}
    ]
  }' \
  {{ config('app.url') }}/api/v2/defi/flash-loan
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- RegTech API -->
                    <section id="regtech" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">RegTech API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The RegTech API provides regulatory compliance capabilities including MiFID II transaction reporting, MiCA crypto-asset compliance, Travel Rule enforcement for cross-border transfers, and jurisdiction-specific adapter configuration.</p>
                            <p class="text-sm text-gray-500">12 routes &middot; <a href="/api/documentation#/RegTech" target="_blank" class="text-amber-600 hover:text-amber-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Submit MiFID II Report</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/mifid/reports</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Submit a MiFID II transaction report to the configured National Competent Authority.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_id": "txn_abc123",
    "report_type": "transaction",
    "jurisdiction": "EU"
  }' \
  {{ config('app.url') }}/api/v2/regtech/mifid/reports
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">MiCA Compliance Check</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/mica/check</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Run a MiCA compliance validation against a crypto-asset or token issuance.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "asset_type": "e-money-token",
    "issuer_id": "issuer_xyz",
    "whitepaper_hash": "sha256:abc..."
  }' \
  {{ config('app.url') }}/api/v2/regtech/mica/check
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Travel Rule Transfer</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/travel-rule/transfers</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Submit originator and beneficiary information for FATF Travel Rule compliance on cross-border transfers.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "transfer_id": "xfer_789",
    "originator": {"name": "Alice Smith", "account": "acc_123"},
    "beneficiary": {"name": "Bob Jones", "vasp_id": "vasp_456"},
    "amount": "15000.00",
    "currency": "USDC"
  }' \
  {{ config('app.url') }}/api/v2/regtech/travel-rule/transfers
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Compliance Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/status</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Get overall compliance status across all active regulatory frameworks.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/regtech/status
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- Mobile Payment API -->
                    <section id="mobile-payment" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Mobile Payment API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The Mobile Payment API powers the {{ config('brand.name', 'Zelta') }} mobile wallet experience with payment intents, digital receipts, activity feeds, receive addresses, P2P transfers, passkey authentication, and biometric JWT sessions.</p>
                            <p class="text-sm text-gray-500">25+ routes &middot; <a href="/api/documentation#/MobilePayment" target="_blank" class="text-violet-600 hover:text-violet-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Payment Intent</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/mobile/payments/intents</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Create a new payment intent for mobile wallet transactions.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "25.00",
    "currency": "USD",
    "recipient": "user_456",
    "description": "Coffee payment"
  }' \
  {{ config('app.url') }}/api/v2/mobile/payments/intents
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Activity Feed</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/mobile/activity</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve the mobile wallet activity feed with payments, transfers, and notifications.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/mobile/activity?page=1&per_page=20"
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">P2P Transfer</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/mobile/transfers/p2p</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Send a peer-to-peer transfer to another mobile wallet user.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "to_user": "user_789",
    "amount": "50.00",
    "currency": "USD",
    "note": "Dinner split"
  }' \
  {{ config('app.url') }}/api/v2/mobile/transfers/p2p
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Passkey Authentication</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/mobile/auth/passkey/verify</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Verify a FIDO2/WebAuthn passkey for passwordless mobile authentication.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "credential_id": "cred_abc",
    "authenticator_data": "base64...",
    "client_data_json": "base64...",
    "signature": "base64..."
  }' \
  {{ config('app.url') }}/api/v2/mobile/auth/passkey/verify
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- Partner BaaS API -->
                    <section id="partner-baas" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Partner / BaaS API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The Partner BaaS (Banking-as-a-Service) API enables third-party partners to provision tenants, generate branded SDKs, configure white-label deployments, and manage their partner integrations. Requires a Partner API key (<code>fpk_</code> prefix) in addition to standard authentication.</p>
                            <p class="text-sm text-gray-500">24 routes &middot; <a href="/api/documentation#/Partner" target="_blank" class="text-rose-600 hover:text-rose-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Provision Tenant</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/partner/tenants</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Key Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Provision a new tenant for a BaaS partner with isolated data and configuration.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "X-Partner-Key: fpk_your_partner_key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme Bank",
    "plan": "enterprise",
    "domain": "acme.finaegis.io"
  }' \
  {{ config('app.url') }}/api/v2/partner/tenants
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Generate SDK</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/partner/sdk/generate</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Key Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Generate a branded SDK package for the partner's platform and language.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "X-Partner-Key: fpk_your_partner_key" \
  -H "Content-Type: application/json" \
  -d '{
    "language": "javascript",
    "branding": {"name": "AcmeSDK", "color": "#3B82F6"},
    "modules": ["accounts", "transfers", "payments"]
  }' \
  {{ config('app.url') }}/api/v2/partner/sdk/generate
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">White-Label Configuration</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">PUT</span>
                                        <span class="ml-2 font-mono text-sm">/partner/config/whitelabel</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Key Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Update white-label branding, theming, and feature toggles for the partner deployment.</p>
                                <x-code-block language="bash">
curl -X PUT \
  -H "Authorization: Bearer your_api_key" \
  -H "X-Partner-Key: fpk_your_partner_key" \
  -H "Content-Type: application/json" \
  -d '{
    "logo_url": "https://acme.com/logo.svg",
    "primary_color": "#3B82F6",
    "features": {"defi": true, "crosschain": true, "lending": false}
  }' \
  {{ config('app.url') }}/api/v2/partner/config/whitelabel
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Partner Tenants</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/partner/tenants</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Key Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">List all tenants provisioned under this partner account.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     -H "X-Partner-Key: fpk_your_partner_key" \
     {{ config('app.url') }}/api/v2/partner/tenants
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- SMS API -->
                    <section id="sms" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">SMS API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Send SMS messages globally via VertexSMS with per-message payment gating through the Machine Payment Protocol (MPP). AI agents and applications pay per-SMS using USDC, Stripe, or Lightning — no prepaid credits, no invoicing.</p>
                            <p class="text-sm text-gray-500">5 routes &middot; <a href="/api/documentation#/SMS" target="_blank" class="text-orange-600 hover:text-orange-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Send SMS (Payment-Gated)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/sms/send</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Send an SMS message to any phone number. This endpoint is gated by MPP — the first request returns <code>402 Payment Required</code> with available payment rails and pricing. After payment, the SMS is sent and a receipt is returned.</p>
                                <x-code-block language="bash">
# Step 1: Get payment challenge (returns 402)
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+37069912345",
    "from": "Zelta",
    "message": "Hello from Zelta!"
  }' \
  {{ config('app.url') }}/api/v1/sms/send

# Step 2: Pay via chosen rail, then resend with payment proof
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Payment ..." \
  -d '{
    "to": "+37069912345",
    "from": "Zelta",
    "message": "Hello from Zelta!"
  }' \
  {{ config('app.url') }}/api/v1/sms/send

# Response:
# {
#   "success": true,
#   "data": {
#     "message_id": "1281532560",
#     "status": "sent",
#     "parts": 1,
#     "destination": "+37069912345",
#     "price_usdc": "48000"
#   }
# }
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Check SMS Rates</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/sms/rates?country=LT</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Returns per-message USDC pricing for a given ISO 3166-1 alpha-2 country code. Rates are sourced from VertexSMS and converted from EUR to atomic USDC (6 decimals, e.g. 48000 = $0.048).</p>
                                <x-code-block language="bash">
curl "{{ config('app.url') }}/api/v1/sms/rates?country=LT"

# Response:
# {
#   "data": {
#     "country": "Lithuania",
#     "country_code": "LT",
#     "rate_eur": "0.0390",
#     "rate_usdc": "48438"
#   }
# }
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Check Delivery Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/sms/status/{messageId}</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Returns the current delivery status of a previously sent SMS. Requires authentication. Statuses: <code>pending</code>, <code>sent</code>, <code>delivered</code>, <code>failed</code>.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v1/sms/status/1281532560"

# Response:
# {
#   "success": true,
#   "data": {
#     "message_id": "1281532560",
#     "status": "delivered",
#     "delivered_at": "2026-04-17T12:01:05+00:00",
#     "payment_status": "settled"
#   }
# }
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Service Info</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/sms/info</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Returns SMS service status, active provider, test mode flag, and supported payment networks.</p>
                                <x-code-block language="bash">
curl "{{ config('app.url') }}/api/v1/sms/info"

# Response:
# {
#   "data": {
#     "provider": "vertexsms",
#     "enabled": true,
#     "test_mode": false,
#     "networks": ["eip155:8453", "eip155:1"]
#   }
# }
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 bg-blue-50 border-blue-200">
                                <h3 class="text-xl font-semibold mb-4">MCP Tool: send_sms</h3>
                                <p class="text-gray-600 mb-4">AI agents can discover and use SMS through the Model Context Protocol. The <code>send_sms</code> tool is automatically available to any MCP-compatible client connected to {{ config('brand.name', 'Zelta') }}. Payment is handled transparently by the {{ config('brand.name', 'Zelta') }} SDK.</p>
                                <x-code-block language="json">
{
  "tool": "send_sms",
  "description": "Send SMS via VertexSMS. Pay per-message via USDC, Stripe, or Lightning.",
  "input": {
    "to": "+37069912345",
    "from": "Zelta",
    "message": "Hello from AI"
  }
}
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- AI API -->
                    <section id="ai" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">AI Query API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The AI Query API provides natural language access to transaction data and financial insights. Ask questions in plain English and receive structured, actionable responses.</p>
                            <p class="text-sm text-gray-500">2 routes &middot; <a href="/api/documentation#/AI" target="_blank" class="text-gray-600 hover:text-gray-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Natural Language Query</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/ai/query</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Submit a natural language question about your transactions, balances, or financial activity.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "What were my largest transactions last month?",
    "context": {"account_id": "acc_123"}
  }' \
  {{ config('app.url') }}/api/v2/ai/query
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">AI Query History</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/ai/queries</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve your past AI query history with cached results.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/ai/queries
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- GraphQL API -->
                    <section id="graphql" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">GraphQL API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Schema-first GraphQL API powered by Lighthouse PHP. Provides queries, mutations, and subscriptions across 45 domain schemas with DataLoader-optimized resolvers and WebSocket-based real-time subscriptions.</p>
                            <p class="text-sm text-gray-500">45 domain schemas &middot; Queries, Mutations, Subscriptions</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Execute GraphQL Query / Mutation</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/graphql</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Execute a GraphQL query or mutation against the unified schema. Supports all 45 domain schemas including Account, AgentProtocol, Basket, Batch, CardIssuance, Cgo, Compliance, CrossChain, DeFi, Exchange, FinancialInstitution, ISO20022, OpenBanking, PaymentRails, Microfinance, Interledger, Ledger, Regulatory, User, Wallet, and more.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "{ accounts(first: 10) { data { id name currency balance { available total } } } }"
  }' \
  {{ config('app.url') }}/api/graphql
                                </x-code-block>

                                <h4 class="font-semibold mb-2 mt-6">Example Query:</h4>
                                <x-code-block language="graphql">
query {
  accounts(first: 10) {
    data {
      id
      name
      currency
      balance {
        available
        total
      }
    }
    paginatorInfo {
      total
      currentPage
      lastPage
    }
  }
}
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">GraphQL Playground</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/graphql-playground</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Interactive GraphQL explorer with schema introspection, auto-complete, and query history. Use this to explore available types, queries, mutations, and subscriptions.</p>
                                <x-code-block language="bash">
# Open in your browser
{{ config('app.url') }}/api/graphql-playground
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 bg-sky-50 border-sky-200">
                                <h3 class="text-xl font-semibold mb-4">Available Subscriptions</h3>
                                <p class="text-gray-600 mb-4">Real-time WebSocket subscriptions are available for the following events:</p>
                                <ul class="list-disc list-inside text-gray-600 space-y-1">
                                    <li><code class="bg-gray-100 px-1">accountUpdated(id: ID!)</code> - Account balance and status changes</li>
                                    <li><code class="bg-gray-100 px-1">transactionCreated(accountId: ID!)</code> - New transactions on an account</li>
                                    <li><code class="bg-gray-100 px-1">transferCompleted(id: ID!)</code> - Transfer completion notifications</li>
                                    <li><code class="bg-gray-100 px-1">orderMatched(pair: String!)</code> - Exchange order match events</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Event Streaming & Live Dashboard -->
                    <section id="event-streaming" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Event Streaming & Live Dashboard</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Real-time event streaming via Redis Streams with a live metrics dashboard. Monitor system health, domain status, event throughput, stream connectivity, and projector lag across all event-sourced domains.</p>
                            <p class="text-sm text-gray-500">5 endpoints &middot; Redis Streams &middot; Real-time Metrics</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">System Metrics</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/live-dashboard/metrics</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve aggregated system metrics including event counts, processing rates, error rates, and uptime statistics.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/live-dashboard/metrics
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Domain Health</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/live-dashboard/domain-health</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Get health status for each event-sourced domain including event store connectivity, projector status, and recent error counts.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/live-dashboard/domain-health
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Event Throughput</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/live-dashboard/event-throughput</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Monitor real-time event throughput rates per domain and aggregate, including events per second and processing latency.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/live-dashboard/event-throughput
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Stream Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/live-dashboard/stream-status</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Check Redis Streams connectivity, consumer group status, pending message counts, and stream memory usage.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/live-dashboard/stream-status
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Projector Lag</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/live-dashboard/projector-lag</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Monitor projector lag across all event-sourced domains showing how far behind each read model projector is from the latest events.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/live-dashboard/projector-lag
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- ISO 20022 API -->
                    <section id="iso20022" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">ISO 20022 API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Parse, generate, and validate ISO 20022 financial messages. Supports 8 message types (pacs.008, pacs.002, pain.001, pain.002, camt.053, camt.054, camt.056, admi.002) with full REST and GraphQL coverage. XML is validated against official XSD schemas.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Parse Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/parse</span>
                                </div>
                                <p class="text-gray-600 mb-4">Parse an ISO 20022 XML message and return a structured JSON representation.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/xml" \
  --data-binary @payment.xml \
  {{ config('app.url') }}/api/v1/iso20022/parse
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Generate Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/generate</span>
                                </div>
                                <p class="text-gray-600 mb-4">Generate a standards-compliant ISO 20022 XML message from a JSON payload.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"type":"pacs.008","amount":"1000.00","currency":"EUR","debtor":"...","creditor":"..."}' \
  {{ config('app.url') }}/api/v1/iso20022/generate
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Validate Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/validate</span>
                                </div>
                                <p class="text-gray-600 mb-4">Validate an ISO 20022 XML message against the official XSD schema and business rules.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Open Banking API -->
                    <section id="open-banking" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Open Banking API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Full PSD2 consent lifecycle with AISP (Account Information Service Provider) and PISP (Payment Initiation Service Provider) services. Supports Berlin Group NextGenPSD2 and UK Open Banking adapters. TPP registration with eIDAS certificate validation.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Consent</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/consents</span>
                                </div>
                                <p class="text-gray-600 mb-4">Initiate a PSD2 consent request for account access or payment initiation.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"access":{"accounts":"all","balances":"all","transactions":"all"},"recurringIndicator":false,"validUntil":"2026-12-31","frequencyPerDay":4}' \
  {{ config('app.url') }}/api/v1/open-banking/consents
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Account Information (AISP)</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/accounts</span>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve account list under an active AISP consent.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Initiate Payment (PISP)</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/payments</span>
                                </div>
                                <p class="text-gray-600 mb-4">Initiate a payment under an active PISP consent.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Payment Rails API -->
                    <section id="payment-rails" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Payment Rails API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Multi-rail payment processing with ACH (NACHA file generation), Fedwire, RTP, FedNow (ISO 20022 native), SEPA Direct Debit, and SCT Inst. Intelligent routing automatically selects the optimal rail based on amount, currency, and counterparty.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Initiate Payment</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/payment-rails/payments</span>
                                </div>
                                <p class="text-gray-600 mb-4">Initiate a payment via the specified rail, or let intelligent routing select the optimal rail automatically.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"amount":"500.00","currency":"USD","rail":"auto","creditor_account":"...","memo":"Invoice #1234"}' \
  {{ config('app.url') }}/api/v1/payment-rails/payments
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">List Supported Rails</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/payment-rails/rails</span>
                                </div>
                                <p class="text-gray-600 mb-4">List all available payment rails with current availability and cut-off times.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Generate ACH File</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/payment-rails/ach/files</span>
                                </div>
                                <p class="text-gray-600 mb-4">Generate a NACHA-compliant ACH batch file from a list of payment entries.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Interledger API -->
                    <section id="interledger" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Interledger API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Cross-network value transfer with ILP connector, Open Payments (GNAP authorization), and cross-currency quotes. Bridge fiat and crypto payment networks seamlessly.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Quote</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/interledger/quotes</span>
                                </div>
                                <p class="text-gray-600 mb-4">Get a cross-currency quote for an Interledger payment including fees and FX rate.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"send_amount":"100.00","send_currency":"USD","receive_currency":"EUR","receiver":"$wallet.example.com/alice"}' \
  {{ config('app.url') }}/api/v1/interledger/quotes
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Send Payment</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/interledger/payments</span>
                                </div>
                                <p class="text-gray-600 mb-4">Execute an Interledger payment using a previously obtained quote.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Ledger API -->
                    <section id="ledger" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Ledger API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Production-grade double-entry accounting engine. Manage chart of accounts, post journal entries, run trial balances, and configure GL auto-posting rules. Supports an optional TigerBeetle driver for extreme throughput workloads.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Chart of Accounts</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/ledger/accounts</span>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve the full chart of accounts with balances.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/ledger/accounts
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Post Journal Entry</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/ledger/entries</span>
                                </div>
                                <p class="text-gray-600 mb-4">Post a balanced double-entry journal entry. Debits must equal credits.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Trial Balance</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/ledger/trial-balance</span>
                                </div>
                                <p class="text-gray-600 mb-4">Generate a trial balance report for a given date range.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Microfinance API -->
                    <section id="microfinance" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Microfinance API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Complete inclusion banking infrastructure: group lending with joint liability, IFRS 9 loan provisioning, cooperative share accounts, teller cash operations, field officer tools, and savings products with dormancy tracking.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Loan Group</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/microfinance/groups</span>
                                </div>
                                <p class="text-gray-600 mb-4">Create a group lending entity with joint liability configuration.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"name":"Village Savings Group A","members":["user_1","user_2","user_3"],"liability_type":"joint"}' \
  {{ config('app.url') }}/api/v1/microfinance/groups
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">IFRS Loan Provisioning</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/microfinance/loans/{id}/provision</span>
                                </div>
                                <p class="text-gray-600 mb-4">Calculate IFRS 9 expected credit loss (ECL) provision for a loan.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Teller Operations</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/microfinance/teller/transactions</span>
                                </div>
                                <p class="text-gray-600 mb-4">Post a teller cash transaction (deposit, withdrawal, or currency exchange).</p>
                            </div>
                        </div>
                    </section>

                    <!-- x402 Protocol API -->
                    <section id="x402" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">x402 Protocol API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The x402 Protocol enables HTTP-native micropayments using USDC on Base. Monetize any API endpoint by returning HTTP 402 responses with payment requirements. Supports AI agent autonomous payments, spending limits, and multi-network settlement.</p>
                            <p class="text-sm text-gray-500">15+ endpoints &middot; <a href="/api/documentation#/X402" target="_blank" class="text-emerald-600 hover:text-emerald-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Protocol Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/x402/status</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Get the current x402 protocol status including supported networks, assets, and facilitator connectivity.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/x402/status
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Supported Networks</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/x402/supported</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">List all supported blockchain networks and payment assets for x402 settlement.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/x402/supported
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Monetized Endpoints</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/x402/endpoints</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Register an API endpoint for x402 monetization with pricing configuration.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "path": "/v2/premium/data",
    "price_usd_cents": 100,
    "network": "eip155:8453",
    "asset": "USDC",
    "description": "Premium market data"
  }' \
  {{ config('app.url') }}/api/v2/x402/endpoints
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Payment History</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/x402/payments</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">Retrieve payment history and settlement status for x402 transactions.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     "{{ config('app.url') }}/api/v2/x402/payments?page=1&per_page=20"
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Spending Limits</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/x402/spending-limits</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">View and manage spending limits for automated x402 payments including AI agent budgets.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/x402/spending-limits
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 bg-emerald-50 border-emerald-200">
                                <h3 class="text-xl font-semibold mb-4">Handling 402 Responses</h3>
                                <p class="text-gray-600 mb-4">When a client hits a monetized endpoint without payment, it receives an HTTP 402 with payment requirements in headers:</p>
                                <x-code-block language="plaintext">
HTTP/1.1 402 Payment Required
X-Payment-Required: true
X-Payment-Network: eip155:8453
X-Payment-Asset: USDC
X-Payment-Amount: 0.01
X-Payment-Receiver: 0x...
X-Payment-Facilitator: https://x402.org/facilitator
                                </x-code-block>

                                <h4 class="font-semibold mb-2 mt-6">JavaScript Client Example:</h4>
                                <x-code-block language="javascript">
const response = await fetch('{{ config('app.url') }}/api/v2/premium/data', {
  headers: { 'Authorization': 'Bearer YOUR_API_KEY' }
});

if (response.status === 402) {
  const paymentInfo = {
    network: response.headers.get('X-Payment-Network'),
    asset: response.headers.get('X-Payment-Asset'),
    amount: response.headers.get('X-Payment-Amount'),
    receiver: response.headers.get('X-Payment-Receiver'),
  };

  // Sign and submit payment, then retry with proof
  const proof = await signPayment(paymentInfo);
  const paid = await fetch('{{ config('app.url') }}/api/v2/premium/data', {
    headers: {
      'Authorization': 'Bearer YOUR_API_KEY',
      'X-Payment-Signature': proof.signature,
      'X-Payment-Payload': proof.payload,
    }
  });
  const data = await paid.json(); // 200 + data
}
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="rate-limits" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Rate Limits</h2>
                        <div class="prose prose-lg max-w-none mb-6">
                            <p>All API requests are rate limited. Limits apply per API key and are enforced on a sliding window.</p>
                        </div>
                        <div class="border rounded-lg overflow-hidden mb-6">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Endpoint Type</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Limit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr><td class="px-4 py-3 text-gray-700">REST API</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">1,000 requests/hour per API key</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">GraphQL (authenticated)</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">120 queries/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">GraphQL (guest)</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">30 queries/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Webhooks</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">100 deliveries/minute per endpoint</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Burst (sliding window)</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">100 requests/minute</code></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Rate limit headers included in every response:</p>
                            <div class="space-y-1 font-mono text-sm text-gray-600">
                                <div><code>X-RateLimit-Limit</code> — Maximum requests allowed in the window</div>
                                <div><code>X-RateLimit-Remaining</code> — Requests remaining in the current window</div>
                                <div><code>X-RateLimit-Reset</code> — Unix timestamp when the window resets</div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-8">
                        <div class="bg-white border rounded-lg p-6 mb-8">
                            <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                            <ul class="space-y-2">
                                <li><a href="{{ route('developers.show', 'sdks') }}" class="text-blue-600 hover:text-blue-800">Official SDKs</a></li>
                                <li><a href="{{ route('developers.show', 'postman') }}" class="text-blue-600 hover:text-blue-800">Postman Collection</a></li>
                                <li><a href="{{ route('developers.show', 'examples') }}" class="text-blue-600 hover:text-blue-800">Code Examples</a></li>
                                <li><a href="{{ route('developers.show', 'webhooks') }}" class="text-blue-600 hover:text-blue-800">Webhooks Guide</a></li>
                            </ul>
                        </div>

                        <div class="bg-white border rounded-lg p-6 mb-8">
                            <h3 class="text-lg font-semibold mb-4">Platform API Areas</h3>
                            <ul class="space-y-2 text-sm">
                                <li><a href="#crosschain" class="text-cyan-600 hover:text-cyan-800 flex justify-between"><span>CrossChain</span><span class="text-gray-400">7 routes</span></a></li>
                                <li><a href="#defi" class="text-emerald-600 hover:text-emerald-800 flex justify-between"><span>DeFi</span><span class="text-gray-400">8 routes</span></a></li>
                                <li><a href="#regtech" class="text-amber-600 hover:text-amber-800 flex justify-between"><span>RegTech</span><span class="text-gray-400">12 routes</span></a></li>
                                <li><a href="#mobile-payment" class="text-violet-600 hover:text-violet-800 flex justify-between"><span>Mobile Payment</span><span class="text-gray-400">25+ routes</span></a></li>
                                <li><a href="#partner-baas" class="text-rose-600 hover:text-rose-800 flex justify-between"><span>Partner BaaS</span><span class="text-gray-400">24 routes</span></a></li>
                                <li><a href="#ai" class="text-gray-600 hover:text-gray-800 flex justify-between"><span>AI Query</span><span class="text-gray-400">2 routes</span></a></li>
                                <li><a href="#graphql" class="text-sky-600 hover:text-sky-800 flex justify-between"><span>GraphQL</span><span class="text-gray-400">45 domains</span></a></li>
                                <li><a href="#event-streaming" class="text-lime-600 hover:text-lime-800 flex justify-between"><span>Event Streaming</span><span class="text-gray-400">5 endpoints</span></a></li>
                                <li><a href="#x402" class="text-emerald-600 hover:text-emerald-800 flex justify-between"><span>x402 Protocol</span><span class="text-gray-400">15+ endpoints</span></a></li>
                                <li><a href="#iso20022" class="text-blue-600 hover:text-blue-800 flex justify-between"><span>ISO 20022</span><span class="text-gray-400">8 msg types</span></a></li>
                                <li><a href="#open-banking" class="text-teal-600 hover:text-teal-800 flex justify-between"><span>Open Banking</span><span class="text-gray-400">AISP + PISP</span></a></li>
                                <li><a href="#payment-rails" class="text-orange-600 hover:text-orange-800 flex justify-between"><span>Payment Rails</span><span class="text-gray-400">ACH/Fedwire/SEPA</span></a></li>
                                <li><a href="#interledger" class="text-purple-600 hover:text-purple-800 flex justify-between"><span>Interledger</span><span class="text-gray-400">ILP + GNAP</span></a></li>
                                <li><a href="#ledger" class="text-slate-600 hover:text-slate-800 flex justify-between"><span>Ledger</span><span class="text-gray-400">Double-entry</span></a></li>
                                <li><a href="#microfinance" class="text-green-600 hover:text-green-800 flex justify-between"><span>Microfinance</span><span class="text-gray-400">Group lending</span></a></li>
                            </ul>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-900 mb-4">OpenAPI Specification</h3>
                            <p class="text-blue-800 mb-4">Download the OpenAPI specification file or view it in your preferred API client.</p>
                            <div class="flex gap-3">
                                <a href="/docs/api-docs.json" download="zelta-api-v2.json" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                    Download OpenAPI JSON
                                </a>
                                <a href="/api/documentation" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                    Interactive API Explorer
                                </a>
                            </div>
                            <p class="text-xs text-gray-600 mt-3">Import the JSON file into Postman, Insomnia, or any OpenAPI-compatible tool</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function copyCode(button) {
    const codeBlock = button.parentElement.querySelector('code');
    const text = codeBlock.textContent;
    
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = button.innerHTML;
        button.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        button.classList.add('text-green-400');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('text-green-400');
        }, 2000);
    });
}
</script>
@endpush