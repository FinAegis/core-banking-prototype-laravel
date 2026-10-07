@extends('layouts.public')

@section('title', 'Bank Integration - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Bank Integration',
        'description' => 'Bank connector adapters (reference implementations). Integration adapters for third-party APIs; no partnership or endorsement implied.',
        'keywords' => 'bank integration, bank connector adapters, reference implementation, ' . config('brand.name', 'Zelta'),
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Bank Integration', 'url' => url('/features/bank-integration')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Bank Integration Adapters</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Reference connector implementations that demonstrate bank integration patterns. Integration adapters for third-party APIs; no partnership or endorsement implied.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Integration Notice -->
    <section class="py-8 bg-amber-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-amber-400">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-slate-900">Integration Architecture</h3>
                        <p class="mt-2 text-slate-500">
                            Bank integrations include adapters for third-party APIs such as a card-issuing integration adapter (e.g. Marqeta API), Ondato KYC and Chainalysis sanctions screening; no partnership or endorsement implied.
                            Additional connectors use reference implementations that demonstrate integration patterns. Live bank connections require commercial agreements and regulatory approvals.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Multi-Bank Allocation Module</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold mb-6">Allocation Modelling</h3>
                    <p class="text-lg text-slate-500 mb-6">
                        Multi-bank allocation module: software for modelling fund allocation across connected institutions (reference implementation).
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Daily Reconciliation</h4>
                                <p class="text-slate-500">Automated daily balance checks and audit reports</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Transparent Reporting</h4>
                                <p class="text-slate-500">Real-time visibility into modelled allocation across institutions</p>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="card-feature !p-8 rounded-2xl">
                    <h4 class="text-xl font-bold mb-6">How Allocation Works</h4>
                    <div class="space-y-4">
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Choose Institutions</span>
                                <span class="text-sm text-gray-500">Step 1</span>
                            </div>
                            <p class="text-sm text-slate-500">Select from connected institutions</p>
                        </div>
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Set Allocation</span>
                                <span class="text-sm text-gray-500">Step 2</span>
                            </div>
                            <p class="text-sm text-slate-500">Set allocation percentages across selected institutions (must total 100%)</p>
                        </div>
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Primary Institution</span>
                                <span class="text-sm text-gray-500">Step 3</span>
                            </div>
                            <p class="text-sm text-slate-500">Designate a primary institution for withdrawals</p>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Automatic Rebalancing</span>
                                <span class="text-sm text-gray-500">Ongoing</span>
                            </div>
                            <p class="text-sm text-slate-500">Rebalancing logic runs automatically within the module</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Integration Process -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Seamless Integration</h2>
            
            <div class="max-w-4xl mx-auto">
                <div class="card-feature !p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold mb-6">How the Connectors Work</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="font-bold mb-4">Technical Integration</h4>
                            <ul class="space-y-2 text-slate-500">
                                <li>• REST API with 13 endpoints and webhook notifications</li>
                                <li>• Account verification via micro-deposit and instant methods</li>
                                <li>• GraphQL transfer operations with real-time subscriptions</li>
                                <li>• Automated reconciliation systems</li>
                                <li>• Secure multi-signature protocols</li>
                                <li>• Daily balance verification</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Operational Excellence</h4>
                            <ul class="space-y-2 text-slate-500">
                                <li>• Compliance reporting tooling</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Explore the Connector Adapters</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Reference implementations; integration adapters for third-party APIs; no partnership or endorsement implied.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('developers.show', 'sandbox') }}" class="btn-primary px-8 py-4 text-lg">
                    Explore the Sandbox
                </a>
                <a href="{{ route('security') }}" class="btn-outline px-8 py-4 text-lg">
                    Learn About Security
                </a>
            </div>
        </div>
    </section>

@endsection