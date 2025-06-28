<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        API Documentation
                    </h1>
                    <p class="mt-6 text-xl text-gray-300 max-w-3xl mx-auto">
                        Complete reference documentation for the FinAegis REST API with interactive examples and code samples.
                    </p>
                </div>
            </div>
        </div>

        <!-- API Navigation -->
        <div class="bg-gray-50 border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex space-x-8 py-4">
                    <a href="#getting-started" class="text-blue-600 font-medium">Getting Started</a>
                    <a href="#authentication" class="text-gray-600 hover:text-gray-900">Authentication</a>
                    <a href="#accounts" class="text-gray-600 hover:text-gray-900">Accounts</a>
                    <a href="#transactions" class="text-gray-600 hover:text-gray-900">Transactions</a>
                    <a href="#transfers" class="text-gray-600 hover:text-gray-900">Transfers</a>
                    <a href="#gcu" class="text-gray-600 hover:text-gray-900">GCU</a>
                    <a href="#assets" class="text-gray-600 hover:text-gray-900">Assets</a>
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
                            <p>The FinAegis API provides programmatic access to our multi-asset banking platform. Our API is organized around REST principles with predictable, resource-oriented URLs.</p>
                            
                            <h3>Base URL</h3>
                            <div class="bg-gray-100 rounded-lg p-4 font-mono text-sm">
                                https://api.finaegis.org/v1
                            </div>
                            
                            <h3>Response Format</h3>
                            <p>All API responses are returned in JSON format with a consistent structure:</p>
                            
                            <div class="bg-gray-900 rounded-lg p-6 overflow-x-auto">
                                <pre class="text-green-400 text-sm"><code>{
  "data": { ... },          // Main response data
  "meta": { ... },          // Metadata (pagination, etc.)
  "links": { ... },         // Related links
  "errors": [ ... ]         // Error details (if any)
}</code></pre>
                            </div>
                        </div>
                    </section>

                    <section id="authentication" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Authentication</h2>
                        
                        <div class="prose prose-lg max-w-none">
                            <p>The FinAegis API uses API keys to authenticate requests. You can generate and manage your API keys in the developer dashboard.</p>
                            
                            <h3>API Key Authentication</h3>
                            <p>Include your API key in the Authorization header:</p>
                            
                            <div class="bg-gray-900 rounded-lg p-6 overflow-x-auto">
                                <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key_here" \
     -H "Content-Type: application/json" \
     https://api.finaegis.org/v1/accounts</code></pre>
                            </div>
                            
                            <h3>Sandbox vs Production</h3>
                            <p>Use these base URLs for testing and production:</p>
                            <ul>
                                <li><strong>Sandbox:</strong> https://api-sandbox.finaegis.org/v1</li>
                                <li><strong>Production:</strong> https://api.finaegis.org/v1</li>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key" \
     https://api.finaegis.org/v1/accounts</code></pre>
                                </div>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key" \
     https://api.finaegis.org/v1/accounts/acct_1234567890</code></pre>
                                </div>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>{
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
}</code></pre>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="transfers" class="mb-16">
                        <h2 class="text-3xl font-bold text-gray-900 mb-8">Transfers</h2>
                        
                        <div class="border rounded-lg p-6">
                            <h3 class="text-xl font-semibold mb-4">Create Transfer</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">POST</span>
                                    <span class="ml-2 font-mono text-sm">/transfers</span>
                                </div>
                            </div>
                            
                            <p class="text-gray-600 mb-4">Create a new transfer between accounts or to external recipients.</p>
                            
                            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-green-400 text-sm"><code>curl -X POST \
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
  https://api.finaegis.org/v1/transfers</code></pre>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key" \
     https://api.finaegis.org/v2/gcu</code></pre>
                                </div>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto mb-4">
                                    <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key" \
     https://api.finaegis.org/v2/gcu/composition</code></pre>
                                </div>
                                
                                <h4 class="font-semibold mb-2">Response Example:</h4>
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>{
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
}</code></pre>
                                </div>
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
                                
                                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                    <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer your_api_key" \
     "https://api.finaegis.org/v2/gcu/value-history?period=7d&interval=hourly"</code></pre>
                                </div>
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

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-900 mb-4">Interactive API Explorer</h3>
                            <p class="text-blue-800 mb-4">Try our API endpoints directly in your browser with our interactive documentation.</p>
                            <a href="/docs/api-docs.json" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                Open API Explorer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>