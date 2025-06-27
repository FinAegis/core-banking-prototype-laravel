<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Support Center - Get help with our open source financial platform. Documentation, guides, and community support.">
        <meta name="keywords" content="FinAegis support, help center, documentation, guides, FAQ">
        
        <title>Support Center - FinAegis</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            }
            .support-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            .support-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.1);
                border-color: #4f46e5;
            }
            .status-badge {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: .7;
                }
            }
        </style>
    </head>
    <body class="antialiased bg-gray-50">
        <x-alpha-banner />
        
        <!-- Spacer for fixed banner -->
        <div class="h-12"></div>
        
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 pb-20 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Support Center
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        Get help with FinAegis. From documentation to community support, we're here to assist.
                    </p>
                    <div class="inline-flex items-center px-4 py-2 bg-white/20 rounded-full backdrop-blur-sm">
                        <div class="w-3 h-3 bg-green-400 rounded-full status-badge mr-3"></div>
                        <span class="text-sm font-medium">Alpha Support Available</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Help Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">How Can We Help?</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Choose from our support options below or explore our documentation
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                    <!-- Documentation -->
                    <a href="{{ route('support.guides') }}" class="support-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Documentation & Guides</h3>
                        <p class="text-gray-600 mb-4">
                            Step-by-step guides and comprehensive documentation for the platform
                        </p>
                        <span class="text-indigo-600 font-semibold">Browse Guides →</span>
                    </a>

                    <!-- FAQ -->
                    <a href="{{ route('support.faq') }}" class="support-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Frequently Asked Questions</h3>
                        <p class="text-gray-600 mb-4">
                            Find answers to common questions about FinAegis and GCU
                        </p>
                        <span class="text-purple-600 font-semibold">View FAQ →</span>
                    </a>

                    <!-- Contact -->
                    <a href="{{ route('support.contact') }}" class="support-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Contact Support</h3>
                        <p class="text-gray-600 mb-4">
                            Get in touch with our support team for alpha testing feedback
                        </p>
                        <span class="text-green-600 font-semibold">Contact Us →</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Community Support -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-6">Community Support</h2>
                        <p class="text-lg text-gray-600 mb-8">
                            Join our open source community for help, discussions, and contributions. As an alpha platform, community feedback is invaluable.
                        </p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-indigo-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">GitHub Discussions</h4>
                                    <p class="text-gray-600">Ask questions and share ideas with the community</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-indigo-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Issue Tracker</h4>
                                    <p class="text-gray-600">Report bugs and request features on GitHub</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-indigo-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Code Contributions</h4>
                                    <p class="text-gray-600">Help improve the platform by contributing code</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8">
                            <a href="https://github.com/FinAegis/core-banking-prototype-laravel/discussions" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                Join the Community
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl p-8">
                        <div class="text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-4">Open Source Project</h3>
                            <p class="text-gray-600 mb-6">
                                FinAegis is open source. View our code, report issues, and contribute on GitHub.
                            </p>
                            <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="bg-gray-900 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-800 transition inline-block">
                                View on GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Alpha Notice -->
        <section class="py-16 bg-amber-50 border-y border-amber-200">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-amber-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-2xl font-bold text-amber-900">Alpha Testing Phase</h3>
                </div>
                <p class="text-lg text-amber-800 max-w-3xl mx-auto">
                    FinAegis is currently in alpha testing. Features are being actively developed and may change. Your feedback helps us build a better platform.
                </p>
            </div>
        </section>

        <!-- Developer Resources -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Developer Resources</h2>
                    <p class="text-xl text-gray-600">Build on FinAegis with our comprehensive documentation</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                    <a href="{{ route('developers') }}" class="bg-gray-50 rounded-lg p-6 text-center hover:bg-gray-100 transition">
                        <svg class="w-8 h-8 text-indigo-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                        <h3 class="font-semibold text-gray-900">API Docs</h3>
                        <p class="text-sm text-gray-600 mt-1">REST API reference</p>
                    </a>
                    
                    <a href="{{ route('developers.show', 'examples') }}" class="bg-gray-50 rounded-lg p-6 text-center hover:bg-gray-100 transition">
                        <svg class="w-8 h-8 text-purple-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="font-semibold text-gray-900">Examples</h3>
                        <p class="text-sm text-gray-600 mt-1">Code samples</p>
                    </a>
                    
                    <a href="{{ route('developers.show', 'postman') }}" class="bg-gray-50 rounded-lg p-6 text-center hover:bg-gray-100 transition">
                        <svg class="w-8 h-8 text-orange-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        <h3 class="font-semibold text-gray-900">Postman</h3>
                        <p class="text-sm text-gray-600 mt-1">Collection download</p>
                    </a>
                    
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="bg-gray-50 rounded-lg p-6 text-center hover:bg-gray-100 transition">
                        <svg class="w-8 h-8 text-gray-700 mx-auto mb-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                        <h3 class="font-semibold text-gray-900">GitHub</h3>
                        <p class="text-sm text-gray-600 mt-1">Source code</p>
                    </a>
                </div>
            </div>
        </section>

        <!-- Contact CTA -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Still Need Help?</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Our team is here to support you during the alpha testing phase
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('support.contact') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        Contact Support
                    </a>
                    <a href="{{ route('support.faq') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/20 transition inline-block">
                        Browse FAQ
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Platform</h4>
                        <ul class="space-y-2">
                            <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                            <li><a href="/gcu" class="hover:text-white transition">GCU</a></li>
                            <li><a href="/sub-products" class="hover:text-white transition">Modules</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Developers</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                            <li><a href="/developers/api-docs" class="hover:text-white transition">API Reference</a></li>
                            <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
                            <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Resources</h4>
                        <ul class="space-y-2">
                            <li><a href="/support" class="hover:text-white transition">Support</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                            <li><a href="/about" class="hover:text-white transition">About</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2">
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                            <li><a href="/legal/cookies" class="hover:text-white transition">Cookies</a></li>
                            <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Open Source Project.</p>
                </div>
            </div>
        </footer>
    </body>
</html>