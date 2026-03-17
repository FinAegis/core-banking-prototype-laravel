@extends('layouts.public')

@section('title', 'Bank Integration - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Bank Integration',
        'description' => 'Direct integration with major banks including Paysera, Deutsche Bank, and Santander for seamless operations.',
        'keywords' => 'bank integration, partner banks, Paysera, Deutsche Bank, Santander, banking partners, ' . config('brand.name', 'Zelta') . '',
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
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Bank Integration Partners</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Your money stays in real banks with government insurance. We partner with trusted financial institutions across Europe to ensure security and accessibility.
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
                            Bank integrations include <strong>production-ready adapters</strong> for Ondato KYC, Chainalysis sanctions screening, and Marqeta card issuing.
                            Additional connectors use reference implementations that demonstrate integration patterns. Live bank connections require commercial agreements and regulatory approvals.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Partner Banks -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Our Banking Partners</h2>
            <p class="text-lg text-slate-500 text-center mb-12 max-w-3xl mx-auto">
                We work with established banks to provide secure, insured storage for your funds across multiple jurisdictions.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Paysera -->
                <div class="bank-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="h-16 mb-6 flex items-center">
                        <img src="https://www.paysera.com/v2/images/logo/paysera.svg" alt="Paysera" class="h-12">
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Paysera</h3>
                    <p class="text-slate-500 mb-4">Lithuania's leading fintech bank serving over 1 million clients globally.</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>€100,000 deposit guarantee</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>24/7 instant transfers</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Multi-currency accounts</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Max allocation:</span>
                            <span class="font-semibold">50%</span>
                        </div>
                    </div>
                </div>

                <!-- Deutsche Bank -->
                <div class="bank-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="h-16 mb-6 flex items-center">
                        <div class="text-2xl font-bold text-blue-900">Deutsche Bank</div>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Deutsche Bank</h3>
                    <p class="text-slate-500 mb-4">Germany's largest bank with over 150 years of financial expertise.</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>€100,000 deposit guarantee</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Global presence</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Premium banking services</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Max allocation:</span>
                            <span class="font-semibold">40%</span>
                        </div>
                    </div>
                </div>

                <!-- Santander -->
                <div class="bank-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="h-16 mb-6 flex items-center">
                        <div class="text-2xl font-bold text-red-600">Santander</div>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Santander</h3>
                    <p class="text-slate-500 mb-4">Leading Spanish bank serving 155 million customers worldwide.</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>€100,000 deposit guarantee</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>International reach</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Digital innovation</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Max allocation:</span>
                            <span class="font-semibold">40%</span>
                        </div>
                    </div>
                </div>

                <!-- Revolut -->
                <div class="bank-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="h-16 mb-6 flex items-center">
                        <div class="text-2xl font-bold">Revolut</div>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Revolut</h3>
                    <p class="text-slate-500 mb-4">UK's digital banking leader with over 30 million customers.</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>£85,000 FSCS protection</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Instant global transfers</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Crypto integration</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Max allocation:</span>
                            <span class="font-semibold">30%</span>
                        </div>
                    </div>
                </div>

                <!-- N26 -->
                <div class="bank-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="h-16 mb-6 flex items-center">
                        <div class="text-2xl font-bold">N26</div>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">N26</h3>
                    <p class="text-slate-500 mb-4">Europe's first mobile bank with 8 million customers.</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>€100,000 deposit guarantee</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Mobile-first banking</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Real-time notifications</span>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Max allocation:</span>
                            <span class="font-semibold">30%</span>
                        </div>
                    </div>
                </div>

                <!-- Coming Soon -->
                <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center">
                    <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <h3 class="text-xl font-bold text-slate-600 mb-2">More Banks Coming</h3>
                    <p class="text-gray-500 text-center">We're actively expanding our banking partnerships across Europe and globally.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Multi-Bank Security</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold mb-6">Distributed Risk Management</h3>
                    <p class="text-lg text-slate-500 mb-6">
                        Your funds are distributed across multiple partner banks according to your preferences, ensuring maximum protection and flexibility.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Government Insurance</h4>
                                <p class="text-slate-500">Up to €100,000 per bank through deposit guarantee schemes</p>
                            </div>
                        </li>
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
                                <p class="text-slate-500">Real-time visibility into fund distribution across banks</p>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="card-feature !p-8 rounded-2xl">
                    <h4 class="text-xl font-bold mb-6">How Allocation Works</h4>
                    <div class="space-y-4">
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Choose Your Banks</span>
                                <span class="text-sm text-gray-500">Step 1</span>
                            </div>
                            <p class="text-sm text-slate-500">Select from our partner banks based on your preferences</p>
                        </div>
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Set Allocation</span>
                                <span class="text-sm text-gray-500">Step 2</span>
                            </div>
                            <p class="text-sm text-slate-500">Distribute your funds across selected banks (must total 100%)</p>
                        </div>
                        <div class="pb-4 border-b">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Primary Bank</span>
                                <span class="text-sm text-gray-500">Step 3</span>
                            </div>
                            <p class="text-sm text-slate-500">Choose your primary bank for quick withdrawals</p>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium">Automatic Management</span>
                                <span class="text-sm text-gray-500">Ongoing</span>
                            </div>
                            <p class="text-sm text-slate-500">We handle all transfers and rebalancing automatically</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Benefits of Multi-Bank Integration</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Enhanced Security</h3>
                    <p class="text-slate-500">Funds distributed across multiple banks reduce single point of failure risk.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Maximum Insurance</h3>
                    <p class="text-slate-500">Up to €500,000 total protection through multiple deposit guarantees.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Instant Access</h3>
                    <p class="text-slate-500">Quick withdrawals from your primary bank while maintaining diversification.</p>
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
                    <h3 class="text-2xl font-bold mb-6">How We Work with Banks</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="font-bold mb-4">Technical Integration</h4>
                            <ul class="space-y-2 text-slate-500">
                                <li>• API connections for real-time operations</li>
                                <li>• Automated reconciliation systems</li>
                                <li>• Secure multi-signature protocols</li>
                                <li>• Daily balance verification</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold mb-4">Operational Excellence</h4>
                            <ul class="space-y-2 text-slate-500">
                                <li>• 24/7 monitoring and support</li>
                                <li>• Automated compliance reporting</li>
                                <li>• Instant fund transfers</li>
                                <li>• Transparent fee structure</li>
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Banking Security You Can Trust</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Experience the peace of mind that comes with multi-bank protection and government insurance
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Open Account
                </a>
                <a href="{{ route('security') }}" class="btn-outline px-8 py-4 text-lg">
                    Learn About Security
                </a>
            </div>
        </div>
    </section>

@endsection