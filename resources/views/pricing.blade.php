<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Pricing - Open source community edition or enterprise support. Start free, scale when ready.">
        <meta name="keywords" content="FinAegis, pricing, open source, enterprise, support">
        
        <title>Pricing - FinAegis</title>

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
                        Open Source & Enterprise Ready
                    </h1>
                    <p class="text-xl md:text-2xl text-purple-100 max-w-3xl mx-auto mb-8">
                        Start with our community edition, scale with enterprise support when you're ready.
                    </p>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-gray-50" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Pricing Options -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Community Edition -->
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border-2 border-gray-200">
                        <div class="p-8">
                            <div class="text-center">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Community Edition</h3>
                                <p class="text-gray-600 mb-6">Perfect for developers and small teams</p>
                                <div class="mb-8">
                                    <span class="text-5xl font-bold text-gray-900">Free</span>
                                    <span class="text-xl text-gray-600">Open Source</span>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-8">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Full source code access</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">MIT License</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">All core features</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Community support</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Self-hosted deployment</span>
                                </li>
                            </ul>

                            <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="w-full bg-gray-900 text-white rounded-lg py-3 font-semibold hover:bg-gray-800 transition text-center block">
                                Get Started on GitHub
                            </a>
                        </div>
                    </div>

                    <!-- Cloud Platform -->
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border-2 border-indigo-600 relative">
                        <div class="absolute top-0 right-0 bg-indigo-600 text-white px-4 py-2 rounded-bl-lg text-sm font-semibold">
                            Most Popular
                        </div>
                        <div class="p-8">
                            <div class="text-center">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Cloud Platform</h3>
                                <p class="text-gray-600 mb-6">Managed infrastructure for growing businesses</p>
                                <div class="mb-8">
                                    <span class="text-5xl font-bold text-gray-900">TBC</span>
                                    <span class="text-xl text-gray-600">Pricing coming soon</span>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-8">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Everything in Community</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Managed hosting</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">99.9% uptime SLA</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Priority support</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Automatic updates</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Daily backups</span>
                                </li>
                            </ul>

                            <a href="{{ route('support.contact') }}" class="w-full bg-indigo-600 text-white rounded-lg py-3 font-semibold hover:bg-indigo-700 transition text-center block">
                                Contact Sales
                            </a>
                        </div>
                    </div>

                    <!-- Enterprise -->
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border-2 border-gray-200">
                        <div class="p-8">
                            <div class="text-center">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Enterprise</h3>
                                <p class="text-gray-600 mb-6">Custom solutions for large organizations</p>
                                <div class="mb-8">
                                    <span class="text-5xl font-bold text-gray-900">TBC</span>
                                    <span class="text-xl text-gray-600">Contact us</span>
                                </div>
                            </div>

                            <ul class="space-y-4 mb-8">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Everything in Cloud</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">On-premise deployment</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Custom integrations</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Dedicated support team</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Service level agreements</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-gray-700">Compliance assistance</span>
                                </li>
                            </ul>

                            <a href="{{ route('support.contact') }}" class="w-full bg-gray-900 text-white rounded-lg py-3 font-semibold hover:bg-gray-800 transition text-center block">
                                Contact Enterprise Sales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-20 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
                    <p class="text-xl text-gray-600">Everything you need to know about our pricing</p>
                </div>

                <div class="space-y-6">
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Is the community edition really free?</h3>
                        <p class="text-gray-600">Yes! Our community edition is completely free and open source under the MIT license. You can use it for any purpose, including commercial projects.</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">What's included in the Cloud Platform?</h3>
                        <p class="text-gray-600">The Cloud Platform includes managed hosting, automatic updates, daily backups, 99.9% uptime SLA, and priority support. We handle all the infrastructure so you can focus on your business.</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I switch between plans?</h3>
                        <p class="text-gray-600">Absolutely! You can start with the community edition and upgrade to Cloud or Enterprise plans at any time. We'll help you migrate your data seamlessly.</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Do you offer discounts for non-profits?</h3>
                        <p class="text-gray-600">Yes, we offer special pricing for non-profit organizations and educational institutions. Contact our sales team for more information.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">Ready to Get Started?</h2>
                <p class="text-xl text-gray-600 mb-8">
                    Join thousands of developers building the future of finance
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://github.com/FinAegis" target="_blank" class="bg-gray-900 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-800 transition shadow-lg hover:shadow-xl">
                        Start with Community Edition
                    </a>
                    <a href="{{ route('support.contact') }}" class="border-2 border-gray-900 text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-50 transition">
                        Talk to Sales
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')
    </body>
</html>