@extends('layouts.public')

@section('title', 'API Documentation - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'API Documentation - ' . config('brand.name', 'Zelta'),
        'description' => 'Reference for the ' . config('brand.name', 'Zelta') . ' REST API. Each endpoint has a short description and a sample request.',
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
                        This is the reference for the {{ config('brand.name', 'Zelta') }} REST API. Each endpoint has a short description and a sample request.
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
                    <a href="#defi" class="text-emerald-600 hover:text-emerald-800">Decentralised Finance</a>
                    <a href="#regtech" class="text-amber-600 hover:text-amber-800">RegTech</a>
                    <a href="#mobile-payment" class="text-violet-600 hover:text-violet-800">Mobile Payment</a>
                    <a href="#partner-baas" class="text-rose-600 hover:text-rose-800">Partner API</a>
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
                </nav>
            </div>
        </div>

        <!-- OpenAPI Spec Banner -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-200 rounded-lg mb-8">
                <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <div>
                    <p class="font-semibold text-blue-900">OpenAPI 3.0 Specification</p>
                    <p class="text-sm text-blue-700">You can import it into Postman, Insomnia or any tool that reads OpenAPI.</p>
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
                            <p>The {{ config('brand.name', 'Zelta') }} API gives programs access to the platform. The platform has 61 domain modules and more than 1,000 API routes. The API follows REST conventions, so each resource has its own predictable address.</p>
                            <p>The API covers core banking, cross-chain transfers, decentralised finance, reporting modules, mobile payments, the Partner API, ISO 20022, open banking, United States payment rails and AI queries.</p>
                            
                            <h3>Base URL</h3>
                            <p>Core banking endpoints live under <code>/api/v2</code>. Newer modules live under <code>/api/v1</code> or under their own prefix, as each section below shows.</p>
                            <x-code-block language="plaintext">
{{ config('app.url') }}/api/v2
                            </x-code-block>
                            
                            <h3>Response Format</h3>
                            <p>Every response is JSON. Most responses use this structure.</p>
                            
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
                            <p>The {{ config('brand.name', 'Zelta') }} API checks an API key on each request. You create and manage your API keys in your dashboard.</p>
                            
                            <h3>Creating API Keys</h3>
                            <ol>
                                <li>Sign in to your {{ config('brand.name', 'Zelta') }} account.</li>
                                <li>Navigate to <a href="{{ route('api-keys.index') }}" class="text-blue-600 hover:text-blue-800">API Keys</a> in your dashboard.</li>
                                <li>Click "Create New Key" and choose its permissions.</li>
                                <li>Copy the key right away, because it is shown only once.</li>
                            </ol>
                            
                            <h3>API Key Authentication</h3>
                            <p>Send your API key in the Authorization header.</p>
                            
                            <x-code-block language="bash">
curl -H "Authorization: Bearer fak_your_api_key_here" \
     -H "Content-Type: application/json" \
     {{ config('app.url') }}/api/v2/accounts
                            </x-code-block>
                            
                            <h3>API Key Security</h3>
                            <ul>
                                <li><strong>Permissions:</strong> Give a key only the permissions it needs, from read, write and delete.</li>
                                <li><strong>Allowed addresses:</strong> Limit a key to the network addresses you name.</li>
                                <li><strong>Expiry:</strong> Set an expiry date for a temporary key.</li>
                                <li><strong>Rotation:</strong> Replace your keys at regular intervals.</li>
                                <li><strong>Storage:</strong> Never commit an API key to version control.</li>
                            </ul>
                            
                            <h3>Demo and Self-Hosted Servers</h3>
                            <div class="mb-2">
                                <span class="font-semibold text-slate-700">Base URL:</span>
                                <code class="bg-slate-100 px-2 py-1 rounded text-sm">{{ config('app.url') }}/api/v2</code>
                            </div>
                            <p class="text-sm text-slate-500">Each deployment has its own base URL. The public demo uses test data only. API keys start with <code>fak_</code>.</p>
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
                                
                                <p class="text-gray-600 mb-4">This endpoint lists the accounts of the signed-in user.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns one account.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns the balance of each asset in an account.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint lists transactions, one page at a time.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint adds funds to an account.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint takes funds out of an account.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint creates a transfer between two accounts.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns the transfer history of one account.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/accounts/acct_1234567890/transfers
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="gcu" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Global Currency Unit (GCU)</h2>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-8 text-sm text-gray-700">
                            The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
                        </div>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The GCU endpoints return data about the Global Currency Unit demo. They cover its composition, its value history and its simulated governance. The GCU is a software demonstration. The values, balances and votes these endpoints return are simulated and have no monetary value.</p>
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns the current composition and value of the GCU demo.</p>
                                
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/gcu
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get GCU Composition</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/composition</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">This endpoint returns each component asset of the demo basket. It shows the current weight, the value and recent changes.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns the value history of the GCU demo.</p>
                                
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
                                <h3 class="text-2xl font-semibold text-gray-900">Basket Voting (Simulated)</h3>
                                <p class="text-gray-600 mt-2">The voting endpoints of the GCU demo simulate monthly basket "votes".</p>
                            </div>

                            <div class="border rounded-lg p-6 mb-6">
                                <h3 class="text-xl font-semibold mb-4">List Voting Proposals</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/gcu/voting/proposals</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 mb-4">This endpoint lists the voting proposals. You can filter them by status.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns one voting proposal.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint casts a vote on a proposal. The voting weight comes from simulated demo balances.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns your votes on all proposals.</p>
                                
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
                            <p>A basket is a unit made of several assets. You can compose a basket from its assets and decompose it back. The GCU demo is the reference basket.</p>
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
                                
                                <p class="text-gray-600 mb-4">This endpoint lists the available baskets.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns one basket.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint turns individual assets into basket units.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint turns basket units back into individual assets.</p>
                                
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
                            <p>A webhook sends a notification to your server when an event happens on the platform. Only platform administrators can manage webhooks, because a webhook receives every subscribed event.</p>
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
                                
                                <p class="text-gray-600 mb-4">This endpoint lists the event types you can subscribe to.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint creates a webhook. The address must use HTTPS.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint lists the configured webhooks.</p>
                                
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
                                
                                <p class="text-gray-600 mb-4">This endpoint returns the delivery history of one webhook.</p>
                                
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
                            <p>The cross-chain API moves tokens between networks. It quotes and starts bridge transfers through adapters for Wormhole, LayerZero and Axelar, and it quotes and runs swaps between networks. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                            <p class="text-sm text-gray-500">6 routes &middot; <a href="/api/documentation#/CrossChain" target="_blank" class="text-cyan-600 hover:text-cyan-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Bridge Quote</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/crosschain/bridge/quote</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint asks every available bridge adapter for a quote. You can compare their fees and times.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_chain": "ethereum",
    "to_chain": "arbitrum",
    "token": "USDC",
    "amount": "5000.00"
  }' \
  {{ config('app.url') }}/api/v1/crosschain/bridge/quote
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Start a Bridge Transfer</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/crosschain/bridge/initiate</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint starts a bridge transfer from one network to another.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_chain": "ethereum",
    "to_chain": "polygon",
    "token": "USDC",
    "amount": "1000.00",
    "sender_address": "0x...",
    "recipient_address": "0x..."
  }' \
  {{ config('app.url') }}/api/v1/crosschain/bridge/initiate
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Bridge Transfer Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/crosschain/bridge/{id}/status</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the status of a bridge transfer.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/crosschain/bridge/{id}/status
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Cross-Chain Swap</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/crosschain/swap/execute</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint bridges a token and swaps it on the destination network. Ask for a quote first at <code>/v1/crosschain/swap/quote</code>.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "from_chain": "ethereum",
    "to_chain": "polygon",
    "from_token": "USDC",
    "to_token": "WETH",
    "amount": "1500.00",
    "wallet_address": "0x..."
  }' \
  {{ config('app.url') }}/api/v1/crosschain/swap/execute
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Supported Networks</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/crosschain/chains</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint lists the supported networks and the bridges available for each.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/crosschain/chains
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- Decentralised Finance API -->
                    <section id="defi" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Decentralised Finance API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The decentralised finance API quotes and runs token swaps across decentralised exchanges. It also lists lending markets, staking positions and protocol rates through adapters for Uniswap, Curve, Aave and Lido. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                            <p class="text-sm text-gray-500">8 routes &middot; <a href="/api/documentation#/DeFi" target="_blank" class="text-emerald-600 hover:text-emerald-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Swap Quote</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/defi/swap/quote</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the best swap quote across the supported decentralised exchanges.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"chain": "ethereum", "from_token": "WETH", "to_token": "USDC", "amount": "2.0"}' \
  {{ config('app.url') }}/api/v1/defi/swap/quote
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Execute Swap</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/defi/swap/execute</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint runs a swap through the best route it found.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "chain": "ethereum",
    "from_token": "WETH",
    "to_token": "USDC",
    "amount": "2.0",
    "wallet_address": "0x...",
    "slippage": 0.5
  }' \
  {{ config('app.url') }}/api/v1/defi/swap/execute
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Decentralised Finance Portfolio</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/defi/portfolio</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns your decentralised finance positions across networks. It covers lending, staking and pool positions.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/defi/portfolio
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Lending Markets</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/defi/lending/markets</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint lists the lending markets the adapters can read.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/defi/lending/markets
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- RegTech API -->
                    <section id="regtech" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">RegTech API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The RegTech API holds reporting and data-exchange modules. They are modelled on the European markets in financial instruments rules, the European markets in crypto-assets rules and the Travel Rule, and each jurisdiction has its own adapter settings. They do not by themselves make a deployment compliant.</p>
                            <p class="text-sm text-gray-500">11 routes &middot; <a href="/api/documentation#/RegTech" target="_blank" class="text-amber-600 hover:text-amber-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Submit a Report</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/reports</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint builds a report for a jurisdiction and sends it to the configured reporting adapter. The supported jurisdictions are the United States, the European Union, the United Kingdom and Singapore. The request uses the codes US, EU, UK and SG.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "report_type": "transaction_report",
    "jurisdiction": "EU",
    "report_data": {"transactions": []}
  }' \
  {{ config('app.url') }}/api/regtech/reports
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Check a Crypto-Asset White Paper</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/mica/whitepaper/validate</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint checks a crypto-asset white paper against a list modelled on the European markets in crypto-assets rules.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "issuer_legal_name": "Example Issuer",
    "publication_date": "2026-01-15",
    "sections": ["issuer", "rights", "risks"]
  }' \
  {{ config('app.url') }}/api/regtech/mica/whitepaper/validate
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Travel Rule Check</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/travel-rule/check</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint tells you which sender and recipient details a transfer needs. The data-exchange module is modelled on the Travel Rule of the Financial Action Task Force.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2500,
    "currency": "EUR",
    "originator": {"name": "Alice Example"},
    "beneficiary": {"name": "Bob Example"}
  }' \
  {{ config('app.url') }}/api/regtech/travel-rule/check
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Compliance Summary</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/regtech/compliance/summary</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the status of the configured reporting modules and adapters.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/regtech/compliance/summary
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- Mobile Payment API -->
                    <section id="mobile-payment" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Mobile Payment API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The mobile payment API serves the Zelta mobile wallet. It covers payment intents, receipts, the activity feed, receive addresses, non-custodial wallet sends and passkey sign-in.</p>
                            <p class="text-sm text-gray-500">More than 25 routes &middot; <a href="/api/documentation#/MobilePayment" target="_blank" class="text-violet-600 hover:text-violet-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Payment Intent</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/payments/intents</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint creates a payment intent for a merchant payment in USDC or USDT.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "merchantId": "merchant_123",
    "amount": 25.00,
    "asset": "USDC",
    "preferredNetwork": "SOLANA"
  }' \
  {{ config('app.url') }}/api/v1/payments/intents
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Activity Feed</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/activity</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the wallet activity feed with payments and transfers.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/activity
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Prepare a Wallet Send</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/wallet/transactions/prepare</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns an unsigned transaction. The device signs it and sends it to <code>/v1/wallet/transactions/submit</code>. The server never sees the private key.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 5f0c..." \
  -d '{"quote_id": "..."}' \
  {{ config('app.url') }}/api/v1/wallet/transactions/prepare
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Passkey Sign-In</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/v1/auth/passkey/authenticate</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint checks a passkey assertion and signs the user in without a password. Ask for a challenge first at <code>/v1/auth/passkey/challenge</code>.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Partner BaaS API -->
                    <section id="partner-baas" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Partner API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The Partner API serves institutions that build on the platform. It covers the partner profile, usage and billing, branding, embeddable widgets, the integration marketplace and the SDK generator. Each request carries the partner client ID and client secret in the <code>X-Partner-Client-Id</code> and <code>X-Partner-Client-Secret</code> headers.</p>
                            <p class="text-sm text-gray-500">27 routes &middot; <a href="/api/documentation#/Partner" target="_blank" class="text-rose-600 hover:text-rose-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Partner Profile</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/partner/v1/profile</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Credentials Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the partner profile and its tier.</p>
                                <x-code-block language="bash">
curl -H "X-Partner-Client-Id: your_client_id" \
     -H "X-Partner-Client-Secret: your_client_secret" \
     {{ config('app.url') }}/api/partner/v1/profile
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Generate a Starter SDK</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/partner/v1/sdk/generate</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Credentials Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint builds a starter client in one language from templates. It needs the Growth or Enterprise tier.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "X-Partner-Client-Id: your_client_id" \
  -H "X-Partner-Client-Secret: your_client_secret" \
  -H "Content-Type: application/json" \
  -d '{"language": "typescript"}' \
  {{ config('app.url') }}/api/partner/v1/sdk/generate
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Update Branding</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">PUT</span>
                                        <span class="ml-2 font-mono text-sm">/partner/v1/branding</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Credentials Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint updates the colours, logo and company name used in the partner widgets.</p>
                                <x-code-block language="bash">
curl -X PUT \
  -H "X-Partner-Client-Id: your_client_id" \
  -H "X-Partner-Client-Secret: your_client_secret" \
  -H "Content-Type: application/json" \
  -d '{"company_name": "Acme Finance", "primary_color": "#1E40AF"}' \
  {{ config('app.url') }}/api/partner/v1/branding
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Usage Summary</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/partner/v1/usage</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block bg-rose-100 text-rose-800 text-xs font-medium px-2.5 py-0.5 rounded">Partner Credentials Required</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the API usage for the current billing period.</p>
                                <x-code-block language="bash">
curl -H "X-Partner-Client-Id: your_client_id" \
     -H "X-Partner-Client-Secret: your_client_secret" \
     {{ config('app.url') }}/api/partner/v1/usage
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- SMS API -->
                    <section id="sms" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">SMS API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The SMS API sends text messages through VertexSMS. Each message is paid for separately through the Machine Payments Protocol, so you need no prepaid credit and no invoice. The protocol offers several payment rails, such as USDC, Stripe and Lightning. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                            <p class="text-sm text-gray-500">4 routes &middot; <a href="/api/documentation#/SMS" target="_blank" class="text-orange-600 hover:text-orange-800">View in Swagger UI</a></p>
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
                                <p class="text-gray-600 mb-4">This endpoint sends a text message to a phone number. The first request returns <code>402 Payment Required</code> with the payment rails and the price. After you pay, the message is sent and you get a receipt.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint returns the price of one message to a country, given as a two-letter ISO 3166-1 country code. The rate comes from VertexSMS in euros. It is converted to USDC in its smallest unit, with six decimals, so 48000 means $0.048.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint returns the delivery status of a message you sent. You must be signed in. The status is <code>pending</code>, <code>sent</code>, <code>delivered</code> or <code>failed</code>.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint returns the service status, the active provider, whether test mode is on and the supported payment networks.</p>
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
                                <h3 class="text-xl font-semibold mb-4">MCP Tool: sms.send</h3>
                                <p class="text-gray-600 mb-4">AI agents can send text messages through the Model Context Protocol server. The <code>sms.send</code> tool costs a fixed amount per message, and that amount counts against the token's daily spending limit. The tool is off by default outside demo and test environments.</p>
                                <x-code-block language="json">
{
  "tool": "sms.send",
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
                            <p>The AI query API answers questions about your transactions. You ask in plain language, and the API turns the question into filters and returns the matching transactions.</p>
                            <p class="text-sm text-gray-500">2 routes &middot; <a href="/api/documentation#/AI" target="_blank" class="text-gray-600 hover:text-gray-800">View in Swagger UI</a></p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Transaction Query</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/ai/query/transactions</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint takes a question in plain language about your transactions.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"query": "What were my largest transactions last month?"}' \
  {{ config('app.url') }}/api/ai/query/transactions
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Spending Analysis</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/ai/query/spending-analysis</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint groups your spending by category, merchant and time.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{}' \
  {{ config('app.url') }}/api/ai/query/spending-analysis
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- GraphQL API -->
                    <section id="graphql" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">GraphQL API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The GraphQL API is built with Lighthouse PHP from a written schema. It offers queries and mutations for 43 domains. Subscriptions send live updates over WebSocket, and DataLoaders batch the database reads.</p>
                            <p class="text-sm text-gray-500">45 schema files for 43 domains &middot; Queries, Mutations, Subscriptions</p>
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
                                <p class="text-gray-600 mb-4">This endpoint runs a GraphQL query or mutation against the combined schema. The schema files are listed in <code>graphql/schema.graphql</code>.</p>
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
                                <h3 class="text-xl font-semibold mb-4">Exploring the Schema</h3>
                                <p class="text-gray-600 mb-4">The server does not host a GraphQL playground. You can send an introspection query from any GraphQL client to see the types, queries, mutations and subscriptions.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"query": "{ __schema { types { name } } }"}' \
  {{ config('app.url') }}/graphql
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 bg-sky-50 border-sky-200">
                                <h3 class="text-xl font-semibold mb-4">Available Subscriptions</h3>
                                <p class="text-gray-600 mb-4">These subscriptions send live updates over WebSocket.</p>
                                <ul class="list-disc list-inside text-gray-600 space-y-1">
                                    <li><code class="bg-gray-100 px-1">orderBookUpdated(pair: String!)</code> sends order book changes for a trading pair.</li>
                                    <li><code class="bg-gray-100 px-1">tradeExecuted(pair: String!)</code> sends each trade on a trading pair.</li>
                                    <li><code class="bg-gray-100 px-1">orderMatched(pair: String)</code> sends matched orders.</li>
                                    <li><code class="bg-gray-100 px-1">walletBalanceUpdated(wallet_id: ID!)</code> sends wallet balance changes.</li>
                                    <li><code class="bg-gray-100 px-1">portfolioRebalanced(portfolio_id: String)</code> sends treasury portfolio rebalancing.</li>
                                    <li><code class="bg-gray-100 px-1">paymentStatusChanged(payment_id: ID)</code> sends payment status changes.</li>
                                    <li><code class="bg-gray-100 px-1">bridgeTransferCompleted(transfer_id: ID)</code> sends completed bridge transfers.</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Event Streaming & Live Dashboard -->
                    <section id="event-streaming" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Event Streaming and Live Dashboard</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>Event streaming publishes domain events to Redis Streams. The live dashboard endpoints report on domain health, event throughput, stream status and how far each projector lags behind.</p>
                            <p class="text-sm text-gray-500">5 endpoints &middot; Redis Streams</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Dashboard Overview</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/monitoring/live-dashboard</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns all dashboard figures in one response.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/monitoring/live-dashboard
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Domain Health</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/monitoring/live-dashboard/domain-health</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the health of each event-sourced domain.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/monitoring/live-dashboard/domain-health
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Event Throughput</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/monitoring/live-dashboard/event-throughput</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns how many events each domain processes.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/monitoring/live-dashboard/event-throughput
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Stream Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/monitoring/live-dashboard/stream-status</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the state of the Redis Streams and their consumer groups.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/monitoring/live-dashboard/stream-status
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Projector Lag</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/v1/monitoring/live-dashboard/projector-lag</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint shows how far each read model projector is behind the latest events.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v1/monitoring/live-dashboard/projector-lag
                                </x-code-block>
                            </div>
                        </div>
                    </section>

                    <!-- ISO 20022 API -->
                    <section id="iso20022" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">ISO 20022 API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The ISO 20022 module reads, writes and checks ISO 20022 payment messages. It supports eight message types: pain.001, pain.008, pacs.008, pacs.002, pacs.003, pacs.004, camt.053 and camt.054. The checks cover well-formed XML and the required fields of each type. These modules are reference implementations at the message and logic layer. They run in demo mode, are switched off on the production site and have no live scheme connection or settlement.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Parse Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/parse</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint reads an ISO 20022 XML message and returns it as structured JSON.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"xml": "<Document>...</Document>"}' \
  {{ config('app.url') }}/api/v1/iso20022/parse
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Generate Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/generate</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint builds an ISO 20022 XML message from JSON data.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"message_type":"pacs.008","data":{"amount":"1000.00","currency":"EUR","debtor":"...","creditor":"..."}}' \
  {{ config('app.url') }}/api/v1/iso20022/generate
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Validate Message</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/iso20022/validate</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint checks that an ISO 20022 XML message is well formed and has the required fields for its type.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Open Banking API -->
                    <section id="open-banking" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Open Banking API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The open banking module manages consents for account information and payment initiation services. It is modelled on the European Union's second Payment Services Directive. It has adapters for the Berlin Group NextGenPSD2 standard and for the United Kingdom Open Banking standard. It also stores the certificates that third-party providers register with and checks that they are well formed. These modules are reference implementations at the message and logic layer. They run in demo mode, are switched off on the production site and have no live bank connections.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Consent</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/consents</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint creates a consent request for account access or payment initiation.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"tpp_id":"tpp_123","permissions":["ReadAccountsBasic","ReadBalances","ReadTransactionsBasic"]}' \
  {{ config('app.url') }}/api/v1/open-banking/consents
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Account Information</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/accounts</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint lists the accounts covered by an active account information consent.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Initiate Payment</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="font-mono text-sm">/api/v1/open-banking/payments</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint starts a payment under an active payment initiation consent.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Payment Rails API -->
                    <section id="payment-rails" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Payment Rails API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The payment rails module holds message formats and routing logic for United States and European rails. It covers Automated Clearing House files in the NACHA format, Fedwire, Real-Time Payments, FedNow, SEPA Direct Debit and SEPA Instant Credit Transfer. The router picks a rail from the amount, the currency, the country and the urgency. These modules are reference implementations at the message and logic layer. They run in demo mode, are switched off on the production site and have no live scheme connection or settlement.</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Send a Payment</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/payment-rails/send</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint sends a payment through the rail the router picks.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "500.00",
    "currency": "USD",
    "country": "US",
    "urgency": "normal",
    "beneficiary": {"name": "Acme Inc", "account_number": "...", "routing_number": "..."}
  }' \
  {{ config('app.url') }}/api/v1/payment-rails/send
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Supported Rails</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/payment-rails/supported/{country}</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint lists the rails available for a country.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Payment Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/payment-rails/transactions/{id}</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the status of a payment.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Interledger API -->
                    <section id="interledger" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Interledger API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The Interledger module moves value between networks. It includes an Interledger connector, Open Payments with authorisation through the Grant Negotiation and Authorization Protocol, and quotes between currencies. Only platform operators can use the quote, payment and connection endpoints. These modules are reference implementations at the message and logic layer. They run in demo mode, are switched off on the production site and have no live scheme connection or settlement.</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Get Quote</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/interledger/quotes</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns a quote between two currencies, including fees and the exchange rate.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"send_asset": "USD", "receive_asset": "EUR", "send_amount": 100.00}' \
  {{ config('app.url') }}/api/v1/interledger/quotes
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Send Payment</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/interledger/payments/outgoing</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint sends an Interledger payment with a quote you got earlier. Incoming payments use <code>/api/v1/interledger/payments/incoming</code>.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Ledger API -->
                    <section id="ledger" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Ledger API</h2>
                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The ledger module does double-entry accounting. It keeps a chart of accounts, posts journal entries, produces trial balances and applies posting rules to the general ledger. An optional TigerBeetle driver handles very high volumes.</p>
                        </div>
                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Chart of Accounts</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/ledger/accounts</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the chart of accounts.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint posts a journal entry with at least two lines. The debits must equal the credits.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Trial Balance</h3>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                    <span class="font-mono text-sm">/api/v1/ledger/trial-balance</span>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns a trial balance.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Microfinance API -->
                    <section id="microfinance" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Microfinance API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The microfinance module supports lending to small groups. It covers groups with joint liability, loan provisioning by days overdue, cooperative share accounts, teller cash work, field officer tools and savings products that track dormant accounts. These modules are reference implementations at the message and logic layer. They run in demo mode, are switched off on the production site and have no live scheme connection or settlement.</p>
                        </div>

                        <div class="space-y-8">
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Create Loan Group</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/microfinance/groups</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint creates a lending group. Members are added afterwards at <code>/api/v1/microfinance/groups/{id}/members</code>.</p>
                                <x-code-block language="bash">
curl -X POST \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"name": "Village Savings Group A", "frequency": "weekly", "meeting_day": "Tuesday"}' \
  {{ config('app.url') }}/api/v1/microfinance/groups
                                </x-code-block>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Loan Provisioning</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">GET</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/microfinance/provisioning</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint returns the loan loss provisions. Each loan falls into a category by how many days it is overdue, and each category has its own rate.</p>
                            </div>
                            <div class="border rounded-lg p-6">
                                <h3 class="text-xl font-semibold mb-4">Share Accounts</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                        <span class="ml-2 font-mono text-sm">/api/v1/microfinance/share-accounts</span>
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-4">This endpoint opens a cooperative share account for a member. Members buy and redeem shares through the purchase and redeem endpoints.</p>
                            </div>
                        </div>
                    </section>

                    <!-- x402 Protocol API -->
                    <section id="x402" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">x402 Protocol API</h2>

                        <div class="prose prose-lg max-w-none mb-8">
                            <p>The x402 protocol lets an API charge small payments in USDC over plain HTTP. A paid endpoint answers an unpaid request with HTTP 402 and the payment terms. AI agents can pay on their own within spending limits, and payments can settle on several networks.</p>
                            <p class="text-sm text-gray-500">15 endpoints &middot; <a href="/api/documentation#/X402" target="_blank" class="text-emerald-600 hover:text-emerald-800">View in Swagger UI</a></p>
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
                                <p class="text-gray-600 mb-4">This endpoint returns the x402 status, the supported networks and assets, and whether the facilitator is reachable.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint lists the networks and assets that x402 payments can settle on.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint registers an API endpoint as paid and sets its price.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint returns the x402 payment history and the settlement status of each payment.</p>
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
                                <p class="text-gray-600 mb-4">This endpoint shows and manages the spending limits for automatic x402 payments, including AI agent budgets.</p>
                                <x-code-block language="bash">
curl -H "Authorization: Bearer your_api_key" \
     {{ config('app.url') }}/api/v2/x402/spending-limits
                                </x-code-block>
                            </div>

                            <div class="border rounded-lg p-6 bg-emerald-50 border-emerald-200">
                                <h3 class="text-xl font-semibold mb-4">Handling 402 Responses</h3>
                                <p class="text-gray-600 mb-4">A client that calls a paid endpoint without paying gets HTTP 402. The <code>PAYMENT-REQUIRED</code> header holds the payment terms, encoded in base64. The client signs a payment and sends it back in the <code>PAYMENT-SIGNATURE</code> header. After settlement, the response carries a <code>PAYMENT-RESPONSE</code> header.</p>
                                <x-code-block language="plaintext">
HTTP/1.1 402 Payment Required
PAYMENT-REQUIRED: eyJ4NDAyVmVyc2lvbiI6Mi...  (base64-encoded payment terms)
X-Payment-Protocol: x402
X-Payment-Version: 2
</x-code-block>

                                <h4 class="font-semibold mb-2 mt-6">JavaScript Client Example:</h4>
                                <x-code-block language="javascript">
// /api/v1/paid/data stands for any endpoint you registered as paid
const response = await fetch('{{ config('app.url') }}/api/v1/paid/data', {
  headers: { 'Authorization': 'Bearer YOUR_API_KEY' }
});

if (response.status === 402) {
  // Decode the payment terms
  const terms = JSON.parse(atob(response.headers.get('PAYMENT-REQUIRED')));

  // Sign a payment that matches the terms, then retry with it
  const signed = await signPayment(terms);
  const paid = await fetch('{{ config('app.url') }}/api/v1/paid/data', {
    headers: {
      'Authorization': 'Bearer YOUR_API_KEY',
      'PAYMENT-SIGNATURE': signed
    }
  });
  const data = await paid.json(); // 200 and the data
}
</x-code-block>
                            </div>
                        </div>
                    </section>

                    <section id="rate-limits" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Rate Limits</h2>
                        <div class="prose prose-lg max-w-none mb-6">
                            <p>Every API request is rate limited. Each type of endpoint has its own limit per user in a 60-second window. These are the default values, and an operator can change them.</p>
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
                                    <tr><td class="px-4 py-3 text-gray-700">Read requests</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">100 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Transactions</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">30 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Sign-in</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">3 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Public endpoints</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">60 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Administration</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">200 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">Incoming webhooks</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">1,000 requests/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">GraphQL, signed in</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">120 queries/minute</code></td></tr>
                                    <tr><td class="px-4 py-3 text-gray-700">GraphQL, guest</td><td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded">30 queries/minute</code></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Rate-limited responses carry these headers.</p>
                            <div class="space-y-1 font-mono text-sm text-gray-600">
                                <div><code>X-RateLimit-Limit</code> is the number of requests allowed in the window.</div>
                                <div><code>X-RateLimit-Remaining</code> is the number of requests left in the current window.</div>
                                <div><code>X-RateLimit-Reset</code> is the Unix time when the window starts again.</div>
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
                                <li><a href="#crosschain" class="text-cyan-600 hover:text-cyan-800 flex justify-between"><span>CrossChain</span><span class="text-gray-400">6 routes</span></a></li>
                                <li><a href="#defi" class="text-emerald-600 hover:text-emerald-800 flex justify-between"><span>Decentralised Finance</span><span class="text-gray-400">8 routes</span></a></li>
                                <li><a href="#regtech" class="text-amber-600 hover:text-amber-800 flex justify-between"><span>RegTech</span><span class="text-gray-400">11 routes</span></a></li>
                                <li><a href="#mobile-payment" class="text-violet-600 hover:text-violet-800 flex justify-between"><span>Mobile Payment</span><span class="text-gray-400">More than 25 routes</span></a></li>
                                <li><a href="#partner-baas" class="text-rose-600 hover:text-rose-800 flex justify-between"><span>Partner API</span><span class="text-gray-400">27 routes</span></a></li>
                                <li><a href="#ai" class="text-gray-600 hover:text-gray-800 flex justify-between"><span>AI Query</span><span class="text-gray-400">2 routes</span></a></li>
                                <li><a href="#graphql" class="text-sky-600 hover:text-sky-800 flex justify-between"><span>GraphQL</span><span class="text-gray-400">43 domains</span></a></li>
                                <li><a href="#event-streaming" class="text-lime-600 hover:text-lime-800 flex justify-between"><span>Event Streaming</span><span class="text-gray-400">5 endpoints</span></a></li>
                                <li><a href="#x402" class="text-emerald-600 hover:text-emerald-800 flex justify-between"><span>x402 Protocol</span><span class="text-gray-400">15 endpoints</span></a></li>
                                <li><a href="#iso20022" class="text-blue-600 hover:text-blue-800 flex justify-between"><span>ISO 20022</span><span class="text-gray-400">8 msg types</span></a></li>
                                <li><a href="#open-banking" class="text-teal-600 hover:text-teal-800 flex justify-between"><span>Open Banking</span><span class="text-gray-400">Consents and payments</span></a></li>
                                <li><a href="#payment-rails" class="text-orange-600 hover:text-orange-800 flex justify-between"><span>Payment Rails</span><span class="text-gray-400">ACH/Fedwire/SEPA</span></a></li>
                                <li><a href="#interledger" class="text-purple-600 hover:text-purple-800 flex justify-between"><span>Interledger</span><span class="text-gray-400">Quotes and payments</span></a></li>
                                <li><a href="#ledger" class="text-slate-600 hover:text-slate-800 flex justify-between"><span>Ledger</span><span class="text-gray-400">Double-entry</span></a></li>
                                <li><a href="#microfinance" class="text-green-600 hover:text-green-800 flex justify-between"><span>Microfinance</span><span class="text-gray-400">Group lending</span></a></li>
                            </ul>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-900 mb-4">OpenAPI Specification</h3>
                            <p class="text-blue-800 mb-4">You can download the OpenAPI specification or open it in the interactive explorer.</p>
                            <div class="flex gap-3">
                                <a href="/docs/api-docs.json" download="zelta-api-v2.json" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                    Download OpenAPI JSON
                                </a>
                                <a href="/api/documentation" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                    Interactive API Explorer
                                </a>
                            </div>
                            <p class="text-xs text-gray-600 mt-3">You can import the JSON file into Postman, Insomnia or any tool that reads OpenAPI.</p>
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