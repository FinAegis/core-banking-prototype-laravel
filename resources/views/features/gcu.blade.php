@extends('layouts.public')

@section('title', 'Global Currency Unit (GCU) - FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Global Currency Unit (GCU)',
        'description' => 'Learn about the Global Currency Unit (GCU) - FinAegis\'s innovative basket currency with democratic governance and stable value.',
        'keywords' => 'GCU, global currency unit, basket currency, democratic governance, stable value, FinAegis',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Global Currency Unit', 'url' => url('/features/gcu')]
    ]" />
@endsection

@push('styles')
<style>
    .gradient-bg-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .feature-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }
    .gcu-symbol {
        font-family: 'Inter', sans-serif;
        font-weight: 700;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="pt-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <div class="inline-flex items-center px-4 py-2 bg-indigo-100 rounded-full mb-6">
                    <span class="text-indigo-600 font-semibold">Revolutionary Currency</span>
                </div>
                <h1 class="text-5xl font-bold text-gray-900 mb-6">Global Currency Unit (GCU)</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    A revolutionary basket currency designed for stability, transparency, and democratic governance in the global economy.
                </p>
            </div>
        </div>
    </section>

    <!-- Overview Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">What is the GCU?</h2>
                    <p class="text-lg text-gray-600 mb-4">
                        The Global Currency Unit is FinAegis's flagship innovation - a basket currency that combines the stability of multiple fiat currencies with the transparency of blockchain technology.
                    </p>
                    <p class="text-lg text-gray-600 mb-4">
                        Unlike traditional currencies controlled by single nations or institutions, the GCU is governed democratically by its holders, ensuring that monetary policy serves the community rather than special interests.
                    </p>
                    <p class="text-lg text-gray-600">
                        Each GCU is backed 1:1 by a diversified basket of major currencies held across multiple regulated banks, providing unprecedented security and stability.
                    </p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">Current Composition</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg">USD (US Dollar)</span>
                            <span class="text-lg font-semibold">40%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: 40%"></div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg">EUR (Euro)</span>
                            <span class="text-lg font-semibold">30%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full" style="width: 30%"></div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg">GBP (British Pound)</span>
                            <span class="text-lg font-semibold">15%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-pink-600 h-2 rounded-full" style="width: 15%"></div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg">CHF (Swiss Franc)</span>
                            <span class="text-lg font-semibold">10%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 10%"></div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg">JPY (Japanese Yen)</span>
                            <span class="text-lg font-semibold">3%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 3%"></div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg">XAU (Gold)</span>
                            <span class="text-lg font-semibold">2%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: 2%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Key Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Stable Value</h3>
                    <p class="text-gray-600">
                        Diversified basket design minimizes volatility and provides predictable value preservation across economic cycles.
                    </p>
                </div>
                
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Democratic Governance</h3>
                    <p class="text-gray-600">
                        GCU holders vote on basket composition, rebalancing frequency, and other key monetary policy decisions.
                    </p>
                </div>
                
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Full Backing</h3>
                    <p class="text-gray-600">
                        Every GCU is backed 1:1 by real currency reserves held at regulated banks with daily audits and transparency reports.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Benefits of Using GCU</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">For Individuals</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Protection from Currency Volatility</h4>
                                <p class="text-gray-600">Diversification across multiple currencies reduces exposure to single-country economic risks.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Global Acceptance</h4>
                                <p class="text-gray-600">Use GCU for international transactions without worrying about exchange rates.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Transparent Governance</h4>
                                <p class="text-gray-600">Participate in decisions about the currency you use daily.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">For Businesses</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Simplified International Trade</h4>
                                <p class="text-gray-600">One currency for global operations reduces complexity and hedging costs.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Predictable Value</h4>
                                <p class="text-gray-600">Stable pricing for long-term contracts and financial planning.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Lower Transaction Costs</h4>
                                <p class="text-gray-600">Eliminate multiple currency conversions and associated fees.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Governance Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Democratic Governance Model</h2>
            
            <div class="max-w-4xl mx-auto">
                <p class="text-lg text-gray-600 mb-8 text-center">
                    GCU holders participate in key decisions through our transparent voting system. Your GCU holdings determine your voting power.
                </p>
                
                <div class="bg-white rounded-2xl p-8 shadow-lg">
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">What GCU Holders Vote On</h3>
                    
                    <div class="space-y-6">
                        <div class="border-l-4 border-indigo-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Basket Composition</h4>
                            <p class="text-gray-600">Monthly voting on currency weightings and potential additions or removals of currencies.</p>
                        </div>
                        
                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Rebalancing Frequency</h4>
                            <p class="text-gray-600">Determine how often the basket should be rebalanced to maintain target weightings.</p>
                        </div>
                        
                        <div class="border-l-4 border-pink-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Reserve Management</h4>
                            <p class="text-gray-600">Policies for how reserves are held, which banks to use, and risk management strategies.</p>
                        </div>
                        
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Fee Structure</h4>
                            <p class="text-gray-600">Transaction fees, conversion costs, and how revenue is used to improve the system.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Implementation -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Technical Implementation</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">Smart Contract Architecture</h3>
                    <ul class="space-y-3 text-gray-600">
                        <li>• Basket currency implementation with weighted composition</li>
                        <li>• Multi-bank treasury management across 5 partner banks</li>
                        <li>• On-chain voting mechanisms with asset-weighted power</li>
                        <li>• Automated rebalancing protocols on the 10th of each month</li>
                        <li>• Real-time audit trails and transparency reports</li>
                        <li>• Oracle integration for real-time exchange rates</li>
                    </ul>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">Security & Compliance</h3>
                    <ul class="space-y-3 text-gray-600">
                        <li>• Bank-grade security infrastructure</li>
                        <li>• Government deposit insurance up to €100k per bank</li>
                        <li>• Daily third-party audits and reconciliation</li>
                        <li>• Segregated customer assets across partner banks</li>
                        <li>• Multi-signature authorization for treasury operations</li>
                        <li>• Transparent reporting dashboard with real-time data</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-indigo-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6 text-white">Start Using GCU Today</h2>
            <p class="text-xl mb-8 text-indigo-100">Join thousands of users already benefiting from the stability and transparency of the Global Currency Unit</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                    Open an Account
                </a>
                <a href="{{ route('gcu') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    View Live Stats
                </a>
            </div>
        </div>
    </section>

@endsection