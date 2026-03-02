@extends('layouts.public')

@section('title', 'Plugin Marketplace - FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Plugin Marketplace',
        'description' => 'Extend FinAegis with a secure plugin ecosystem. Sandbox execution, static security scanning, hook-based integration, and a full management UI.',
        'keywords' => 'plugin marketplace, extensibility, sandbox execution, security scanner, plugin hooks, FinAegis',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Plugin Marketplace', 'url' => url('/features/plugin-marketplace')]
    ]" />
@endsection

@push('styles')
<style>
    .plugin-gradient {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #6366f1 100%);
    }
    .plugin-card {
        transition: all 0.3s ease;
    }
    .plugin-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .hook-badge {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="plugin-gradient text-white pt-24 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-6">Plugin Marketplace</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    Extend FinAegis with a secure, sandboxed plugin system. Discover, install, and manage plugins with built-in security scanning and permission enforcement.
                </p>
            </div>
        </div>
    </section>

    <!-- Core Capabilities -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12">Core Capabilities</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="plugin-card bg-white rounded-xl p-8 shadow-md text-center">
                    <div class="w-16 h-16 bg-violet-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Plugin Manager</h3>
                    <p class="text-gray-600">Install, enable, disable, update, and remove plugins with semver-aware dependency resolution. Full lifecycle management via API or admin UI.</p>
                </div>
                <div class="plugin-card bg-white rounded-xl p-8 shadow-md text-center">
                    <div class="w-16 h-16 bg-amber-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Sandbox Execution</h3>
                    <p class="text-gray-600">Plugins run in a permission-enforced sandbox. Each plugin declares required permissions and is restricted from accessing unauthorized resources.</p>
                </div>
                <div class="plugin-card bg-white rounded-xl p-8 shadow-md text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Security Scanner</h3>
                    <p class="text-gray-600">Static analysis scans plugin code for dangerous patterns before activation. Severity classification (critical, high, medium, low) with detailed reporting.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Hook System -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold mb-6">17 Integration Hooks</h2>
                    <p class="text-gray-600 mb-6">
                        Plugins integrate via a hook-based system that fires at key points in the platform lifecycle. Hooks are type-safe and support priority ordering.
                    </p>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="hook-badge bg-violet-100 text-violet-700 text-xs px-2.5 py-1 rounded-full font-medium">account.*</span>
                            <span class="text-gray-600 text-sm">Account creation, updates, deletion</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hook-badge bg-blue-100 text-blue-700 text-xs px-2.5 py-1 rounded-full font-medium">transaction.*</span>
                            <span class="text-gray-600 text-sm">Pre/post transaction processing</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hook-badge bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-medium">compliance.*</span>
                            <span class="text-gray-600 text-sm">KYC/AML verification triggers</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hook-badge bg-amber-100 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">wallet.*</span>
                            <span class="text-gray-600 text-sm">Wallet operations and balance changes</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hook-badge bg-red-100 text-red-700 text-xs px-2.5 py-1 rounded-full font-medium">security.*</span>
                            <span class="text-gray-600 text-sm">Authentication and authorization events</span>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-900 rounded-xl p-6 text-sm font-mono text-gray-300 overflow-x-auto">
                    <div class="text-gray-500 mb-2">// Example: Webhook Notifier Plugin</div>
                    <div><span class="text-purple-400">class</span> <span class="text-yellow-300">WebhookNotifierPlugin</span></div>
                    <div>{</div>
                    <div class="ml-4"><span class="text-purple-400">public string</span> <span class="text-blue-300">$name</span> = <span class="text-green-400">'webhook-notifier'</span>;</div>
                    <div class="ml-4"><span class="text-purple-400">public array</span> <span class="text-blue-300">$hooks</span> = [</div>
                    <div class="ml-8"><span class="text-green-400">'transaction.completed'</span>,</div>
                    <div class="ml-8"><span class="text-green-400">'account.created'</span>,</div>
                    <div class="ml-4">];</div>
                    <div class="ml-4"><span class="text-purple-400">public array</span> <span class="text-blue-300">$permissions</span> = [</div>
                    <div class="ml-8"><span class="text-green-400">'http:outbound'</span>,</div>
                    <div class="ml-8"><span class="text-green-400">'config:read'</span>,</div>
                    <div class="ml-4">];</div>
                    <div>}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- REST API -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12">Plugin REST API</h2>
            <div class="max-w-3xl mx-auto">
                <div class="bg-gray-50 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                                <th class="px-6 py-3 font-semibold text-gray-700">Method</th>
                                <th class="px-6 py-3 font-semibold text-gray-700">Endpoint</th>
                                <th class="px-6 py-3 font-semibold text-gray-700">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-3"><span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">GET</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins</td>
                                <td class="px-6 py-3 text-gray-600">List all plugins</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">GET</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/{id}</td>
                                <td class="px-6 py-3 text-gray-600">Show plugin details</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold">POST</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/{id}/enable</td>
                                <td class="px-6 py-3 text-gray-600">Enable a plugin</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold">POST</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/{id}/disable</td>
                                <td class="px-6 py-3 text-gray-600">Disable a plugin</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold">POST</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/{id}/scan</td>
                                <td class="px-6 py-3 text-gray-600">Run security scan</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold">POST</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/discover</td>
                                <td class="px-6 py-3 text-gray-600">Discover new plugins</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3"><span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-bold">DELETE</span></td>
                                <td class="px-6 py-3 font-mono text-xs">/api/v2/plugins/{id}</td>
                                <td class="px-6 py-3 text-gray-600">Remove a plugin</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Admin Panel CTA -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl p-12 text-center text-white">
                <h2 class="text-3xl font-bold mb-4">Manage Plugins in the Admin Panel</h2>
                <p class="text-violet-100 text-lg mb-8 max-w-2xl mx-auto">
                    The full plugin management UI is available in the Filament admin dashboard. Browse installed plugins, run security scans, enable/disable plugins, and discover new ones.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="/admin/plugin-marketplace" class="inline-flex items-center px-8 py-3 bg-white text-violet-700 font-semibold rounded-lg hover:bg-violet-50 transition">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Open Plugin Marketplace
                    </a>
                </div>
                <p class="text-violet-200 text-sm mt-4">Requires admin authentication</p>
            </div>
        </div>
    </section>

    <!-- Built-in Plugins -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-4">Built-in Plugins</h2>
            <p class="text-gray-600 text-center mb-12 max-w-2xl mx-auto">FinAegis ships with reference plugins that demonstrate the hook system and serve as templates for custom development.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="plugin-card border border-gray-200 rounded-xl p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold">Webhook Notifier</h3>
                            <span class="text-xs text-gray-500">webhook-notifier</span>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm">Sends HTTP webhooks on platform events. Supports configurable endpoints, retry logic, and payload signing.</p>
                </div>
                <div class="plugin-card border border-gray-200 rounded-xl p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold">Audit Exporter</h3>
                            <span class="text-xs text-gray-500">audit-exporter</span>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm">Exports audit trail events to external systems. Supports CSV, JSON, and direct database export with scheduled runs.</p>
                </div>
            </div>
        </div>
    </section>

@endsection
