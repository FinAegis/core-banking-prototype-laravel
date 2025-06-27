<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Global Currency Unit (GCU) - The world's first democratically governed basket currency. Real bank backing, government insurance, community control.">
        <meta name="keywords" content="GCU, global currency unit, democratic banking, basket currency, FinAegis">
        
        <title>Global Currency Unit (GCU) - FinAegis</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .feature-card {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-alpha-banner />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-5xl md:text-6xl font-bold mb-6">
                            Global Currency Unit
                        </h1>
                        <p class="text-xl md:text-2xl text-purple-100 mb-8">
                            The world's first democratically governed basket currency. Real bank backing, government insurance, community control.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl text-center">
                                Open GCU Account
                            </a>
                            <a href="#composition" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition text-center">
                                View Composition
                            </a>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-3 gap-6 mt-12">
                            <div>
                                <div class="text-3xl font-bold">{{ config('platform.statistics.supported_currencies') }}+</div>
                                <div class="text-purple-200 text-sm">Currencies</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold">100%</div>
                                <div class="text-purple-200 text-sm">Bank Backed</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold">€100k</div>
                                <div class="text-purple-200 text-sm">Insured</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center lg:justify-end">
                        <div class="relative">
                            <div class="absolute inset-0 bg-white/20 backdrop-blur-sm rounded-3xl"></div>
                            <div class="relative bg-white rounded-3xl p-12 shadow-2xl">
                                <div class="text-8xl md:text-9xl font-bold bg-gradient-to-br from-indigo-600 to-purple-600 bg-clip-text text-transparent text-center">
                                    Ǥ
                                </div>
                                <p class="text-gray-600 text-center mt-4">The GCU Symbol</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Current Composition -->
        <section id="composition" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Current Composition</h2>
                    <p class="text-xl text-gray-600">Democratically determined, optimized for stability</p>
                </div>
                
                <div class="max-w-4xl mx-auto">
                    <div class="bg-gray-50 rounded-3xl p-8 shadow-xl">
                        <div class="space-y-8">
                            @php
                                $composition = config('platform.gcu.composition');
                                $colors = [
                                    'USD' => 'from-blue-500 to-blue-600',
                                    'EUR' => 'from-indigo-500 to-indigo-600',
                                    'GBP' => 'from-purple-500 to-purple-600',
                                    'CHF' => 'from-red-500 to-red-600',
                                    'JPY' => 'from-pink-500 to-pink-600',
                                    'XAU' => 'from-yellow-500 to-yellow-600'
                                ];
                                $flags = ['USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'CHF' => '🇨🇭', 'JPY' => '🇯🇵', 'XAU' => '🏆'];
                                $names = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'CHF' => 'Swiss Franc', 'JPY' => 'Japanese Yen', 'XAU' => 'Gold'];
                            @endphp
                            
                            @foreach($composition as $currency => $percentage)
                            <div class="group">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-3xl">{{ $flags[$currency] }}</span>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ $names[$currency] }}</h4>
                                            <p class="text-sm text-gray-600">{{ $currency }}</p>
                                        </div>
                                    </div>
                                    <span class="text-2xl font-bold text-gray-900">{{ $percentage }}%</span>
                                </div>
                                <div class="relative h-4 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="absolute inset-0 bg-gradient-to-r {{ $colors[$currency] }} rounded-full transition-all duration-1000 ease-out"
                                         style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-10 pt-8 border-t border-gray-200">
                            <div class="text-center">
                                @if(config('platform.gcu.voting_enabled'))
                                    @php
                                        $nextVoting = \Carbon\Carbon::parse(config('platform.gcu.next_voting_date'));
                                        $daysUntil = now()->diffInDays($nextVoting);
                                    @endphp
                                    <p class="text-lg text-gray-700">
                                        Next voting round in <span class="font-bold text-indigo-600">{{ $daysUntil }} days</span>
                                    </p>
                                @else
                                    <div class="inline-flex items-center px-4 py-2 bg-amber-100 text-amber-800 rounded-full">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="font-medium">Democratic voting coming soon</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">How GCU Works</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Revolutionary technology meets traditional banking security
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 mb-16">
                    <div class="relative">
                        <div class="bg-white rounded-2xl p-8 shadow-lg h-full feature-card">
                            <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center mb-6">
                                <span class="text-2xl font-bold text-indigo-600">1</span>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Deposit Funds</h3>
                            <p class="text-gray-600">
                                Convert any supported currency to GCU instantly at transparent rates
                            </p>
                        </div>
                        <div class="hidden md:block absolute top-1/2 right-0 transform translate-x-1/2 -translate-y-1/2">
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="bg-white rounded-2xl p-8 shadow-lg h-full feature-card">
                            <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-6">
                                <span class="text-2xl font-bold text-purple-600">2</span>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Bank Storage</h3>
                            <p class="text-gray-600">
                                Funds held in {{ config('platform.statistics.banking_partners') }} partner banks with government insurance
                            </p>
                        </div>
                        <div class="hidden md:block absolute top-1/2 right-0 transform translate-x-1/2 -translate-y-1/2">
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-8 shadow-lg feature-card">
                        <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-6">
                            <span class="text-2xl font-bold text-green-600">3</span>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Community Voting</h3>
                        <p class="text-gray-600">
                            Vote monthly on optimal currency composition based on global conditions
                        </p>
                    </div>
                </div>

                <!-- Democratic Process -->
                <div class="bg-white rounded-3xl shadow-xl p-12">
                    <h3 class="text-2xl font-bold text-center text-gray-900 mb-12">The Democratic Process</h3>
                    
                    <div class="grid md:grid-cols-2 gap-12">
                        <div class="space-y-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold mb-2">Monthly Proposals</h4>
                                    <p class="text-gray-600">
                                        Economic experts and community members submit currency basket proposals
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-3-4H9a2 2 0 01-2-2V10a2 2 0 012-2h8m0 0V6a2 2 0 00-2-2H7a2 2 0 00-2 2v6h10z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold mb-2">Community Discussion</h4>
                                    <p class="text-gray-600">
                                        Open debate with data-driven analysis and risk assessments
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold mb-2">Weighted Voting</h4>
                                    <p class="text-gray-600">
                                        GCU holders vote proportionally to their holdings
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold mb-2">Automatic Execution</h4>
                                    <p class="text-gray-600">
                                        Winning composition automatically rebalanced across all banks
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Why Choose GCU?</h2>
                    <p class="text-xl text-gray-600">The perfect balance of innovation and security</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Stability Through Diversity</h3>
                            <p class="text-gray-600">
                                Multi-currency basket reduces volatility and protects against single-currency risks
                            </p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Government Protection</h3>
                            <p class="text-gray-600">
                                Funds in regulated banks with €100,000 deposit insurance per bank
                            </p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Democratic Control</h3>
                            <p class="text-gray-600">
                                Your voice matters - vote on currency composition monthly
                            </p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Global Acceptance</h3>
                            <p class="text-gray-600">
                                Instant conversion to any local currency with transparent fees
                            </p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Full Transparency</h3>
                            <p class="text-gray-600">
                                Real-time reporting of reserves, allocations, and voting results
                            </p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-2xl p-8 h-full">
                            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">Instant Transfers</h3>
                            <p class="text-gray-600">
                                Send GCU globally in seconds, convert to any currency instantly
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banking Partners -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Banking Partners</h2>
                    <p class="text-xl text-gray-600">Your funds secured with regulated European banks</p>
                </div>
                
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <p class="text-center text-gray-600 mb-8">
                        Currently integrating with {{ config('platform.statistics.banking_partners') }} regulated banks across Europe
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <div class="px-6 py-3 bg-green-100 text-green-700 rounded-full font-medium">
                            €100,000 deposit insurance per bank
                        </div>
                        <div class="px-6 py-3 bg-blue-100 text-blue-700 rounded-full font-medium">
                            PSD2 compliant
                        </div>
                        <div class="px-6 py-3 bg-purple-100 text-purple-700 rounded-full font-medium">
                            Real-time reporting
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Join the Future of Money</h2>
                <p class="text-xl text-purple-100 mb-8">
                    Open your GCU account today and be part of the democratic banking revolution
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Create Free Account
                    </a>
                    <a href="{{ route('support.contact') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Contact Sales
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')
    </body>
</html>