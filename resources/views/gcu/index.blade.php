<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Global Currency Unit (GCU) - The world's first democratically governed basket currency. Real bank backing, government insurance, community voting.">
        <meta name="keywords" content="GCU, global currency unit, democratic banking, basket currency, multi-currency, stable currency, FinAegis">
        
        <!-- Open Graph -->
        <meta property="og:title" content="Global Currency Unit (GCU) - Democratic Banking Redefined">
        <meta property="og:description" content="Experience banking where your money stays in real banks with government insurance while you control the currency composition through voting.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/gcu') }}">

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
            .gcu-symbol {
                font-size: 8rem;
                line-height: 1;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .benefit-card {
                transition: all 0.3s ease;
            }
            .benefit-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .currency-bar {
                transition: width 1s ease-out;
            }
            .voting-card {
                background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                border: 2px solid #e5e7eb;
                transition: all 0.3s ease;
            }
            .voting-card:hover {
                border-color: #667eea;
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h1 class="text-5xl md:text-6xl font-bold mb-6">
                            Global Currency Unit
                        </h1>
                        <p class="text-xl md:text-2xl mb-8 text-purple-100">
                            The world's first democratically governed basket currency. Your money stays in real banks with government insurance while you vote on the currency composition.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                                Open GCU Account
                            </a>
                            <a href="#how-it-works" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                                How It Works
                            </a>
                        </div>
                        
                        <!-- Key Stats -->
                        <div class="grid grid-cols-3 gap-6 mt-12">
                            <div>
                                <div class="text-3xl font-bold">6+</div>
                                <div class="text-purple-200">Currencies</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold">100%</div>
                                <div class="text-purple-200">Bank Backed</div>
                            </div>
                            <div>
                                <div class="text-3xl font-bold">Monthly</div>
                                <div class="text-purple-200">Voting</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center lg:justify-end">
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 text-center">
                            <div class="gcu-symbol mb-6">Ǥ</div>
                            <h3 class="text-2xl font-semibold mb-2">The GCU Symbol</h3>
                            <p class="text-purple-100">Representing global unity and democratic finance</p>
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

        <!-- Current Composition Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Current GCU Composition</h2>
                    <p class="text-xl text-gray-600">Voted on by the community, optimized for stability</p>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-8 shadow-lg max-w-4xl mx-auto">
                    <div class="space-y-6">
                        <!-- USD -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇺🇸</span>
                                    <span class="font-semibold text-gray-900">US Dollar (USD)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">35%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-blue-600 h-3 rounded-full" style="width: 35%"></div>
                            </div>
                        </div>
                        
                        <!-- EUR -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇪🇺</span>
                                    <span class="font-semibold text-gray-900">Euro (EUR)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">30%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-indigo-600 h-3 rounded-full" style="width: 30%"></div>
                            </div>
                        </div>
                        
                        <!-- GBP -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇬🇧</span>
                                    <span class="font-semibold text-gray-900">British Pound (GBP)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">20%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-purple-600 h-3 rounded-full" style="width: 20%"></div>
                            </div>
                        </div>
                        
                        <!-- CHF -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇨🇭</span>
                                    <span class="font-semibold text-gray-900">Swiss Franc (CHF)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">10%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-red-600 h-3 rounded-full" style="width: 10%"></div>
                            </div>
                        </div>
                        
                        <!-- JPY -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇯🇵</span>
                                    <span class="font-semibold text-gray-900">Japanese Yen (JPY)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">3%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-pink-600 h-3 rounded-full" style="width: 3%"></div>
                            </div>
                        </div>
                        
                        <!-- XAU -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🏆</span>
                                    <span class="font-semibold text-gray-900">Gold (XAU)</span>
                                </div>
                                <span class="text-2xl font-bold text-gray-900">2%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="currency-bar bg-yellow-600 h-3 rounded-full" style="width: 2%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <p class="text-center text-gray-600">
                            Next voting round begins in <span class="font-semibold text-indigo-600">12 days</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">How GCU Works</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        A revolutionary approach to currency that combines stability, democracy, and real banking infrastructure
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">1</div>
                        <h3 class="text-xl font-semibold mb-3">Deposit Your Funds</h3>
                        <p class="text-gray-600">
                            Deposit EUR, USD, or other supported currencies. Your money is instantly converted to GCU at the current rate.
                        </p>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">2</div>
                        <h3 class="text-xl font-semibold mb-3">Money Stays in Real Banks</h3>
                        <p class="text-gray-600">
                            Your funds are held in partner banks across multiple countries, protected by government deposit insurance.
                        </p>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">3</div>
                        <h3 class="text-xl font-semibold mb-3">Vote on Composition</h3>
                        <p class="text-gray-600">
                            Every month, GCU holders vote on the optimal currency basket composition based on market conditions.
                        </p>
                    </div>
                </div>
                
                <!-- Detailed Process -->
                <div class="mt-16 bg-white rounded-2xl shadow-xl p-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">The Democratic Process</h3>
                    
                    <div class="grid lg:grid-cols-2 gap-8">
                        <div>
                            <h4 class="text-xl font-semibold mb-4 flex items-center">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                Monthly Proposals
                            </h4>
                            <p class="text-gray-600 mb-4">
                                Community members and economic advisors submit proposals for the optimal currency basket based on global economic conditions.
                            </p>
                            
                            <h4 class="text-xl font-semibold mb-4 flex items-center">
                                <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                </svg>
                                Community Discussion
                            </h4>
                            <p class="text-gray-600">
                                Proposals are discussed in the community forum. Economic data, risk assessments, and market analysis inform the debate.
                            </p>
                        </div>
                        
                        <div>
                            <h4 class="text-xl font-semibold mb-4 flex items-center">
                                <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Weighted Voting
                            </h4>
                            <p class="text-gray-600 mb-4">
                                GCU holders vote on proposals. Voting power is proportional to GCU holdings, ensuring aligned incentives.
                            </p>
                            
                            <h4 class="text-xl font-semibold mb-4 flex items-center">
                                <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Automatic Rebalancing
                            </h4>
                            <p class="text-gray-600">
                                Once voting concludes, the platform automatically rebalances the currency basket across all partner banks.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose GCU?</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Combining the best of traditional banking with innovative governance
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Stability -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Stability Through Diversification</h3>
                        <p class="text-gray-600">
                            By holding multiple currencies, GCU reduces volatility and protects against single-currency risks.
                        </p>
                    </div>
                    
                    <!-- Security -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Government-Backed Security</h3>
                        <p class="text-gray-600">
                            Your funds are held in regulated banks with deposit insurance, providing institutional-grade security.
                        </p>
                    </div>
                    
                    <!-- Democracy -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Democratic Control</h3>
                        <p class="text-gray-600">
                            Every GCU holder has a voice. Vote monthly on currency composition based on global economic conditions.
                        </p>
                    </div>
                    
                    <!-- Global -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Global Acceptance</h3>
                        <p class="text-gray-600">
                            Use GCU worldwide. Automatic conversion to local currencies with optimized exchange rates.
                        </p>
                    </div>
                    
                    <!-- Transparency -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Full Transparency</h3>
                        <p class="text-gray-600">
                            Real-time reporting of reserves, bank allocations, and voting results. Complete audit trails for all operations.
                        </p>
                    </div>
                    
                    <!-- Innovation -->
                    <div class="benefit-card bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Future-Ready</h3>
                        <p class="text-gray-600">
                            Built on modern infrastructure with APIs for developers. Ready for the next generation of financial services.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banking Partners Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Trusted Banking Partners</h2>
                    <p class="text-xl text-gray-600">Your funds are secure with regulated European banks</p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">Paysera</h3>
                        <p class="text-gray-600 mt-2">Lithuania</p>
                    </div>
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">Deutsche Bank</h3>
                        <p class="text-gray-600 mt-2">Germany</p>
                    </div>
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">Santander</h3>
                        <p class="text-gray-600 mt-2">Spain</p>
                    </div>
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">BNP Paribas</h3>
                        <p class="text-gray-600 mt-2">France</p>
                    </div>
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">ING</h3>
                        <p class="text-gray-600 mt-2">Netherlands</p>
                    </div>
                    <div class="bg-white rounded-lg p-8 shadow-md text-center">
                        <h3 class="text-xl font-semibold text-gray-900">UniCredit</h3>
                        <p class="text-gray-600 mt-2">Italy</p>
                    </div>
                </div>
                
                <div class="text-center mt-12">
                    <p class="text-gray-600">
                        All partner banks are regulated by their respective national authorities and provide deposit insurance up to €100,000
                    </p>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-20 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
                    <p class="text-xl text-gray-600">Everything you need to know about GCU</p>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">What exactly is GCU?</h3>
                        <p class="text-gray-600">
                            GCU (Global Currency Unit) is a basket currency whose value is derived from a weighted combination of major world currencies. Unlike traditional currencies controlled by governments, GCU's composition is determined by democratic voting among its holders.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Is my money safe?</h3>
                        <p class="text-gray-600">
                            Yes. Your funds are held in regulated European banks with government deposit insurance. FinAegis never holds your money directly - it's always in real banks with the same protections as traditional bank accounts.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">How does voting work?</h3>
                        <p class="text-gray-600">
                            Every month, GCU holders can vote on proposals for the currency basket composition. Your voting power is proportional to your GCU holdings. The winning proposal is automatically implemented through our banking network.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Can I convert GCU back to regular currency?</h3>
                        <p class="text-gray-600">
                            Yes, you can convert your GCU to any supported currency at any time. The conversion happens at the current market rate with transparent fees. Withdrawals can be made to any bank account.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">What are the fees?</h3>
                        <p class="text-gray-600">
                            GCU accounts are free to open and maintain. We charge a small conversion fee (0.5%) when exchanging between GCU and other currencies. There are no hidden fees or minimum balance requirements.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Who can open a GCU account?</h3>
                        <p class="text-gray-600">
                            Anyone over 18 with valid identification can open a GCU account. We support individuals and businesses from most countries. The onboarding process includes standard KYC verification.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Ready to Join the Future of Money?</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Open your GCU account in minutes and start participating in democratic banking
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        Open Free Account
                    </a>
                    <a href="{{ route('support.contact') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-indigo-600 transition inline-block">
                        Contact Sales
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Product</h4>
                        <ul class="space-y-2">
                            <li><a href="/features" class="hover:text-white transition">Features</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                            <li><a href="/security" class="hover:text-white transition">Security</a></li>
                            <li><a href="/compliance" class="hover:text-white transition">Compliance</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Platform</h4>
                        <ul class="space-y-2">
                            <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                            <li><a href="/sub-products/exchange" class="hover:text-white transition">Exchange</a></li>
                            <li><a href="/sub-products/lending" class="hover:text-white transition">Lending</a></li>
                            <li><a href="/sub-products/stablecoins" class="hover:text-white transition">Stablecoins</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Resources</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                            <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2">
                            <li><a href="/about" class="hover:text-white transition">About Us</a></li>
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