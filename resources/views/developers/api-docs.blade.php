@extends('layouts.public')

@section('title', 'API Documentation - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'API Documentation - ' . config('brand.name', 'Zelta') . '',
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
                    <a href="#ai" class="text-gray-600 hover:text-gray-900">AI</a>
                    <a href="#graphql" class="text-sky-600 hover:text-sky-800">GraphQL</a>
                    <a href="#event-streaming" class="text-lime-600 hover:text-lime-800">Event Streaming</a>
                    <a href="#x402" class="text-emerald-600 hover:text-emerald-800">x402 Protocol</a>
                    <a href="#webhooks" class="text-gray-600 hover:text-gray-900">Webhooks</a>
                    <a href="#errors" class="text-gray-600 hover:text-gray-900">Errors</a>
                </nav>
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
                            <p>The {{ config('brand.name', 'Zelta') }} API provides programmatic access to our multi-asset banking platform spanning 45 DDD domains with over 1,250 routes. Our API is organized around REST principles with predictable, resource-oriented URLs. Domains include core banking, CrossChain bridging, DeFi protocols, RegTech compliance, Mobile Payment, Partner BaaS, and AI-powered queries.</p>
                            
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
                            <p>Use these base URLs for testing and production:</p>
                            <ul>
                                <li><strong>Sandbox:</strong> {{ config('app.url') }}/api/v2</li>
                                <li><strong>Production:</strong> {{ config('app.url') }}/api/v2</li>
                            </ul>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/composition</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/value-history</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/voting/proposals</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/voting/proposals/{id}</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/voting/proposals/{id}/vote</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/gcu/voting/my-votes</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/crosschain/bridge</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/crosschain/bridge/fees</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/crosschain/swap</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/crosschain/portfolio</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/defi/swap/quote</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/defi/swap/execute</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/defi/portfolio</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/defi/flash-loan</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/regtech/mifid/reports</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/regtech/mica/check</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/regtech/travel-rule/transfers</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/regtech/status</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/mobile/payments/intents</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/mobile/activity</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/mobile/transfers/p2p</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/mobile/auth/passkey/verify</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/partner/tenants</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/partner/sdk/generate</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/partner/config/whitelabel</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/partner/tenants</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/ai/query</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/ai/queries</span>
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
                            <p>Schema-first GraphQL API powered by Lighthouse PHP. Provides queries, mutations, and subscriptions across 36 domain schemas with DataLoader-optimized resolvers and WebSocket-based real-time subscriptions.</p>
                            <p class="text-sm text-gray-500">36 domain schemas &middot; Queries, Mutations, Subscriptions</p>
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
                                <p class="text-gray-600 mb-4">Execute a GraphQL query or mutation against the unified schema. Supports all 36 domain schemas including Account, AgentProtocol, Basket, Batch, CardIssuance, Cgo, Compliance, CrossChain, DeFi, Exchange, FinancialInstitution, Product, Regulatory, User, Wallet, and more.</p>
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
                                        <span class="ml-2 font-mono text-sm">/v2/x402/status</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/x402/supported</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/x402/endpoints</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/x402/payments</span>
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
                                        <span class="ml-2 font-mono text-sm">/v2/x402/spending-limits</span>
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
                                <li><a href="#graphql" class="text-sky-600 hover:text-sky-800 flex justify-between"><span>GraphQL</span><span class="text-gray-400">36 domains</span></a></li>
                                <li><a href="#event-streaming" class="text-lime-600 hover:text-lime-800 flex justify-between"><span>Event Streaming</span><span class="text-gray-400">5 endpoints</span></a></li>
                                <li><a href="#x402" class="text-emerald-600 hover:text-emerald-800 flex justify-between"><span>x402 Protocol</span><span class="text-gray-400">15+ endpoints</span></a></li>
                            </ul>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-900 mb-4">OpenAPI Specification</h3>
                            <p class="text-blue-800 mb-4">Download the OpenAPI specification file or view it in your preferred API client.</p>
                            <div class="flex gap-3">
                                <a href="/docs/api-docs.json" download="finaegis-api-v2.json" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
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