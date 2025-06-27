<x-guest-layout>
    <x-main-navigation />

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-800 text-white">
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        </div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    The FinAegis Platform
                </h1>
                <p class="text-xl md:text-2xl text-indigo-100 max-w-3xl mx-auto mb-8">
                    Build your financial future with our modular banking infrastructure. Start with GCU, scale with purpose.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-4 bg-white text-indigo-900 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                        Start Building
                    </a>
                    <a href="#architecture" class="inline-flex items-center px-8 py-4 bg-indigo-800 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all">
                        Explore Architecture
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Platform Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Enterprise-Grade Infrastructure</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Built on modern architecture with security, compliance, and scalability at its core
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="group hover:transform hover:-translate-y-2 transition-all duration-300">
                    <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl p-6 h-full">
                        <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-indigo-200 transition-colors">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Bank-Grade Security</h3>
                        <p class="text-gray-600">Quantum-resistant encryption, multi-factor auth, and continuous monitoring</p>
                    </div>
                </div>

                <div class="group hover:transform hover:-translate-y-2 transition-all duration-300">
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl p-6 h-full">
                        <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-purple-200 transition-colors">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Multi-Currency Rails</h3>
                        <p class="text-gray-600">Support for {{ config('platform.statistics.supported_currencies') }} currencies with instant conversion</p>
                    </div>
                </div>

                <div class="group hover:transform hover:-translate-y-2 transition-all duration-300">
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-6 h-full">
                        <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-green-200 transition-colors">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Partner Banks</h3>
                        <p class="text-gray-600">{{ config('platform.statistics.banking_partners') }} integrated banks with deposit insurance</p>
                    </div>
                </div>

                <div class="group hover:transform hover:-translate-y-2 transition-all duration-300">
                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-2xl p-6 h-full">
                        <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-yellow-200 transition-colors">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Full Compliance</h3>
                        <p class="text-gray-600">PSD2, MiCA ready, GDPR compliant infrastructure</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture Diagram -->
    <section id="architecture" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Modular Architecture</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Start with what you need, expand when you're ready
                </p>
            </div>

            <!-- Visual Architecture -->
            <div class="relative">
                <!-- Core Platform -->
                <div class="bg-white rounded-3xl shadow-xl p-8 mb-12 border-2 border-indigo-200">
                    <h3 class="text-2xl font-bold text-center text-gray-900 mb-8">Core Banking Platform</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-3">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-800">Identity</h4>
                        </div>
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-3">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-800">Payments</h4>
                        </div>
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-3">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-800">Security</h4>
                        </div>
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-3">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-800">Compliance</h4>
                        </div>
                    </div>
                </div>

                <!-- Modules -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- GCU Module (Featured) -->
                    <div class="lg:col-span-3">
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-1">
                            <div class="bg-white rounded-3xl p-8">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center">
                                        <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white text-2xl font-bold mr-4">
                                            Ǥ
                                        </div>
                                        <div>
                                            <h3 class="text-2xl font-bold text-gray-900">Global Currency Unit (GCU)</h3>
                                            <p class="text-gray-600">Core Product - Available Now</p>
                                        </div>
                                    </div>
                                    <span class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-semibold">Active</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-2">Multi-Currency Basket</h4>
                                        <p class="text-gray-600 text-sm">{{ config('platform.statistics.supported_currencies') }} currencies with democratic governance</p>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-2">Bank Backing</h4>
                                        <p class="text-gray-600 text-sm">Real bank deposits with government insurance</p>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-2">Instant Transfers</h4>
                                        <p class="text-gray-600 text-sm">Send and receive globally in seconds</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="{{ route('gcu') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                        Learn more about GCU
                                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Modules -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Coming {{ config('platform.sub_products.exchange.launch_date') }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Exchange</h3>
                        <p class="text-gray-600 text-sm mb-4">Professional trading platform for digital and traditional assets</p>
                        <a href="{{ route('sub-products.show', 'exchange') }}" class="text-purple-600 font-medium text-sm hover:text-purple-700">Learn more →</a>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Coming {{ config('platform.sub_products.lending.launch_date') }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Lending</h3>
                        <p class="text-gray-600 text-sm mb-4">P2P lending marketplace for businesses and investors</p>
                        <a href="{{ route('sub-products.show', 'lending') }}" class="text-green-600 font-medium text-sm hover:text-green-700">Learn more →</a>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Coming {{ config('platform.sub_products.stablecoins.launch_date') }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Stablecoins</h3>
                        <p class="text-gray-600 text-sm mb-4">Issue and manage stable digital currencies</p>
                        <a href="{{ route('sub-products.show', 'stablecoins') }}" class="text-yellow-600 font-medium text-sm hover:text-yellow-700">Learn more →</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Platform Stats -->
    <section class="py-20 bg-indigo-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-4xl md:text-5xl font-bold mb-2">{{ config('platform.statistics.supported_currencies') }}</div>
                    <p class="text-indigo-200">Currencies</p>
                </div>
                <div>
                    <div class="text-4xl md:text-5xl font-bold mb-2">{{ config('platform.statistics.banking_partners') }}</div>
                    <p class="text-indigo-200">Partner Banks</p>
                </div>
                <div>
                    <div class="text-4xl md:text-5xl font-bold mb-2">{{ config('platform.statistics.api_endpoints') }}</div>
                    <p class="text-indigo-200">API Endpoints</p>
                </div>
                <div>
                    <div class="text-4xl md:text-5xl font-bold mb-2">{{ config('platform.statistics.uptime_sla') }}</div>
                    <p class="text-indigo-200">Target Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">Ready to Build?</h2>
            <p class="text-xl text-gray-600 mb-8">
                Join our alpha testing program and help shape the future of finance
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-4 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all transform hover:scale-105">
                    Create Alpha Account
                </a>
                <a href="{{ route('developers') }}" class="inline-flex items-center px-8 py-4 bg-white text-indigo-600 rounded-lg font-semibold border-2 border-indigo-600 hover:bg-indigo-50 transition-all">
                    View Documentation
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')
</x-guest-layout>