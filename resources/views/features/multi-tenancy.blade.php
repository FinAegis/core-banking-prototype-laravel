@extends('layouts.public')

@section('title', 'Multi-Tenancy - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Multi-Tenancy',
        'description' => 'Enterprise-grade multi-tenancy with team-based isolation, per-tenant configuration, data migration, and the UsesTenantConnection trait powered by stancl/tenancy v3.9.',
        'keywords' => 'multi-tenancy, tenant isolation, database scoping, enterprise, data migration, tenant configuration, stancl tenancy, ' . config('brand.name', 'Zelta') . '',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Multi-Tenancy', 'url' => url('/features/multi-tenancy')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Tenancy</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    True enterprise-grade tenant isolation built on stancl/tenancy v3.9. Every team gets its own secure, configurable environment with database-level data separation and zero cross-tenant leakage.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Overview Cards -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="tenant-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Team-Based Isolation</h3>
                    <p class="text-slate-500">Database-level scoping ensures complete data separation between tenants. Each organization operates in its own secure partition.</p>
                </div>

                <div class="tenant-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Per-Tenant Configuration</h3>
                    <p class="text-slate-500">Each tenant gets independent settings for features, limits, branding, compliance rules, and integrations -- all manageable at runtime.</p>
                </div>

                <div class="tenant-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Data Migration</h3>
                    <p class="text-slate-500">Seamless tenant data migration, export, and import tooling. Move data between environments with full integrity verification.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture Detail -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Tenancy Architecture</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <p class="text-lg text-slate-500 mb-6">
                        Built on stancl/tenancy v3.9, the {{ config('brand.name', 'Zelta') }} multi-tenancy system provides robust team-based isolation at the database level. The UsesTenantConnection trait automatically scopes all Eloquent queries, events, and jobs to the current tenant context.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-indigo-600 font-bold">1</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Tenant Identification</h4>
                                <p class="text-slate-500">Middleware resolves the tenant from the request domain, header, or authentication token before any database access occurs.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-purple-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-purple-600 font-bold">2</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Connection Switching</h4>
                                <p class="text-slate-500">The UsesTenantConnection trait automatically binds models to the tenant database connection, preventing any cross-tenant data access.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-green-600 font-bold">3</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Scoped Operations</h4>
                                <p class="text-slate-500">Events, jobs, notifications, and cache entries are all automatically scoped to the current tenant for complete isolation.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold mb-6 text-slate-900">Tenant Operations</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <span class="text-slate-500">Tenant Provisioning</span>
                            <span class="text-lg font-bold text-indigo-600">Automated</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <span class="text-slate-500">Schema Migrations</span>
                            <span class="text-lg font-bold text-purple-600">Per-Tenant</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <span class="text-slate-500">Data Export</span>
                            <span class="text-lg font-bold text-green-600">JSON / CSV</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <span class="text-slate-500">Configuration Scope</span>
                            <span class="text-lg font-bold text-pink-600">Runtime</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enterprise Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Enterprise Features</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-indigo-900">Data Management</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Tenant Data Migration</h4>
                                <p class="text-slate-600">Move tenant data between environments with integrity checks and rollback support</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Data Export and Import</h4>
                                <p class="text-slate-600">Full tenant data export to JSON or CSV with selective table and date range filters</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Backup and Restore</h4>
                                <p class="text-slate-600">Per-tenant backup scheduling with point-in-time recovery capabilities</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-purple-900">Tenant Configuration</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Feature Flags</h4>
                                <p class="text-slate-600">Enable or disable platform features per tenant without redeployment</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Rate Limits and Quotas</h4>
                                <p class="text-slate-600">Configurable API rate limits, storage quotas, and transaction caps per tenant</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Compliance Rules</h4>
                                <p class="text-slate-600">Jurisdiction-specific compliance configuration scoped to each tenant</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Features Checklist -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Technical Implementation</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Isolation and Security</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>UsesTenantConnection trait for automatic query scoping</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Tenant-aware middleware for request-level isolation</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Database-level separation preventing cross-tenant leaks</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Scoped cache, queues, and event broadcasting</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Tenant identification via domain, header, or token</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Audit logging scoped to tenant context</span>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Operations and Tooling</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Artisan commands for tenant provisioning and management</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Per-tenant database migrations with version tracking</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>stancl/tenancy v3.9 with event-driven lifecycle hooks</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Tenant health monitoring and resource usage dashboards</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Automated tenant onboarding with seed data provisioning</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Tenant suspension and archival with data retention policies</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Scale With Confidence</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Whether you serve ten organizations or ten thousand, {{ config('brand.name', 'Zelta') }} multi-tenancy ensures every tenant gets enterprise-grade isolation, performance, and control.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Start Free Trial
                </a>
                <a href="{{ route('features') }}" class="btn-outline px-8 py-4 text-lg">
                    Explore All Features
                </a>
            </div>
        </div>
    </section>

@endsection
