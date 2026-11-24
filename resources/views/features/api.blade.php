@extends('layouts.public')

@section('title', 'Developer APIs - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Developer APIs',
        'description' => 'The platform has REST APIs, GraphQL, webhooks and official client libraries for PHP, JavaScript and Python. Developers use them to build applications on the platform.',
        'keywords' => 'API, REST API, webhooks, developer tools, integration, ' . config('brand.name', 'Zelta') . ' API',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Developer APIs', 'url' => url('/features/api')]
    ]" />
@endsection

@push('styles')
<style>
    .code-block {
        background: #1a202c;
        color: #e2e8f0;
        border-radius: 0.5rem;
        padding: 1.5rem;
        overflow-x: auto;
        font-family: 'Fira Code', monospace;
    }
    .endpoint-card {
        transition: all 0.3s ease;
    }
    .endpoint-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Developer APIs</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    {{ config('brand.name', 'Zelta') }} has REST APIs, webhooks and official client libraries. You use them to connect your own applications to the platform.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Overview Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">REST APIs</h3>
                    <p class="text-slate-500">The REST endpoints follow one consistent pattern. Each one is documented with examples.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Webhooks</h3>
                    <p class="text-slate-500">Webhooks tell your system when an important event happens, such as a completed transaction.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Client Libraries</h3>
                    <p class="text-slate-500">Official client libraries exist for PHP, JavaScript and Python. A separate PHP payment library handles x402 and machine payments.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- API Categories -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">API Categories</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Authentication -->
                <div class="endpoint-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Authentication & Users</h3>
                    <p class="text-slate-500 mb-6">These endpoints sign users in and manage their profiles.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/auth/register</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/auth/login</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/auth/user</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="endpoint-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Account Management</h3>
                    <p class="text-slate-500 mb-6">These endpoints create and manage accounts. Each account can hold several assets.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/accounts</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/accounts</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/accounts/{uuid}/balances</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="endpoint-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Transactions & Transfers</h3>
                    <p class="text-slate-500 mb-6">These endpoints run payments and transfers.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/transfers</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/transactions</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/accounts/{uuid}/transactions/reverse</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GCU & Voting -->
                <div class="endpoint-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">GCU demo (simulated)</h3>
                    <p class="text-slate-500 mb-6">These endpoints serve the GCU demo. Balances and votes are simulated.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/v2/gcu</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/voting/polls/{id}/vote</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Code Examples -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Quick Start Examples</h2>
            
            <div class="max-w-4xl mx-auto space-y-8">
                <!-- Authentication Example -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Authentication</h3>
                    <div class="code-block">
                        <pre><code>// Login and get access token
const response = await fetch('{{ config('app.url') }}/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'secure_password'
  })
});

const { access_token } = await response.json();

// Use token for authenticated requests
const headers = {
  'Authorization': `Bearer ${access_token}`,
  'Content-Type': 'application/json'
};</code></pre>
                    </div>
                </div>

                <!-- Transfer Example -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Create Transfer</h3>
                    <div class="code-block">
                        <pre><code>// Transfer between accounts
const transfer = await fetch('{{ config('app.url') }}/api/transfers', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${access_token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    from_account: 'acc_123456',
    to_account: 'acc_789012',
    amount: 100.00,
    currency: 'EUR',
    description: 'Payment for services'
  })
});

const result = await transfer.json();
console.log('Transfer ID:', result.id);
console.log('Status:', result.status);</code></pre>
                    </div>
                </div>

                <!-- Webhook Example -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Webhook Handler</h3>
                    <div class="code-block">
                        <pre><code>// Express.js webhook handler
app.post('/webhooks/finaegis', (req, res) => {
  const signature = req.headers['x-webhook-signature'];
  const payload = req.body;
  
  // Verify webhook signature
  if (!verifyWebhookSignature(payload, signature)) {
    return res.status(401).send('Invalid signature');
  }
  
  // Handle different event types
  switch (payload.event_type) {
    case 'transaction.completed':
      handleTransactionCompleted(payload.data);
      break;
    case 'account.created':
      handleAccountCreated(payload.data);
      break;
    // Handle other events...
  }
  
  res.status(200).send('OK');
});</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">API Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Technical Features</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>REST design with predictable endpoints</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>JSON request/response format</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Token and OAuth 2.0 authentication</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Rate limits shown in response headers</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Idempotency keys, so a retry does not repeat a payment</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Consistent error responses</span>
                        </li>
                    </ul>
                </div>
                
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Developer Experience</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Interactive API documentation</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Sandbox environment for testing</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Code examples in multiple languages</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Postman collection available</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>API changelog and versioning</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Community support on GitHub</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- SDKs -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Official Client Libraries</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-purple-600">PHP</span>
                    </div>
                    <h4 class="font-semibold">PHP Library</h4>
                    <p class="text-sm text-slate-500 mt-1">Composer package</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-yellow-600">JS</span>
                    </div>
                    <h4 class="font-semibold">JavaScript Library</h4>
                    <p class="text-sm text-slate-500 mt-1">npm package</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-blue-600">Py</span>
                    </div>
                    <h4 class="font-semibold">Python Library</h4>
                    <p class="text-sm text-slate-500 mt-1">PyPI package</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-red-600">Pay</span>
                    </div>
                    <h4 class="font-semibold">Payment Library</h4>
                    <p class="text-sm text-slate-500 mt-1">Composer package</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Connect Your Application</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Create a sandbox account to get API keys. You can then connect your application to {{ config('brand.name', 'Zelta') }} with test data.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get API Keys
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    View Documentation
                </a>
            </div>
        </div>
    </section>

@endsection