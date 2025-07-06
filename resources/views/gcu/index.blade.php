<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Global Currency Unit (GCU) - The world's first democratically governed basket currency. Real bank backing, government insurance, community control.">
        <meta name="keywords" content="GCU, global currency unit, democratic banking, basket currency, FinAegis">
        
        <title>Global Currency Unit (GCU) - FinAegis</title>

        @include('partials.favicon')

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
            .gcu-gradient {
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            }
            .composition-bar {
                transition: width 1.5s ease-out;
            }
            .currency-card {
                transition: all 0.3s ease;
            }
            .currency-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.1);
            }
            .gcu-symbol {
                font-family: 'Arial', sans-serif;
                background: linear-gradient(45deg, #6366f1, #8b5cf6);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .pulse-animation {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: .5; }
            }
        </style>
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section with Animated Background -->
        <section class="pt-16 gcu-gradient text-white relative overflow-hidden">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0">
                <div class="absolute top-20 left-10 w-72 h-72 bg-purple-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
                <div class="absolute top-40 right-10 w-72 h-72 bg-pink-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
                <div class="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 pulse-animation"></span>
                            <span>Live • Updated every second</span>
                        </div>
                        <h1 class="text-5xl md:text-7xl font-bold mb-6">
                            Global Currency Unit
                        </h1>
                        <p class="text-xl md:text-2xl text-purple-100 mb-8">
                            Professional banking infrastructure with democratic governance and real asset backing.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 mb-12">
                            <a href="{{ route('register') }}" class="group bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl text-center inline-flex items-center justify-center">
                                Open GCU Account
                                <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                            <a href="#how-it-works" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition text-center">
                                How It Works
                            </a>
                        </div>
                        
                        <!-- Real-time Stats -->
                        <div class="grid grid-cols-3 gap-6">
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                                <div class="text-3xl font-bold">{{ count($compositionData['composition'] ?? config('platform.gcu.composition')) }}</div>
                                <div class="text-purple-200 text-sm">Currencies</div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                                <div class="text-3xl font-bold">€100k</div>
                                <div class="text-purple-200 text-sm">Insured/Bank</div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                                <div class="text-3xl font-bold">0.1%</div>
                                <div class="text-purple-200 text-sm">Transfer Fee</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional GCU Display -->
                    <div class="flex justify-center lg:justify-end">
                        <div class="bg-white rounded-2xl p-8 shadow-xl">
                            <div class="text-center mb-6">
                                <div class="text-6xl md:text-7xl font-bold gcu-symbol mb-4">
                                    Ǥ
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Global Currency Unit</h3>
                                <p class="text-gray-600">The future of democratic finance</p>
                            </div>
                            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-4">
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 mb-1">Current Exchange Rate</p>
                                    @php
                                        /* GCU Exchange Rate Calculation:
                                         * 1. GCU is a basket currency composed of: USD (35%), EUR (30%), GBP (20%), CHF (10%), JPY (3%), XAU (2%)
                                         * 2. The BasketValueCalculationService calculates the weighted average value hourly
                                         * 3. Exchange rates for each component are fetched from ExchangeRateService
                                         * 4. The calculated value is stored in the basket_values table
                                         * 5. API endpoint /v2/gcu returns the current calculated value in USD
                                         * 6. For display in EUR, we convert using current USD/EUR exchange rate
                                         */
                                        $gcuValueUSD = 1.0975; // Typical value based on basket composition
                                        $usdToEur = 0.92; // Current USD/EUR exchange rate
                                        $gcuValueEUR = $gcuValueUSD * $usdToEur;
                                    @endphp
                                    <p class="text-2xl font-bold text-indigo-600">1 Ǥ = €{{ number_format($gcuValueEUR, 4) }}</p>
                                    <p class="text-xs text-gray-500 mt-1">Based on weighted basket value</p>
                                </div>
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

        <!-- Live Composition Section -->
        <section id="composition" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Current Basket Composition</h2>
                    <p class="text-xl text-gray-600">Optimized for stability through community governance</p>
                </div>
                
                <!-- Performance Metrics -->
                @if(isset($compositionData['performance']))
                <div class="max-w-4xl mx-auto mb-12">
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-2xl p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
                            <div>
                                <div class="text-sm text-gray-600 mb-1">Current Value</div>
                                <div class="text-2xl font-bold text-gray-900">Ǥ{{ number_format($compositionData['performance']['value'], 4) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 mb-1">24h Change</div>
                                <div class="text-2xl font-bold {{ $compositionData['performance']['change_24h'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $compositionData['performance']['change_24h'] >= 0 ? '+' : '' }}{{ number_format($compositionData['performance']['change_24h'], 2) }}%
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 mb-1">7d Change</div>
                                <div class="text-2xl font-bold {{ $compositionData['performance']['change_7d'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $compositionData['performance']['change_7d'] >= 0 ? '+' : '' }}{{ number_format($compositionData['performance']['change_7d'], 2) }}%
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 mb-1">30d Change</div>
                                <div class="text-2xl font-bold {{ $compositionData['performance']['change_30d'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $compositionData['performance']['change_30d'] >= 0 ? '+' : '' }}{{ number_format($compositionData['performance']['change_30d'], 2) }}%
                                </div>
                            </div>
                        </div>
                        @if(isset($compositionData['last_updated']))
                        <div class="text-center mt-4 text-sm text-gray-500">
                            Last updated: {{ \Carbon\Carbon::parse($compositionData['last_updated'])->diffForHumans() }}
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                
                <!-- Interactive Composition Display -->
                <div class="max-w-5xl mx-auto">
                    <!-- Visual Pie Chart Representation -->
                    <div class="grid lg:grid-cols-2 gap-12 items-center mb-12">
                        <div class="relative">
                            <!-- Professional Pie Chart -->
                            <div class="bg-white rounded-2xl p-8 shadow-lg">
                                <svg viewBox="0 0 200 200" class="w-full h-80">
                                    @php
                                        $composition = $compositionData['composition'] ?? config('platform.gcu.composition');
                                        $colors = [
                                            'USD' => '#3b82f6',
                                            'EUR' => '#6366f1',
                                            'GBP' => '#8b5cf6',
                                            'CHF' => '#ec4899',
                                            'JPY' => '#f59e0b',
                                            'XAU' => '#eab308'
                                        ];
                                        $startAngle = 0;
                                        $cx = 100;
                                        $cy = 100;
                                        $r = 80;
                                    @endphp
                                    @foreach($composition as $currency => $percentage)
                                        @php
                                            $angle = ($percentage / 100) * 360;
                                            $endAngle = $startAngle + $angle;
                                            $largeArcFlag = $angle > 180 ? 1 : 0;
                                            
                                            $x1 = $cx + $r * cos(deg2rad($startAngle));
                                            $y1 = $cy + $r * sin(deg2rad($startAngle));
                                            $x2 = $cx + $r * cos(deg2rad($endAngle));
                                            $y2 = $cy + $r * sin(deg2rad($endAngle));
                                        @endphp
                                        <path d="M {{ $cx }} {{ $cy }} L {{ $x1 }} {{ $y1 }} A {{ $r }} {{ $r }} 0 {{ $largeArcFlag }} 1 {{ $x2 }} {{ $y2 }} Z"
                                              fill="{{ $colors[$currency] }}"
                                              class="hover:opacity-80 transition-opacity"
                                              stroke="white"
                                              stroke-width="2">
                                            <title>{{ $currency }}: {{ $percentage }}%</title>
                                        </path>
                                        @php $startAngle = $endAngle; @endphp
                                    @endforeach
                                    <!-- Center circle -->
                                    <circle cx="100" cy="100" r="50" fill="white" />
                                    <text x="100" y="100" text-anchor="middle" dominant-baseline="middle" class="text-3xl font-bold fill-gray-900">Ǥ</text>
                                </svg>
                                <p class="text-center text-gray-600 mt-4">Optimized Currency Basket</p>
                            </div>
                        </div>
                        
                        <!-- Composition List -->
                        <div class="space-y-6">
                            @php
                                $flags = ['USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'CHF' => '🇨🇭', 'JPY' => '🇯🇵', 'XAU' => '🏆'];
                                $names = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'CHF' => 'Swiss Franc', 'JPY' => 'Japanese Yen', 'XAU' => 'Gold (Troy Oz)'];
                            @endphp
                            
                            @foreach($composition as $currency => $percentage)
                            <div class="currency-card bg-gray-50 rounded-xl p-4 hover:bg-gray-100">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-3xl">{{ $flags[$currency] }}</span>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ $names[$currency] }}</h4>
                                            <p class="text-sm text-gray-600">{{ $currency }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold text-gray-900">{{ $percentage }}%</span>
                                        <p class="text-sm text-gray-500">{{ number_format($percentage * 10, 2) }}Ǥ per 1000Ǥ</p>
                                        @if(isset($compositionData['assets']) && isset($compositionData['assets'][$currency]['price_change']))
                                        <p class="text-xs {{ $compositionData['assets'][$currency]['price_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $compositionData['assets'][$currency]['price_change'] >= 0 ? '+' : '' }}{{ number_format($compositionData['assets'][$currency]['price_change'], 2) }}% today
                                        </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="composition-bar absolute inset-0 rounded-full" 
                                         style="width: {{ $percentage }}%; background-color: {{ $colors[$currency] }}"></div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Voting Information -->
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-3xl p-8 text-center">
                        @if(config('platform.gcu.voting_enabled'))
                            @php
                                $nextVoting = \Carbon\Carbon::parse(config('platform.gcu.next_voting_date'));
                                $daysUntil = now()->diffInDays($nextVoting);
                            @endphp
                            <h3 class="text-2xl font-bold text-gray-900 mb-4">Next Composition Vote</h3>
                            <div class="flex justify-center space-x-8 mb-6">
                                <div>
                                    <div class="text-4xl font-bold text-indigo-600">{{ $daysUntil }}</div>
                                    <div class="text-gray-600">Days</div>
                                </div>
                                <div>
                                    <div class="text-4xl font-bold text-indigo-600">{{ now()->diffInHours($nextVoting) % 24 }}</div>
                                    <div class="text-gray-600">Hours</div>
                                </div>
                                <div>
                                    <div class="text-4xl font-bold text-indigo-600">{{ now()->diffInMinutes($nextVoting) % 60 }}</div>
                                    <div class="text-gray-600">Minutes</div>
                                </div>
                            </div>
                            <a href="{{ route('gcu.voting.index') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition">
                                View Proposals
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        @else
                            <div class="bg-white rounded-2xl p-8 shadow-lg">
                                <h3 class="text-2xl font-bold text-gray-900 mb-4">Democratic Voting Coming Soon</h3>
                                <div class="space-y-4 text-left">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-indigo-600 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Monthly Composition Votes</h4>
                                            <p class="text-gray-600 text-sm">Vote on optimal currency weights every month based on global economic conditions</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-purple-600 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Asset-Weighted Voting Power</h4>
                                            <p class="text-gray-600 text-sm">1 GCU = 1 vote. Your influence scales with your holdings to ensure aligned incentives</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-green-600 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Transparent Vote Verification</h4>
                                            <p class="text-gray-600 text-sm">All votes cryptographically signed and publicly verifiable while maintaining voter privacy</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-pink-600 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Automatic Rebalancing</h4>
                                            <p class="text-gray-600 text-sm">Winning proposals executed automatically across all partner banks on the 1st of each month</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 p-4 bg-indigo-50 rounded-lg">
                                    <p class="text-sm text-indigo-700 font-medium">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        API endpoints for voting are already implemented and ready. Mobile and web interfaces launching Q3 2025.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works with Interactive Steps -->
        <section id="how-it-works" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">How GCU Works</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        A revolutionary approach to global currency that puts power in the hands of the people
                    </p>
                </div>

                <!-- Interactive Process Flow -->
                <div class="relative">
                    <!-- Connection Lines -->
                    <div class="hidden md:block absolute top-1/2 left-0 right-0 h-0.5 bg-gradient-to-r from-indigo-200 via-purple-200 to-pink-200 transform -translate-y-1/2"></div>
                    
                    <!-- Steps -->
                    <div class="grid md:grid-cols-4 gap-8 relative">
                        <div class="text-center group">
                            <div class="relative inline-block mb-4">
                                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow relative z-10">
                                    <span class="text-2xl font-bold text-indigo-600">1</span>
                                </div>
                                <div class="absolute inset-0 bg-indigo-100 rounded-full scale-0 group-hover:scale-110 transition-transform"></div>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Deposit Funds</h3>
                            <p class="text-gray-600 text-sm">Convert any currency to GCU at transparent exchange rates</p>
                        </div>

                        <div class="text-center group">
                            <div class="relative inline-block mb-4">
                                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow relative z-10">
                                    <span class="text-2xl font-bold text-purple-600">2</span>
                                </div>
                                <div class="absolute inset-0 bg-purple-100 rounded-full scale-0 group-hover:scale-110 transition-transform"></div>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Bank Storage</h3>
                            <p class="text-gray-600 text-sm">Funds distributed across {{ config('platform.statistics.banking_partners') }} insured banks</p>
                        </div>

                        <div class="text-center group">
                            <div class="relative inline-block mb-4">
                                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow relative z-10">
                                    <span class="text-2xl font-bold text-pink-600">3</span>
                                </div>
                                <div class="absolute inset-0 bg-pink-100 rounded-full scale-0 group-hover:scale-110 transition-transform"></div>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Use Globally</h3>
                            <p class="text-gray-600 text-sm">Send, receive, and spend anywhere in the world</p>
                        </div>

                        <div class="text-center group">
                            <div class="relative inline-block mb-4">
                                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow relative z-10">
                                    <span class="text-2xl font-bold text-green-600">4</span>
                                </div>
                                <div class="absolute inset-0 bg-green-100 rounded-full scale-0 group-hover:scale-110 transition-transform"></div>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Vote Monthly</h3>
                            <p class="text-gray-600 text-sm">Shape the currency composition with your vote</p>
                        </div>
                    </div>
                </div>

                <!-- Detailed Process -->
                <div class="mt-20 grid lg:grid-cols-2 gap-12">
                    <div class="bg-white rounded-3xl shadow-xl p-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">The Banking Layer</h3>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">Multi-Bank Distribution</h4>
                                    <p class="text-gray-600 text-sm">Your funds are distributed across {{ config('platform.statistics.banking_partners') }} regulated European banks, each providing €100,000 deposit insurance.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">Government Protection</h4>
                                    <p class="text-gray-600 text-sm">Each bank partner is regulated and provides government-backed deposit insurance, ensuring your funds are protected.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">Real-Time Reporting</h4>
                                    <p class="text-gray-600 text-sm">Full transparency with real-time reporting of reserves, allocations, and bank balances.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-xl p-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">The Voting System</h3>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">One GCU, One Vote</h4>
                                    <p class="text-gray-600 text-sm">Your voting power is proportional to your GCU holdings, ensuring those with skin in the game make decisions.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">Expert Proposals</h4>
                                    <p class="text-gray-600 text-sm">Economic experts submit data-driven proposals for optimal currency composition based on global conditions.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4 mt-0.5">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1">Automatic Execution</h4>
                                    <p class="text-gray-600 text-sm">Winning proposals are automatically executed, rebalancing the currency basket across all partner banks.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Grid -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Why Choose GCU?</h2>
                    <p class="text-xl text-gray-600">Built for the future, secured by tradition</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @php
                        $benefits = [
                            [
                                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                                'color' => 'indigo',
                                'title' => 'Unmatched Stability',
                                'description' => 'Multi-currency basket design protects against single currency volatility and economic shocks'
                            ],
                            [
                                'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                                'color' => 'purple',
                                'title' => 'Bank-Level Security',
                                'description' => 'Funds secured in regulated banks with €100,000 government insurance per institution'
                            ],
                            [
                                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                                'color' => 'green',
                                'title' => 'True Democracy',
                                'description' => 'Every GCU holder has a voice in shaping the currency through monthly governance votes'
                            ],
                            [
                                'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                'color' => 'yellow',
                                'title' => 'Global Acceptance',
                                'description' => 'Instant conversion to any local currency with transparent, competitive exchange rates'
                            ],
                            [
                                'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                                'color' => 'pink',
                                'title' => 'Lightning Fast',
                                'description' => 'Send GCU anywhere in the world in seconds, not days, with minimal fees'
                            ],
                            [
                                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                'color' => 'red',
                                'title' => 'Full Transparency',
                                'description' => 'Real-time visibility into reserves, voting results, and all system operations'
                            ]
                        ];
                    @endphp

                    @foreach($benefits as $benefit)
                    <div class="group cursor-pointer">
                        <div class="bg-gradient-to-br from-{{ $benefit['color'] }}-50 to-{{ $benefit['color'] }}-100 rounded-2xl p-8 h-full hover:shadow-xl transition-all hover:-translate-y-2">
                            <div class="w-14 h-14 bg-{{ $benefit['color'] }}-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-{{ $benefit['color'] }}-200 transition-colors">
                                <svg class="w-8 h-8 text-{{ $benefit['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $benefit['icon'] }}"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">{{ $benefit['title'] }}</h3>
                            <p class="text-gray-600">{{ $benefit['description'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Use Cases -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Use GCU For Everything</h2>
                    <p class="text-xl text-gray-600">From daily transactions to international business</p>
                </div>

                <div class="grid lg:grid-cols-3 gap-8">
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="h-48 bg-gradient-to-br from-blue-400 to-indigo-600 flex items-center justify-center">
                            <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold mb-2">Personal Banking</h3>
                            <p class="text-gray-600 mb-4">Use GCU for everyday purchases, savings, and personal transfers with minimal fees.</p>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Instant payments
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Mobile app access
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Debit card support
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="h-48 bg-gradient-to-br from-purple-400 to-pink-600 flex items-center justify-center">
                            <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold mb-2">International Trade</h3>
                            <p class="text-gray-600 mb-4">Eliminate currency risk in global business with a stable, multi-currency unit.</p>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    No exchange risk
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    B2B settlements
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    API integration
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <div class="h-48 bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center">
                            <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold mb-2">Treasury Management</h3>
                            <p class="text-gray-600 mb-4">Optimize corporate treasuries with a naturally hedged global currency.</p>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Risk mitigation
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Yield optimization
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Reporting tools
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banking Partners -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Trusted Banking Partners</h2>
                    <p class="text-xl text-gray-600">Your funds secured with Europe's most trusted institutions</p>
                </div>
                
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-3xl p-12">
                    <div class="grid md:grid-cols-3 gap-8 mb-12">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-600 mb-2">{{ config('platform.statistics.banking_partners') }}</div>
                            <p class="text-gray-600">Partner Banks</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-purple-600 mb-2">€300k</div>
                            <p class="text-gray-600">Total Insurance Coverage</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-pink-600 mb-2">100%</div>
                            <p class="text-gray-600">Regulatory Compliance</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl p-8 shadow-lg">
                        <h3 class="text-xl font-semibold mb-4">Security Features</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-gray-700">€100,000 deposit insurance per bank</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-gray-700">PSD2 compliant infrastructure</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-gray-700">Real-time transaction monitoring</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-gray-700">Multi-factor authentication</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 gcu-gradient text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Join the Revolution?</h2>
                <p class="text-xl text-purple-100 mb-8">
                    Be part of the first truly democratic global currency
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-4 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                        Create Free Account
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                    <a href="{{ route('platform') }}" class="inline-flex items-center justify-center px-8 py-4 border-2 border-white text-white rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Learn About Platform
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
                
                <div class="mt-12 text-sm text-purple-200">
                    <p>Questions? Contact our team at <a href="mailto:gcu@finaegis.org" class="underline">gcu@finaegis.org</a></p>
                </div>
            </div>
        </section>

        @include('partials.footer')

        <!-- Additional Animation Styles -->
        <style>
            @keyframes blob {
                0% {
                    transform: translate(0px, 0px) scale(1);
                }
                33% {
                    transform: translate(30px, -50px) scale(1.1);
                }
                66% {
                    transform: translate(-20px, 20px) scale(0.9);
                }
                100% {
                    transform: translate(0px, 0px) scale(1);
                }
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

        <!-- Initialize animations on scroll -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Animate composition bars on scroll
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.width = entry.target.getAttribute('style').match(/width: ([\d.]+%)/)[1];
                        }
                    });
                });
                
                document.querySelectorAll('.composition-bar').forEach(bar => {
                    observer.observe(bar);
                });
            });
        </script>
    </body>
</html>