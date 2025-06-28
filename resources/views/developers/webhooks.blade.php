<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Webhooks - Real-time event notifications for your application. Get instant updates on transactions, accounts, and workflows.">
        <meta name="keywords" content="FinAegis, webhooks, real-time, notifications, API, events">
        
        <title>Webhooks - FinAegis Developer Documentation</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .code-font {
                font-family: 'Fira Code', monospace;
            }
            .webhook-gradient {
                background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            }
            .code-block {
                font-family: 'Fira Code', monospace;
                font-size: 0.875rem;
                line-height: 1.5;
                overflow-x: auto;
                white-space: pre;
            }
            @keyframes ping {
                75%, 100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            .animate-ping-slow {
                animation: ping 3s cubic-bezier(0, 0, 0.2, 1) infinite;
            }
            .floating-card {
                animation: float 6s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-20px); }
            }
        </style>
    </head>
    <body class="antialiased">
        <x-alpha-banner />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 webhook-gradient text-white relative overflow-hidden">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0">
                <div class="absolute top-20 left-10 w-72 h-72 bg-yellow-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
                <div class="absolute top-40 right-10 w-72 h-72 bg-red-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
                <div class="absolute -bottom-8 left-20 w-72 h-72 bg-orange-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                        <span>Real-time Event System</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6">
                        Webhooks
                    </h1>
                    <p class="text-xl md:text-2xl text-yellow-100 max-w-3xl mx-auto">
                        Real-time notifications for account events, transaction updates, and workflow completions delivered directly to your application.
                    </p>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">How Webhooks Work</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Never miss an important event. Get instant HTTP POST notifications when things happen in your FinAegis account.
                    </p>
                </div>

                <!-- Flow Diagram -->
                <div class="relative mb-16">
                    <div class="hidden md:block absolute top-1/2 left-0 right-0 h-0.5 bg-gradient-to-r from-yellow-200 via-orange-200 to-red-200 transform -translate-y-1/2"></div>
                    
                    <div class="grid md:grid-cols-4 gap-8 relative">
                        @php
                            $steps = [
                                ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'yellow', 'title' => 'Event Occurs', 'description' => 'Transaction completes or account changes'],
                                ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'orange', 'title' => 'Webhook Triggered', 'description' => 'Event queued for delivery'],
                                ['icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'color' => 'red', 'title' => 'POST Request Sent', 'description' => 'Secure HTTPS delivery to your endpoint'],
                                ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green', 'title' => 'You Respond', 'description' => 'Process event and return 200 OK']
                            ];
                        @endphp

                        @foreach($steps as $index => $step)
                        <div class="text-center relative">
                            <div class="relative inline-block">
                                <div class="w-20 h-20 bg-{{ $step['color'] }}-100 rounded-full flex items-center justify-center relative z-10">
                                    <svg class="w-10 h-10 text-{{ $step['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                                    </svg>
                                </div>
                                @if($index === 0)
                                <div class="absolute inset-0 bg-{{ $step['color'] }}-400 rounded-full animate-ping-slow"></div>
                                @endif
                            </div>
                            <h3 class="text-lg font-semibold mt-4 mb-2">{{ $step['title'] }}</h3>
                            <p class="text-gray-600 text-sm">{{ $step['description'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Quick Setup -->
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-3xl p-8 text-center">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Quick Setup</h3>
                    <div class="code-font bg-gray-900 text-green-400 rounded-lg p-4 max-w-3xl mx-auto text-left">
                        <div class="mb-2">$ curl -X POST https://api.finaegis.org/v1/webhooks \</div>
                        <div class="ml-4">-H "Authorization: Bearer YOUR_API_KEY" \</div>
                        <div class="ml-4">-d '{"url": "https://yourapp.com/webhooks", "events": ["*"]}'</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Event Types -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Available Events</h2>
                    <p class="text-xl text-gray-600">Subscribe to exactly what you need</p>
                </div>

                <div class="grid lg:grid-cols-2 gap-8">
                    @php
                        $eventCategories = [
                            [
                                'title' => 'Account Events',
                                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                                'color' => 'blue',
                                'events' => [
                                    ['name' => 'account.created', 'desc' => 'New account opened'],
                                    ['name' => 'account.updated', 'desc' => 'Account details changed'],
                                    ['name' => 'account.balance_updated', 'desc' => 'Balance changed'],
                                    ['name' => 'account.frozen', 'desc' => 'Account frozen'],
                                    ['name' => 'account.unfrozen', 'desc' => 'Account unfrozen'],
                                    ['name' => 'account.closed', 'desc' => 'Account closed']
                                ]
                            ],
                            [
                                'title' => 'Transfer Events',
                                'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
                                'color' => 'green',
                                'events' => [
                                    ['name' => 'transfer.created', 'desc' => 'Transfer initiated'],
                                    ['name' => 'transfer.pending', 'desc' => 'Transfer processing'],
                                    ['name' => 'transfer.completed', 'desc' => 'Transfer successful'],
                                    ['name' => 'transfer.failed', 'desc' => 'Transfer failed'],
                                    ['name' => 'transfer.reversed', 'desc' => 'Transfer reversed'],
                                    ['name' => 'transfer.expired', 'desc' => 'Transfer expired']
                                ]
                            ],
                            [
                                'title' => 'Workflow Events',
                                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                                'color' => 'purple',
                                'events' => [
                                    ['name' => 'workflow.started', 'desc' => 'Workflow initiated'],
                                    ['name' => 'workflow.step_completed', 'desc' => 'Step finished'],
                                    ['name' => 'workflow.completed', 'desc' => 'Workflow finished'],
                                    ['name' => 'workflow.failed', 'desc' => 'Workflow failed'],
                                    ['name' => 'workflow.timeout', 'desc' => 'Workflow timed out'],
                                    ['name' => 'workflow.compensation_executed', 'desc' => 'Rollback completed']
                                ]
                            ],
                            [
                                'title' => 'System Events',
                                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                                'color' => 'yellow',
                                'events' => [
                                    ['name' => 'system.maintenance_start', 'desc' => 'Maintenance begins'],
                                    ['name' => 'system.maintenance_end', 'desc' => 'Maintenance ends'],
                                    ['name' => 'system.rate_limit_warning', 'desc' => 'Approaching rate limit'],
                                    ['name' => 'system.api_key_expiring', 'desc' => 'API key expiring soon'],
                                    ['name' => 'system.security_alert', 'desc' => 'Security event detected'],
                                    ['name' => 'system.status_change', 'desc' => 'System status changed']
                                ]
                            ]
                        ];
                    @endphp

                    @foreach($eventCategories as $category)
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="bg-gradient-to-r from-{{ $category['color'] }}-500 to-{{ $category['color'] }}-600 p-6">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $category['icon'] }}"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold text-white">{{ $category['title'] }}</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                @foreach($category['events'] as $event)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                    <code class="code-font text-sm bg-gray-100 px-3 py-1 rounded text-{{ $category['color'] }}-700">{{ $event['name'] }}</code>
                                    <span class="text-sm text-gray-600">{{ $event['desc'] }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Implementation Guide -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Implementation Guide</h2>
                    <p class="text-xl text-gray-600">Get up and running in minutes</p>
                </div>

                <div class="grid lg:grid-cols-2 gap-12">
                    <!-- Setup Steps -->
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-8">1. Create Endpoint</h3>
                        
                        <div class="bg-gray-900 rounded-xl p-6 mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm text-gray-400 code-font">create_webhook.sh</span>
                                <button class="text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <pre class="code-block text-green-400"><code>curl -X POST https://api.finaegis.org/v1/webhooks \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://yourapp.com/webhooks/finaegis",
    "events": [
      "transfer.completed",
      "transfer.failed",
      "account.balance_updated"
    ],
    "secret": "whsec_your_webhook_secret_key",
    "active": true
  }'</code></pre>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-8">2. Handle Events</h3>
                        
                        <div class="bg-gray-900 rounded-xl p-6">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm text-gray-400 code-font">webhook_handler.js</span>
                                <button class="text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <pre class="code-block"><code><span class="text-purple-400">const</span> <span class="text-blue-400">express</span> = <span class="text-green-400">require</span>(<span class="text-amber-400">'express'</span>);
<span class="text-purple-400">const</span> <span class="text-blue-400">app</span> = <span class="text-green-400">express</span>();

<span class="text-white">app</span>.<span class="text-green-400">post</span>(<span class="text-amber-400">'/webhooks/finaegis'</span>, (<span class="text-orange-400">req</span>, <span class="text-orange-400">res</span>) => {
  <span class="text-purple-400">const</span> { <span class="text-white">event_type</span>, <span class="text-white">data</span> } = <span class="text-orange-400">req</span>.<span class="text-white">body</span>;
  
  <span class="text-gray-400">// Verify webhook signature</span>
  <span class="text-purple-400">if</span> (!<span class="text-green-400">verifySignature</span>(<span class="text-orange-400">req</span>)) {
    <span class="text-purple-400">return</span> <span class="text-orange-400">res</span>.<span class="text-green-400">status</span>(<span class="text-amber-400">401</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'Invalid signature'</span>);
  }
  
  <span class="text-purple-400">switch</span> (<span class="text-white">event_type</span>) {
    <span class="text-purple-400">case</span> <span class="text-amber-400">'transfer.completed'</span>:
      <span class="text-green-400">handleTransferCompleted</span>(<span class="text-white">data</span>);
      <span class="text-purple-400">break</span>;
    <span class="text-purple-400">case</span> <span class="text-amber-400">'account.balance_updated'</span>:
      <span class="text-green-400">handleBalanceUpdate</span>(<span class="text-white">data</span>);
      <span class="text-purple-400">break</span>;
  }
  
  <span class="text-orange-400">res</span>.<span class="text-green-400">status</span>(<span class="text-amber-400">200</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'OK'</span>);
});</code></pre>
                        </div>
                    </div>

                    <!-- Security & Verification -->
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-8">3. Verify Signatures</h3>
                        
                        <div class="bg-gray-900 rounded-xl p-6 mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm text-gray-400 code-font">signature_verification.js</span>
                                <button class="text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <pre class="code-block"><code><span class="text-purple-400">const</span> <span class="text-blue-400">crypto</span> = <span class="text-green-400">require</span>(<span class="text-amber-400">'crypto'</span>);

<span class="text-purple-400">function</span> <span class="text-green-400">verifySignature</span>(<span class="text-orange-400">req</span>) {
  <span class="text-purple-400">const</span> <span class="text-white">signature</span> = <span class="text-orange-400">req</span>.<span class="text-white">headers</span>[<span class="text-amber-400">'x-finaegis-signature'</span>];
  <span class="text-purple-400">const</span> <span class="text-white">timestamp</span> = <span class="text-orange-400">req</span>.<span class="text-white">headers</span>[<span class="text-amber-400">'x-finaegis-timestamp'</span>];
  <span class="text-purple-400">const</span> <span class="text-white">payload</span> = <span class="text-amber-400">`${timestamp}.${JSON.stringify(req.body)}`</span>;
  
  <span class="text-purple-400">const</span> <span class="text-white">expectedSignature</span> = <span class="text-blue-400">crypto</span>
    .<span class="text-green-400">createHmac</span>(<span class="text-amber-400">'sha256'</span>, <span class="text-white">process</span>.<span class="text-white">env</span>.<span class="text-white">WEBHOOK_SECRET</span>)
    .<span class="text-green-400">update</span>(<span class="text-white">payload</span>)
    .<span class="text-green-400">digest</span>(<span class="text-amber-400">'hex'</span>);
  
  <span class="text-purple-400">return</span> <span class="text-white">signature</span> === <span class="text-amber-400">`sha256=${expectedSignature}`</span>;
}</code></pre>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-8">4. Handle Retries</h3>
                        
                        <div class="bg-gray-900 rounded-xl p-6">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm text-gray-400 code-font">idempotency_handler.js</span>
                                <button class="text-gray-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <pre class="code-block"><code><span class="text-purple-400">const</span> <span class="text-blue-400">processedEvents</span> = <span class="text-purple-400">new</span> <span class="text-green-400">Map</span>();

<span class="text-purple-400">async function</span> <span class="text-green-400">handleWebhook</span>(<span class="text-orange-400">req</span>, <span class="text-orange-400">res</span>) {
  <span class="text-purple-400">const</span> <span class="text-white">eventId</span> = <span class="text-orange-400">req</span>.<span class="text-white">headers</span>[<span class="text-amber-400">'x-finaegis-event-id'</span>];
  
  <span class="text-gray-400">// Check if already processed</span>
  <span class="text-purple-400">if</span> (<span class="text-blue-400">processedEvents</span>.<span class="text-green-400">has</span>(<span class="text-white">eventId</span>)) {
    <span class="text-purple-400">return</span> <span class="text-orange-400">res</span>.<span class="text-green-400">status</span>(<span class="text-amber-400">200</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'Already processed'</span>);
  }
  
  <span class="text-gray-400">// Process event</span>
  <span class="text-purple-400">await</span> <span class="text-green-400">processWebhookEvent</span>(<span class="text-orange-400">req</span>.<span class="text-white">body</span>);
  
  <span class="text-gray-400">// Mark as processed</span>
  <span class="text-blue-400">processedEvents</span>.<span class="text-green-400">set</span>(<span class="text-white">eventId</span>, <span class="text-purple-400">true</span>);
  
  <span class="text-gray-400">// Clean up old entries after 24 hours</span>
  <span class="text-green-400">setTimeout</span>(() => {
    <span class="text-blue-400">processedEvents</span>.<span class="text-green-400">delete</span>(<span class="text-white">eventId</span>);
  }, <span class="text-amber-400">24</span> * <span class="text-amber-400">60</span> * <span class="text-amber-400">60</span> * <span class="text-amber-400">1000</span>);
  
  <span class="text-orange-400">res</span>.<span class="text-green-400">status</span>(<span class="text-amber-400">200</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'OK'</span>);
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Payload Examples -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Example Payloads</h2>
                    <p class="text-xl text-gray-600">See what you'll receive</p>
                </div>

                <div class="space-y-8">
                    @php
                        $payloadExamples = [
                            [
                                'title' => 'Transfer Completed',
                                'color' => 'green',
                                'payload' => '{
  "event_id": "evt_abc123def456",
  "event_type": "transfer.completed",
  "created_at": "2025-01-01T12:00:00Z",
  "data": {
    "uuid": "txfr_xyz789abc123",
    "from_account": "acct_sender123",
    "to_account": "acct_receiver456",
    "amount": "100.00",
    "asset_code": "USD",
    "status": "completed",
    "reference": "Payment for services",
    "workflow_id": "wf_workflow123",
    "fees": {
      "transfer_fee": "0.50",
      "currency": "USD"
    },
    "completed_at": "2025-01-01T12:05:00Z"
  }
}'
                            ],
                            [
                                'title' => 'Balance Updated',
                                'color' => 'blue',
                                'payload' => '{
  "event_id": "evt_def456ghi789",
  "event_type": "account.balance_updated",
  "created_at": "2025-01-01T12:05:30Z",
  "data": {
    "account_uuid": "acct_receiver456",
    "asset_code": "USD",
    "previous_balance": "500.00",
    "new_balance": "600.00",
    "change_amount": "100.00",
    "change_reason": "incoming_transfer",
    "related_transaction": "txfr_xyz789abc123"
  }
}'
                            ],
                            [
                                'title' => 'Workflow Failed',
                                'color' => 'red',
                                'payload' => '{
  "event_id": "evt_ghi789jkl012",
  "event_type": "workflow.failed",
  "created_at": "2025-01-01T12:10:00Z",
  "data": {
    "workflow_id": "wf_failed123",
    "workflow_type": "currency_conversion_transfer",
    "failed_step": "transfer",
    "failure_reason": "insufficient_funds",
    "error_details": {
      "code": "INSUFFICIENT_FUNDS",
      "message": "Account balance too low",
      "required_amount": "100.00",
      "available_amount": "50.00"
    },
    "compensation_triggered": true
  }
}'
                            ]
                        ];
                    @endphp

                    @foreach($payloadExamples as $example)
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-{{ $example['color'] }}-500 to-{{ $example['color'] }}-600 px-6 py-4">
                            <h3 class="text-xl font-semibold text-white">{{ $example['title'] }}</h3>
                        </div>
                        <div class="p-6">
                            <div class="bg-gray-900 rounded-xl p-6 overflow-x-auto">
                                <pre class="code-block"><code class="text-green-400">{{ $example['payload'] }}</code></pre>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Best Practices -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Best Practices</h2>
                    <p class="text-xl text-gray-600">Build reliable webhook integrations</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    @php
                        $practices = [
                            [
                                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                                'color' => 'green',
                                'title' => 'Security First',
                                'points' => [
                                    'Always verify signatures',
                                    'Use HTTPS endpoints only',
                                    'Implement IP whitelisting',
                                    'Store secrets securely'
                                ]
                            ],
                            [
                                'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                                'color' => 'yellow',
                                'title' => 'High Performance',
                                'points' => [
                                    'Respond quickly (< 3s)',
                                    'Process asynchronously',
                                    'Use message queues',
                                    'Handle timeouts gracefully'
                                ]
                            ],
                            [
                                'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                                'color' => 'blue',
                                'title' => 'Reliability',
                                'points' => [
                                    'Implement idempotency',
                                    'Handle duplicates',
                                    'Log all events',
                                    'Monitor endpoint health'
                                ]
                            ],
                            [
                                'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                'color' => 'red',
                                'title' => 'Error Handling',
                                'points' => [
                                    'Return proper status codes',
                                    'Implement exponential backoff',
                                    'Handle partial failures',
                                    'Set up alerting'
                                ]
                            ]
                        ];
                    @endphp

                    @foreach($practices as $practice)
                    <div class="bg-gradient-to-br from-{{ $practice['color'] }}-50 to-{{ $practice['color'] }}-100 rounded-2xl p-6">
                        <div class="w-14 h-14 bg-{{ $practice['color'] }}-100 rounded-xl flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-{{ $practice['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $practice['icon'] }}"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">{{ $practice['title'] }}</h3>
                        <ul class="space-y-2">
                            @foreach($practice['points'] as $point)
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-{{ $practice['color'] }}-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700 text-sm">{{ $point }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Testing Section -->
        <section class="py-20 bg-gradient-to-r from-yellow-50 to-orange-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Testing Your Integration</h2>
                    <p class="text-xl text-gray-600">Tools and techniques for webhook development</p>
                </div>

                <div class="grid lg:grid-cols-3 gap-8">
                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Local Development</h3>
                        <p class="text-gray-600 mb-4">Use ngrok to expose your local server:</p>
                        <div class="bg-gray-900 rounded p-3">
                            <code class="text-green-400 text-sm">ngrok http 3000</code>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Test Events</h3>
                        <p class="text-gray-600 mb-4">Trigger test webhooks via API:</p>
                        <div class="bg-gray-900 rounded p-3">
                            <code class="text-green-400 text-sm">POST /v1/webhooks/{id}/test</code>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Event Logs</h3>
                        <p class="text-gray-600 mb-4">View delivery history in dashboard:</p>
                        <div class="bg-gray-900 rounded p-3">
                            <code class="text-green-400 text-sm">GET /v1/webhooks/{id}/logs</code>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 webhook-gradient text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to receive real-time events?</h2>
                <p class="text-xl text-yellow-100 mb-8">
                    Set up your first webhook in minutes
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('developers') }}" class="inline-flex items-center justify-center px-8 py-4 bg-white text-orange-600 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                        View Documentation
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                    <a href="{{ route('developers.show', 'api-docs') }}" class="inline-flex items-center justify-center px-8 py-4 border-2 border-white text-white rounded-lg font-semibold hover:bg-white hover:text-orange-600 transition">
                        API Reference
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')

        <!-- Animation Styles -->
        <style>
            @keyframes blob {
                0% { transform: translate(0px, 0px) scale(1); }
                33% { transform: translate(30px, -50px) scale(1.1); }
                66% { transform: translate(-20px, 20px) scale(0.9); }
                100% { transform: translate(0px, 0px) scale(1); }
            }
            .animate-blob {
                animation: blob 7s infinite;
            }
            .animation-delay-2000 {
                animation-delay: 2s;
            }
            .animation-delay-4000 {
                animation-delay: 4s;
            }
        </style>
    </body>
</html>