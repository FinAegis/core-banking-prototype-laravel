@extends('layouts.public')

@section('title', 'Instant Settlements - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Instant Settlements',
        'description' => 'Experience sub-second transaction processing with our advanced settlement engine. No more waiting days for transfers.',
        'keywords' => 'instant settlements, real-time transactions, fast transfers, payment processing, ' . config('brand.name', 'Zelta') . '',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Instant Settlements', 'url' => url('/features/settlements')]
    ]" />
@endsection

@push('styles')
<style>
    .timeline-item {
        position: relative;
        padding-left: 2rem;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0.5rem;
        bottom: -2rem;
        width: 2px;
        background: #e5e7eb;
    }
    .timeline-item:last-child::before {
        display: none;
    }
    .timeline-dot {
        position: absolute;
        left: -0.5rem;
        top: 0.5rem;
        width: 1rem;
        height: 1rem;
        background: #10b981;
        border-radius: 50%;
        border: 3px solid white;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Instant Settlements</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Say goodbye to waiting. Our advanced settlement engine processes transactions in milliseconds, not days.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Speed Comparison -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Speed That Matters</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold mb-6">Traditional Banking vs {{ config('brand.name', 'Zelta') }}</h3>
                    <div class="space-y-6">
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="font-semibold">Domestic Transfers</span>
                                <span class="text-green-600 font-bold">< 1 second</span>
                            </div>
                            <div class="relative">
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full" style="width: 95%"></div>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">Traditional: 1-2 business days</div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="font-semibold">International Transfers</span>
                                <span class="text-green-600 font-bold">< 3 seconds</span>
                            </div>
                            <div class="relative">
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full" style="width: 90%"></div>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">Traditional: 3-5 business days</div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="font-semibold">Cross-Currency</span>
                                <span class="text-green-600 font-bold">< 2 seconds</span>
                            </div>
                            <div class="relative">
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full" style="width: 92%"></div>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">Traditional: 2-3 business days</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-8">
                    <h4 class="text-xl font-bold mb-6">Performance Metrics</h4>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-600 mb-2">< 100ms</div>
                            <p class="text-slate-500">Average Settlement Time</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-purple-600 mb-2">99.99%</div>
                            <p class="text-slate-500">Success Rate</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-green-600 mb-2">10K+</div>
                            <p class="text-slate-500">TPS Capacity</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-pink-600 mb-2">24/7</div>
                            <p class="text-slate-500">Availability</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">How Instant Settlement Works</h2>
            
            <div class="max-w-3xl mx-auto">
                <div class="space-y-8">
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-bold text-lg mb-2">1. Transaction Initiated</h4>
                            <p class="text-slate-500">User initiates transfer through API or interface with full validation.</p>
                            <p class="text-sm text-green-600 mt-2">Time: < 10ms</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-bold text-lg mb-2">2. Instant Validation</h4>
                            <p class="text-slate-500">Balance checks, compliance screening, and fraud detection in parallel.</p>
                            <p class="text-sm text-green-600 mt-2">Time: < 20ms</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-bold text-lg mb-2">3. Atomic Processing</h4>
                            <p class="text-slate-500">Event sourcing ensures transaction atomicity with immediate consistency.</p>
                            <p class="text-sm text-green-600 mt-2">Time: < 30ms</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="bg-white rounded-lg p-6 shadow-md">
                            <h4 class="font-bold text-lg mb-2">4. Real-time Notification</h4>
                            <p class="text-slate-500">Instant webhook notifications and balance updates to all parties.</p>
                            <p class="text-sm text-green-600 mt-2">Time: < 40ms</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-12 text-center">
                    <div class="inline-flex items-center bg-green-100 text-green-800 px-6 py-3 rounded-full">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span class="font-semibold">Total Time: Under 100 milliseconds</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Settlement Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Guaranteed Finality</h3>
                    <p class="text-slate-500">Once confirmed, transactions are irreversible and immediately final.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Global Reach</h3>
                    <p class="text-slate-500">Send money anywhere in the world at the same lightning speed.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">24/7 Operations</h3>
                    <p class="text-slate-500">No banking hours, no weekends, no holidays. Always instant.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Technology Stack -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Powered by Advanced Technology</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-2xl font-bold mb-6">Event Sourcing Architecture</h3>
                    <ul class="space-y-3 text-slate-500">
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Complete audit trail of all financial operations
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Immutable transaction history with cryptographic verification
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Event replay capability for system recovery
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Real-time event streaming for instant notifications
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-2xl font-bold mb-6">Performance Optimization</h3>
                    <ul class="space-y-3 text-slate-500">
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Redis caching for sub-millisecond data access
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Optimized database queries with proper indexing
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Horizontal scaling for unlimited throughput
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2">•</span>
                            Load balancing across multiple processing nodes
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Perfect For</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="bg-gray-50 rounded-xl p-6">
                    <h4 class="font-bold text-lg mb-3">E-commerce</h4>
                    <p class="text-slate-500">Instant payment confirmation for better customer experience.</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-6">
                    <h4 class="font-bold text-lg mb-3">Payroll</h4>
                    <p class="text-slate-500">Pay employees instantly, any day of the week.</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-6">
                    <h4 class="font-bold text-lg mb-3">Trading</h4>
                    <p class="text-slate-500">Execute trades and settle funds in real-time.</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-6">
                    <h4 class="font-bold text-lg mb-3">Remittances</h4>
                    <p class="text-slate-500">Send money home instantly at low cost.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Experience Instant Settlements</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Join the future of banking where waiting is a thing of the past
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Start Now
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    Developer Docs
                </a>
            </div>
        </div>
    </section>

@endsection