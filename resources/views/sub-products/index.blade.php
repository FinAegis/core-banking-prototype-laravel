<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Sub-Products - Optional financial services including Exchange, Lending, Stablecoins, and Treasury. Enable only what you need.">
        <meta name="keywords" content="FinAegis sub-products, crypto exchange, P2P lending, stablecoins, treasury management, optional services">
        
        <!-- Open Graph -->
        <meta property="og:title" content="FinAegis Sub-Products - Modular Financial Services">
        <meta property="og:description" content="Extend your GCU account with professional trading, lending, stablecoins, and treasury management. Enable only what you need.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/sub-products') }}">

        <title>Sub-Products - Optional Services | FinAegis</title>

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
            .product-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
            }
            .product-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .product-card.exchange:hover {
                border-color: #8b5cf6;
            }
            .product-card.lending:hover {
                border-color: #10b981;
            }
            .product-card.stablecoins:hover {
                border-color: #f59e0b;
            }
            .product-card.treasury:hover {
                border-color: #3b82f6;
            }
            .coming-soon-badge {
                position: absolute;
                top: 20px;
                right: -30px;
                background: #ef4444;
                color: white;
                padding: 5px 40px;
                transform: rotate(45deg);
                font-size: 12px;
                font-weight: bold;
            }
            .feature-icon {
                transition: transform 0.3s ease;
            }
            .product-card:hover .feature-icon {
                transform: scale(1.1);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-alpha-banner />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Optional Sub-Products
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        Extend your GCU account with professional financial services. Enable only what you need, when you need it.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('dashboard') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Manage Sub-Products
                        </a>
                        <a href="#products" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                            Explore Services
                        </a>
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

        <!-- Products Overview Section -->
        <section id="products" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Professional Financial Services</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Each sub-product integrates seamlessly with your GCU account. Enable services through your dashboard with a single click.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Exchange -->
                    <div class="product-card exchange bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-semibold">Available</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">FinAegis Exchange</h3>
                        <p class="text-gray-600 mb-6">
                            Professional trading platform for crypto and fiat currencies. Institutional-grade security with advanced trading features.
                        </p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Spot & derivatives trading</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>50+ trading pairs</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Institutional custody</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>API trading support</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'exchange') }}" class="text-purple-600 font-semibold hover:text-purple-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">From €9.99/month</span>
                        </div>
                    </div>

                    <!-- Lending -->
                    <div class="product-card lending bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">Available</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">FinAegis Lending</h3>
                        <p class="text-gray-600 mb-6">
                            P2P lending marketplace connecting capital with opportunity. Earn yield or access working capital.
                        </p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>SME business loans</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Invoice financing</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Automated credit scoring</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Up to 12% APY</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'lending') }}" class="text-green-600 font-semibold hover:text-green-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">0.5% origination fee</span>
                        </div>
                    </div>

                    <!-- Stablecoins -->
                    <div class="product-card stablecoins bg-gray-50 rounded-2xl p-8 shadow-lg relative">
                        <span class="coming-soon-badge">COMING SOON</span>
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-full text-sm font-semibold">Q2 2025</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">FinAegis Stablecoins</h3>
                        <p class="text-gray-600 mb-6">
                            Issue and manage stable tokens backed by real assets. Multi-chain support with instant redemption.
                        </p>
                        
                        <div class="space-y-3 mb-6 opacity-75">
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>EUR, USD, GBP stables</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Full reserve backing</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Cross-chain bridges</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>MiCA compliant</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'stablecoins') }}" class="text-yellow-600 font-semibold hover:text-yellow-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Pricing TBD</span>
                        </div>
                    </div>

                    <!-- Treasury -->
                    <div class="product-card treasury bg-gray-50 rounded-2xl p-8 shadow-lg relative">
                        <span class="coming-soon-badge">COMING SOON</span>
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-full text-sm font-semibold">Q3 2025</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">FinAegis Treasury</h3>
                        <p class="text-gray-600 mb-6">
                            Advanced cash management across multiple banks. Optimize yields and minimize risk automatically.
                        </p>
                        
                        <div class="space-y-3 mb-6 opacity-75">
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Multi-bank allocation</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Automated rebalancing</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Risk optimization</span>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Corporate controls</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'treasury') }}" class="text-blue-600 font-semibold hover:text-blue-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Enterprise pricing</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Simple Activation Process</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Enable sub-products instantly from your dashboard. No complex setup or migration required.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">1</div>
                        <h3 class="font-semibold mb-2">Choose Service</h3>
                        <p class="text-gray-600 text-sm">Select the sub-product you want to enable</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">2</div>
                        <h3 class="font-semibold mb-2">Review Terms</h3>
                        <p class="text-gray-600 text-sm">Check pricing and accept service terms</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">3</div>
                        <h3 class="font-semibold mb-2">One-Click Enable</h3>
                        <p class="text-gray-600 text-sm">Activate with a single click</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">4</div>
                        <h3 class="font-semibold mb-2">Start Using</h3>
                        <p class="text-gray-600 text-sm">Access immediately from your dashboard</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Integration Benefits Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-4xl font-bold text-gray-900 mb-6">Seamless Integration Benefits</h2>
                        <p class="text-lg text-gray-600 mb-8">
                            All sub-products are designed to work perfectly with your GCU account and each other.
                        </p>
                        
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Instant Transfers</h3>
                                    <p class="text-gray-600">Move funds between GCU and sub-products instantly with no fees.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Unified Dashboard</h3>
                                    <p class="text-gray-600">Manage everything from one place. No separate logins or accounts.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Single Compliance</h3>
                                    <p class="text-gray-600">Complete KYC once. All sub-products share your verification status.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-2xl p-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">Integration Examples</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">Exchange + GCU</h4>
                                <p class="text-gray-600 text-sm">Trade directly from your GCU balance. Profits settle back to GCU automatically.</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">Lending + Treasury</h4>
                                <p class="text-gray-600 text-sm">Excess treasury funds automatically allocated to lending for yield optimization.</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">Stablecoins + Exchange</h4>
                                <p class="text-gray-600 text-sm">Mint stablecoins from GCU balance. Trade stable pairs with zero slippage.</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">All Products</h4>
                                <p class="text-gray-600 text-sm">Complete financial ecosystem. One account, unlimited possibilities.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Overview Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Transparent Pricing</h2>
                    <p class="text-xl text-gray-600">Pay only for what you use. No hidden fees.</p>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden max-w-4xl mx-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Service</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Monthly Fee</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Transaction Fees</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                            <span class="text-indigo-600 font-bold">Ǥ</span>
                                        </div>
                                        <span class="font-medium">GCU Account</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-green-600 font-semibold">Free</span>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600">0.5% conversion</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">Active</span>
                                </td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                            </svg>
                                        </div>
                                        <span class="font-medium">Exchange</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">€9.99</td>
                                <td class="px-6 py-4 text-center text-gray-600">0.1% - 0.25%</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">Available</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                        <span class="font-medium">Lending</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-green-600 font-semibold">Free</span>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600">0.5% origination</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">Available</span>
                                </td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <span class="font-medium">Stablecoins</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-500">TBD</td>
                                <td class="px-6 py-4 text-center text-gray-500">TBD</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm">Coming Q2</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <span class="font-medium">Treasury</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-500">Custom</td>
                                <td class="px-6 py-4 text-center text-gray-500">Volume-based</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm">Coming Q3</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-8">
                    <a href="{{ route('pricing') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                        View detailed pricing →
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Start with GCU, Grow with Sub-Products</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Open your free GCU account today and enable sub-products as your needs evolve
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        Get Started Free
                    </a>
                    <a href="{{ route('platform') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-indigo-600 transition inline-block">
                        Platform Overview
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Sub-Products</h4>
                        <ul class="space-y-2">
                            <li><a href="/sub-products/exchange" class="hover:text-white transition">Exchange</a></li>
                            <li><a href="/sub-products/lending" class="hover:text-white transition">Lending</a></li>
                            <li><a href="/sub-products/stablecoins" class="hover:text-white transition">Stablecoins</a></li>
                            <li><a href="/sub-products/treasury" class="hover:text-white transition">Treasury</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Platform</h4>
                        <ul class="space-y-2">
                            <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                            <li><a href="/gcu" class="hover:text-white transition">GCU</a></li>
                            <li><a href="/features" class="hover:text-white transition">Features</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Resources</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Developers</a></li>
                            <li><a href="/support" class="hover:text-white transition">Support</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/status" class="hover:text-white transition">Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2">
                            <li><a href="/about" class="hover:text-white transition">About</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </body>
</html>