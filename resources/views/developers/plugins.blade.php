@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'Plugin Development - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title' => 'Plugin Development - ' . $brand . ' Developer Documentation',
        'description' => $brand . ' Plugin Development Guide — build extensions with hooks, permissions, sandbox security, and the full plugin lifecycle.',
        'keywords' => $brand . ', plugins, development, hooks, marketplace, extensions, API',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .plugin-gradient { background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%); }
    .code-block {
        font-family: 'Fira Code', monospace;
        font-size: 0.875rem;
        line-height: 1.5;
        overflow-x: auto;
        white-space: pre;
    }
    .code-container {
        position: relative;
        background: #0f1419;
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .code-header {
        background: #0f172a;
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
        font-family: 'Figtree', sans-serif;
        color: #94a3b8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="plugin-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-20 left-10 w-72 h-72 bg-purple-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-blue-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">
                Developer Guide
            </span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Plugin Development</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Extend the {{ $brand }} platform with hooks, permissions, and sandboxed execution.
            </p>
        </div>
    </div>
</section>

<!-- Quick Start -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Quick Start</h2>

        <div class="space-y-6">
            <!-- Step 1: Scaffold -->
            <div class="border border-slate-200 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-purple-700 font-bold text-sm">1</span>
                    <h3 class="text-lg font-semibold">Scaffold a plugin</h3>
                </div>
                <div class="code-container">
                    <div class="code-header"><span>Terminal</span></div>
                    <pre class="code-block p-4 text-green-400">php artisan plugin:create my-company payment-analytics</pre>
                </div>
                <p class="mt-3 text-slate-600 text-sm">Creates the following structure:</p>
                <div class="code-container mt-2">
                    <div class="code-header"><span>Directory Structure</span></div>
                    <pre class="code-block p-4 text-slate-300">plugins/my-company/payment-analytics/
├── plugin.json              ← Manifest (metadata, permissions, deps)
├── src/
│   └── ServiceProvider.php  ← Entry point (register + boot)
├── routes/
├── config/
├── migrations/
└── tests/</pre>
                </div>
            </div>

            <!-- Step 2: Manifest -->
            <div class="border border-slate-200 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-purple-700 font-bold text-sm">2</span>
                    <h3 class="text-lg font-semibold">Configure the manifest</h3>
                </div>
                <div class="code-container">
                    <div class="code-header"><span>plugin.json</span></div>
                    <pre class="code-block p-4 text-slate-300">{
  "vendor": "my-company",
  "name": "payment-analytics",
  "version": "1.0.0",
  "display_name": "Payment Analytics",
  "description": "Real-time payment analytics and reporting dashboard",
  "author": "My Company",
  "license": "MIT",
  "entry_point": "ServiceProvider",
  "permissions": [
    "database:read",
    "events:listen",
    "cache:read",
    "cache:write"
  ],
  "dependencies": {},
  "extra": {
    "dashboard_url": "/admin/payment-analytics"
  }
}</pre>
                </div>
            </div>

            <!-- Step 3: Hook -->
            <div class="border border-slate-200 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-purple-700 font-bold text-sm">3</span>
                    <h3 class="text-lg font-semibold">Register a hook listener</h3>
                </div>
                <div class="code-container">
                    <div class="code-header"><span>src/ServiceProvider.php</span></div>
                    <pre class="code-block p-4 text-slate-300"><span class="text-purple-400">namespace</span> Plugins\MyCompany\PaymentAnalytics;

<span class="text-purple-400">use</span> App\Infrastructure\Plugins\PluginHookInterface;
<span class="text-purple-400">use</span> App\Infrastructure\Plugins\PluginHookManager;
<span class="text-purple-400">use</span> Illuminate\Support\ServiceProvider <span class="text-purple-400">as</span> BaseServiceProvider;

<span class="text-purple-400">class</span> <span class="text-yellow-300">ServiceProvider</span> <span class="text-purple-400">extends</span> BaseServiceProvider
{
    <span class="text-purple-400">public function</span> <span class="text-blue-300">boot</span>(): <span class="text-green-400">void</span>
    {
        <span class="text-slate-500">// Register hook listener for completed payments</span>
        <span class="text-purple-400">$this</span>->app->make(PluginHookManager::class)
            ->register(<span class="text-purple-400">new</span> PaymentCompletedListener());
    }
}

<span class="text-purple-400">class</span> <span class="text-yellow-300">PaymentCompletedListener</span> <span class="text-purple-400">implements</span> PluginHookInterface
{
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getHookName</span>(): <span class="text-green-400">string</span>
    {
        <span class="text-purple-400">return</span> <span class="text-green-400">'payment.completed'</span>;
    }

    <span class="text-purple-400">public function</span> <span class="text-blue-300">getPriority</span>(): <span class="text-green-400">int</span>
    {
        <span class="text-purple-400">return</span> <span class="text-orange-400">10</span>; <span class="text-slate-500">// Lower = runs first</span>
    }

    <span class="text-purple-400">public function</span> <span class="text-blue-300">handle</span>(<span class="text-green-400">array</span> $payload): <span class="text-green-400">void</span>
    {
        <span class="text-slate-500">// $payload contains payment data</span>
        Log::info(<span class="text-green-400">'Payment analytics: tracking'</span>, $payload);
    }
}</pre>
                </div>
            </div>

            <!-- Step 4: Install -->
            <div class="border border-slate-200 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-purple-700 font-bold text-sm">4</span>
                    <h3 class="text-lg font-semibold">Install and enable</h3>
                </div>
                <div class="code-container">
                    <div class="code-header"><span>Terminal</span></div>
                    <pre class="code-block p-4 text-green-400">php artisan plugin:install plugins/my-company/payment-analytics/plugin.json
php artisan plugin:enable my-company payment-analytics
php artisan plugin:list</pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Available Hooks -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Available Hooks</h2>
        <p class="text-slate-600 mb-6">Register listeners for these events. Hooks fire asynchronously — exceptions in one listener don't prevent others from executing.</p>

        @php
        $hookGroups = [
            'Account' => [
                ['name' => 'account.created', 'desc' => 'New account created'],
                ['name' => 'account.updated', 'desc' => 'Account details updated'],
            ],
            'Payments' => [
                ['name' => 'payment.initiated', 'desc' => 'Payment started'],
                ['name' => 'payment.completed', 'desc' => 'Payment confirmed'],
                ['name' => 'payment.failed', 'desc' => 'Payment failed'],
            ],
            'Wallet' => [
                ['name' => 'wallet.created', 'desc' => 'New wallet created'],
                ['name' => 'wallet.transfer', 'desc' => 'Token transfer occurred'],
            ],
            'Compliance' => [
                ['name' => 'compliance.alert', 'desc' => 'Compliance alert triggered'],
                ['name' => 'compliance.kyc', 'desc' => 'KYC verification status changed'],
            ],
            'Exchange' => [
                ['name' => 'order.placed', 'desc' => 'Exchange order submitted'],
                ['name' => 'order.matched', 'desc' => 'Order matched/filled'],
            ],
            'Lending' => [
                ['name' => 'loan.applied', 'desc' => 'Loan application submitted'],
                ['name' => 'loan.approved', 'desc' => 'Loan approved'],
            ],
            'Cross-Chain' => [
                ['name' => 'bridge.initiated', 'desc' => 'Bridge transfer started'],
                ['name' => 'bridge.completed', 'desc' => 'Bridge transfer completed'],
            ],
            'DeFi' => [
                ['name' => 'defi.position.opened', 'desc' => 'DeFi position opened'],
                ['name' => 'defi.position.closed', 'desc' => 'DeFi position closed'],
            ],
        ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($hookGroups as $category => $hooks)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-sm text-purple-700 uppercase tracking-wider mb-3">{{ $category }}</h3>
                <div class="space-y-2">
                    @foreach($hooks as $hook)
                    <div class="flex items-baseline gap-2">
                        <code class="code-font text-sm bg-slate-100 px-2 py-0.5 rounded text-slate-800">{{ $hook['name'] }}</code>
                        <span class="text-sm text-slate-500">{{ $hook['desc'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Permissions -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Permission Model</h2>
        <p class="text-slate-600 mb-8">Plugins declare required permissions in <code class="code-font bg-slate-100 px-1.5 py-0.5 rounded">plugin.json</code>. The sandbox enforces these at runtime — undeclared access throws a <code class="code-font bg-slate-100 px-1.5 py-0.5 rounded">RuntimeException</code>.</p>

        @php
        $permGroups = [
            'Data Access' => [
                ['perm' => 'database:read', 'desc' => 'Read from database tables'],
                ['perm' => 'database:write', 'desc' => 'Write to database tables'],
            ],
            'API' => [
                ['perm' => 'api:internal', 'desc' => 'Access internal API endpoints'],
                ['perm' => 'api:external', 'desc' => 'Make outbound HTTP requests'],
            ],
            'Events' => [
                ['perm' => 'events:listen', 'desc' => 'Listen to application events'],
                ['perm' => 'events:dispatch', 'desc' => 'Dispatch application events'],
            ],
            'Infrastructure' => [
                ['perm' => 'queue:dispatch', 'desc' => 'Queue background jobs'],
                ['perm' => 'cache:read', 'desc' => 'Read from cache'],
                ['perm' => 'cache:write', 'desc' => 'Write to cache'],
                ['perm' => 'filesystem:read', 'desc' => 'Read files'],
                ['perm' => 'filesystem:write', 'desc' => 'Write files'],
                ['perm' => 'config:read', 'desc' => 'Read application configuration'],
            ],
        ];
        @endphp

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Category</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Permission</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($permGroups as $category => $perms)
                        @foreach($perms as $i => $p)
                        <tr>
                            @if($i === 0)
                            <td class="px-4 py-2.5 text-slate-600 font-medium" rowspan="{{ count($perms) }}">{{ $category }}</td>
                            @endif
                            <td class="px-4 py-2.5"><code class="code-font text-sm bg-purple-50 text-purple-700 px-2 py-0.5 rounded">{{ $p['perm'] }}</code></td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $p['desc'] }}</td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Security Scanner -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Security Scanner</h2>
        <p class="text-slate-600 mb-8">All plugins are automatically scanned for dangerous patterns before installation. The scanner detects four severity levels:</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border-l-4 border-red-500 p-5">
                <h3 class="font-bold text-red-700 mb-2">Critical</h3>
                <p class="text-sm text-slate-600">Blocks installation. <code class="code-font bg-red-50 px-1 rounded">eval()</code>, <code class="code-font bg-red-50 px-1 rounded">exec()</code>, <code class="code-font bg-red-50 px-1 rounded">shell_exec()</code>, <code class="code-font bg-red-50 px-1 rounded">system()</code>, backtick execution, dynamic includes.</p>
            </div>
            <div class="bg-white rounded-xl border-l-4 border-orange-500 p-5">
                <h3 class="font-bold text-orange-700 mb-2">High</h3>
                <p class="text-sm text-slate-600">Review required. <code class="code-font bg-orange-50 px-1 rounded">DB::raw()</code>, <code class="code-font bg-orange-50 px-1 rounded">unserialize()</code>, <code class="code-font bg-orange-50 px-1 rounded">extract()</code>, direct <code class="code-font bg-orange-50 px-1 rounded">env()</code> access.</p>
            </div>
            <div class="bg-white rounded-xl border-l-4 border-yellow-500 p-5">
                <h3 class="font-bold text-yellow-700 mb-2">Medium</h3>
                <p class="text-sm text-slate-600">Warning. Remote <code class="code-font bg-yellow-50 px-1 rounded">file_get_contents()</code>, <code class="code-font bg-yellow-50 px-1 rounded">curl_exec()</code> — declare <code class="code-font bg-yellow-50 px-1 rounded">api:external</code> permission instead.</p>
            </div>
            <div class="bg-white rounded-xl border-l-4 border-blue-500 p-5">
                <h3 class="font-bold text-blue-700 mb-2">Low</h3>
                <p class="text-sm text-slate-600">Informational. Non-standard patterns that may indicate code quality issues.</p>
            </div>
        </div>

        <div class="mt-6 code-container">
            <div class="code-header"><span>Run scanner manually</span></div>
            <pre class="code-block p-4 text-green-400">php artisan plugin:verify my-company payment-analytics</pre>
        </div>
    </div>
</section>

<!-- Plugin Lifecycle -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Plugin Lifecycle</h2>

        <div class="flex flex-col md:flex-row items-center gap-4 mb-8">
            @foreach(['Scaffold', 'Install', 'Enable', 'Active', 'Disable', 'Remove'] as $i => $step)
            <div class="flex items-center gap-2">
                <div class="px-4 py-2 rounded-lg {{ $step === 'Active' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-slate-100 text-slate-700 border border-slate-200' }} font-semibold text-sm">
                    {{ $step }}
                </div>
                @if($i < 5)
                <svg class="w-4 h-4 text-slate-400 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                @endif
            </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Command</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:create {vendor} {name}</code></td><td class="px-4 py-2.5 text-slate-600">Scaffold plugin directory and manifest</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:install {manifest.json}</code></td><td class="px-4 py-2.5 text-slate-600">Validate, resolve deps, register plugin (inactive)</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:enable {vendor} {name}</code></td><td class="px-4 py-2.5 text-slate-600">Activate plugin, boot ServiceProvider, register hooks</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:disable {vendor} {name}</code></td><td class="px-4 py-2.5 text-slate-600">Deactivate plugin, unregister hooks</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:remove {vendor} {name}</code></td><td class="px-4 py-2.5 text-slate-600">Delete plugin (system plugins cannot be removed)</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:list</code></td><td class="px-4 py-2.5 text-slate-600">List all plugins with status</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">plugin:verify {vendor} {name}</code></td><td class="px-4 py-2.5 text-slate-600">Run security scanner on plugin code</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Manifest Reference -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Manifest Reference</h2>
        <p class="text-slate-600 mb-6">Complete <code class="code-font bg-slate-200 px-1.5 py-0.5 rounded">plugin.json</code> schema:</p>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Field</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Required</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr><td class="px-4 py-2"><code class="code-font">vendor</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-green-600">Yes</td><td class="px-4 py-2 text-slate-600">Vendor identifier (alphanumeric, hyphens, underscores)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">name</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-green-600">Yes</td><td class="px-4 py-2 text-slate-600">Plugin name (alphanumeric, hyphens, underscores)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">version</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-green-600">Yes</td><td class="px-4 py-2 text-slate-600">Semantic version (X.Y.Z)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">display_name</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Human-readable name (defaults to name)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">description</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Plugin description</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">author</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Author name</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">license</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">License type (default: MIT)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">entry_point</code></td><td class="px-4 py-2 text-slate-500">string</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">ServiceProvider class name (default: ServiceProvider)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">permissions</code></td><td class="px-4 py-2 text-slate-500">string[]</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Required permissions (see table above)</td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">dependencies</code></td><td class="px-4 py-2 text-slate-500">object</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Semver constraints: <code class="code-font">{"vendor/name": "^1.0"}</code></td></tr>
                    <tr><td class="px-4 py-2"><code class="code-font">extra</code></td><td class="px-4 py-2 text-slate-500">object</td><td class="px-4 py-2 text-slate-400">No</td><td class="px-4 py-2 text-slate-600">Custom metadata (dashboard URLs, settings schema, etc.)</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- REST & GraphQL APIs -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">REST & GraphQL APIs</h2>
        <p class="text-slate-600 mb-8">Manage plugins programmatically via REST or GraphQL.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- REST -->
            <div>
                <h3 class="font-semibold text-lg mb-4">REST Endpoints</h3>
                <div class="space-y-2">
                    @php
                    $endpoints = [
                        ['GET', '/api/plugins', 'List all plugins'],
                        ['GET', '/api/plugins/{id}', 'Get plugin details'],
                        ['POST', '/api/plugins/{id}/enable', 'Enable plugin'],
                        ['POST', '/api/plugins/{id}/disable', 'Disable plugin'],
                        ['POST', '/api/plugins/{id}/scan', 'Security scan'],
                        ['GET', '/api/plugins/marketplace/stats', 'Marketplace stats'],
                    ];
                    @endphp
                    @foreach($endpoints as $ep)
                    <div class="flex items-center gap-3 text-sm border border-slate-200 rounded-lg px-3 py-2">
                        <span class="code-font font-semibold {{ $ep[0] === 'GET' ? 'text-green-600' : 'text-blue-600' }} w-12">{{ $ep[0] }}</span>
                        <code class="code-font text-slate-700 flex-1">{{ $ep[1] }}</code>
                        <span class="text-slate-500 text-xs hidden sm:inline">{{ $ep[2] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- GraphQL -->
            <div>
                <h3 class="font-semibold text-lg mb-4">GraphQL Operations</h3>
                <div class="space-y-2">
                    @php
                    $gqlOps = [
                        ['Query', 'plugin(id: ID!)', 'Get plugin by ID'],
                        ['Query', 'plugins(status: String)', 'List plugins with filter'],
                        ['Query', 'pluginMarketplaceStats', 'Marketplace stats'],
                        ['Mutation', 'enablePlugin(id: ID!)', 'Enable a plugin'],
                        ['Mutation', 'disablePlugin(id: ID!)', 'Disable a plugin'],
                        ['Mutation', 'scanPlugin(id: ID!)', 'Security scan'],
                    ];
                    @endphp
                    @foreach($gqlOps as $op)
                    <div class="flex items-center gap-3 text-sm border border-slate-200 rounded-lg px-3 py-2">
                        <span class="code-font font-semibold {{ $op[0] === 'Query' ? 'text-purple-600' : 'text-orange-600' }} w-16">{{ $op[0] }}</span>
                        <code class="code-font text-slate-700 flex-1">{{ $op[1] }}</code>
                        <span class="text-slate-500 text-xs hidden sm:inline">{{ $op[2] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Back to Developers -->
<section class="bg-slate-50 py-12">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('developers') }}" class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Developer Hub
        </a>
    </div>
</section>

@endsection
