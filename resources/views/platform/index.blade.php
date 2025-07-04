<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Platform - Open-source banking infrastructure for developers. Build, deploy, and scale financial services with our MIT-licensed platform.">
        <meta name="keywords" content="FinAegis, platform, banking infrastructure, open source, developer, API, MIT license">
        
        <title>FinAegis Platform - Open Banking for Developers</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .terminal-gradient {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            }
            .code-font {
                font-family: 'Fira Code', monospace;
            }
            @keyframes typing {
                from { width: 0 }
                to { width: 100% }
            }
            @keyframes blink {
                0%, 49% { opacity: 1 }
                50%, 100% { opacity: 0 }
            }
            .typing-animation {
                overflow: hidden;
                white-space: nowrap;
                animation: typing 3s steps(40, end);
            }
            .cursor {
                animation: blink 1s infinite;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px) }
                50% { transform: translateY(-20px) }
            }
            .float-animation {
                animation: float 6s ease-in-out infinite;
            }
            .matrix-bg {
                background-image: 
                    repeating-linear-gradient(
                        0deg,
                        transparent,
                        transparent 2px,
                        rgba(0, 255, 0, 0.03) 2px,
                        rgba(0, 255, 0, 0.03) 4px
                    );
            }
            .hover-lift {
                transition: all 0.3s ease;
            }
            .hover-lift:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section - Terminal Style -->
        <section class="pt-16 terminal-gradient text-white relative overflow-hidden">
            <!-- Matrix effect background -->
            <div class="absolute inset-0 matrix-bg opacity-20"></div>
            
            <!-- Floating code snippets -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="code-font text-green-400 text-sm opacity-20 absolute top-20 left-10 transform rotate-12 hidden lg:block">
                    const bank = new FinAegis();
                </div>
                <div class="code-font text-blue-400 text-sm opacity-20 absolute top-40 right-20 transform -rotate-12 hidden lg:block">
                    $ npm install @finaegis/sdk
                </div>
                <div class="code-font text-purple-400 text-sm opacity-20 absolute bottom-40 left-1/4 hidden lg:block">
                    pip install finaegis==latest
                </div>
                <div class="code-font text-yellow-400 text-sm opacity-20 absolute bottom-20 right-1/3 transform rotate-6 hidden lg:block">
                    composer require finaegis/sdk
                </div>
            </div>
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Left side - Terminal -->
                    <div>
                        <!-- Terminal window -->
                        <div class="bg-black rounded-lg shadow-2xl overflow-hidden">
                            <div class="bg-gray-800 px-4 py-2 flex items-center">
                                <div class="flex space-x-2">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div class="ml-4 text-gray-400 text-sm code-font">finaegis@terminal</div>
                            </div>
                            <div class="p-6 code-font text-sm">
                                <div class="text-gray-400 mb-2">$ whoami</div>
                                <div class="text-green-400 mb-4">developer@finaegis</div>
                                
                                <div class="text-gray-400 mb-2">$ git clone https://github.com/FinAegis/core-banking-prototype-laravel</div>
                                <div class="text-white mb-2">Cloning into 'core-banking-prototype-laravel'...</div>
                                <div class="text-green-400 mb-4">✓ Ready to build</div>
                                
                                <div class="text-gray-400 mb-2">$ cd core-banking-prototype-laravel</div>
                                <div class="text-gray-400 mb-2">$ composer install</div>
                                <div class="text-white mb-2">Installing dependencies...</div>
                                <div class="text-gray-400 mb-2">$ php artisan serve</div>
                                <div class="text-blue-400 mb-2">Starting Laravel development server...</div>
                                <div class="text-green-400">🚀 Platform running on http://127.0.0.1:8000</div>
                                
                                <div class="mt-4">
                                    <span class="text-gray-400">$</span>
                                    <span class="cursor text-green-400">_</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right side - Content -->
                    <div>
                        <div class="mb-6">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-500/20 text-green-400 border border-green-500/30">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                100% Open Source
                            </span>
                        </div>
                        
                        <h1 class="text-5xl md:text-6xl font-bold mb-6">
                            <span class="bg-clip-text text-transparent bg-gradient-to-r from-green-400 via-blue-400 to-purple-400">
                                Build Banking APIs
                            </span>
                            <br />
                            <span class="text-3xl text-gray-300">
                                That Actually Scale
                            </span>
                        </h1>
                        
                        <p class="text-xl text-gray-400 mb-8">
                            Open-source banking infrastructure with everything you need. 
                            No vendor lock-in. No licensing fees. Just pure developer freedom.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="group inline-flex items-center justify-center px-6 py-3 bg-black border-2 border-green-500 text-green-400 rounded-lg font-semibold hover:bg-green-500 hover:text-black transition-all">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                Star on GitHub
                            </a>
                            <a href="{{ route('developers') }}" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-all">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                                View Documentation
                            </a>
                        </div>
                        
                        <!-- Quick stats -->
                        <div class="grid grid-cols-3 gap-4 mt-12">
                            <div>
                                <div class="text-2xl font-bold text-green-400 code-font">MIT</div>
                                <div class="text-sm text-gray-500">License</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-blue-400 code-font">12+</div>
                                <div class="text-sm text-gray-500">APIs</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-purple-400 code-font">∞</div>
                                <div class="text-sm text-gray-500">Scale</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom gradient border -->
            <div class="h-1 bg-gradient-to-r from-green-500 via-blue-500 to-purple-500"></div>
        </section>

        <!-- Quick Start Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Get Started in 3 Commands</h2>
                    <p class="text-xl text-gray-600">From zero to running banking API in under 5 minutes</p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="relative">
                        <div class="absolute -top-1 -left-1 w-10 h-10 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold z-10">1</div>
                        <div class="pl-12">
                            <h3 class="text-lg font-semibold mb-2">Clone the Repository</h3>
                            <div class="bg-gray-900 rounded-lg p-4 code-font text-sm">
                                <span class="text-gray-400">$</span> <span class="text-green-400">git clone</span> <span class="text-blue-400">finaegis/core</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="relative">
                        <div class="absolute -top-1 -left-1 w-10 h-10 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold z-10">2</div>
                        <div class="pl-12">
                            <h3 class="text-lg font-semibold mb-2">Install Dependencies</h3>
                            <div class="bg-gray-900 rounded-lg p-4 code-font text-sm">
                                <span class="text-gray-400">$</span> <span class="text-green-400">composer</span> <span class="text-blue-400">install</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="relative">
                        <div class="absolute -top-1 -left-1 w-10 h-10 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold z-10">3</div>
                        <div class="pl-12">
                            <h3 class="text-lg font-semibold mb-2">Start Building</h3>
                            <div class="bg-gray-900 rounded-lg p-4 code-font text-sm">
                                <span class="text-gray-400">$</span> <span class="text-green-400">php</span> <span class="text-purple-400">artisan</span> <span class="text-blue-400">serve</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Developer Features -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Built for Developers, By Developers</h2>
                    <p class="text-xl text-gray-600">Every feature designed with DX in mind</p>
                </div>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- RESTful APIs -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">RESTful APIs</h3>
                        <p class="text-gray-600 mb-4">Clean, intuitive API design following REST principles</p>
                        <div class="code-font text-sm bg-gray-100 rounded p-3">
                            <span class="text-blue-600">GET</span> /api/v1/accounts<br>
                            <span class="text-green-600">POST</span> /api/v1/transfers<br>
                            <span class="text-purple-600">PUT</span> /api/v1/users/:id
                        </div>
                    </div>
                    
                    <!-- Multi-SDK Support -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">SDK Everything</h3>
                        <p class="text-gray-600 mb-4">Official SDKs for your favorite language</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">JavaScript</span>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Python</span>
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">PHP</span>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Go</span>
                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Ruby</span>
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">More...</span>
                        </div>
                    </div>
                    
                    <!-- Docker Ready -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13.983 11.078h2.119a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.119a.185.185 0 00-.185.185v1.888c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 00.186-.186V3.574a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.185m0 2.716h2.118a.187.187 0 00.186-.186V6.29a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.887c0 .102.082.185.185.186m-2.93 0h2.12a.186.186 0 00.184-.186V6.29a.185.185 0 00-.185-.185H8.1a.185.185 0 00-.185.185v1.887c0 .102.083.185.185.186m-2.964 0h2.119a.186.186 0 00.185-.186V6.29a.185.185 0 00-.185-.185H5.136a.186.186 0 00-.186.185v1.887c0 .102.084.185.186.186m5.893 2.715h2.118a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.083.185.185.185m-2.964 0h2.119a.185.185 0 00.185-.185V9.006a.185.185 0 00-.184-.186h-2.12a.186.186 0 00-.186.186v1.887c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.082.185.185.185M23.763 9.89c-.065-.051-.672-.51-1.954-.51-.338.001-.676.03-1.01.087-.248-1.7-1.653-2.53-1.716-2.566l-.344-.199-.226.327c-.284.438-.49.922-.612 1.43-.23.97-.09 1.882.403 2.661-.595.332-1.55.413-1.744.42H.751a.751.751 0 00-.75.748 11.376 11.376 0 00.692 4.062c.545 1.428 1.355 2.48 2.41 3.124 1.18.723 3.1 1.137 5.275 1.137.983.003 1.963-.086 2.93-.266a12.248 12.248 0 003.823-1.389c.98-.567 1.86-1.288 2.61-2.136 1.252-1.418 1.998-2.997 2.553-4.4h.221c1.372 0 2.215-.549 2.68-1.009.309-.293.55-.65.707-1.046l.098-.288Z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Container First</h3>
                        <p class="text-gray-600 mb-4">Docker & Kubernetes ready out of the box</p>
                        <div class="code-font text-sm bg-gray-100 rounded p-3">
                            <span class="text-gray-500">$</span> docker-compose up -d
                        </div>
                    </div>
                    
                    <!-- Webhook Support -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Real-time Events</h3>
                        <p class="text-gray-600 mb-4">Webhooks for every important event</p>
                        <div class="space-y-1 text-sm">
                            <div class="flex items-center text-gray-700">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                transaction.completed
                            </div>
                            <div class="flex items-center text-gray-700">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                account.created
                            </div>
                            <div class="flex items-center text-gray-700">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                kyc.verified
                            </div>
                        </div>
                    </div>
                    
                    <!-- Testing Tools -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Test Everything</h3>
                        <p class="text-gray-600 mb-4">Comprehensive test suite included</p>
                        <div class="code-font text-sm bg-gray-100 rounded p-3">
                            <span class="text-gray-500">$</span> <span class="text-green-400">./vendor/bin/pest</span><br>
                            <span class="text-green-500">✓</span> 142 tests passed
                        </div>
                    </div>
                    
                    <!-- Documentation -->
                    <div class="hover-lift bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Docs That Don't Suck</h3>
                        <p class="text-gray-600 mb-4">Clear, example-driven documentation</p>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">API Reference</span>
                            <span class="text-indigo-600 font-semibold">View →</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Architecture Overview -->
        <section id="architecture" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Modular Architecture</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Pick what you need, leave what you don't. Every module works independently.
                    </p>
                </div>

                <!-- Core Platform -->
                <div class="bg-gray-900 rounded-2xl p-8 mb-12">
                    <h3 class="text-2xl font-bold text-white mb-8 text-center">Core Banking Engine</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        @foreach([
                            ['Identity & KYC', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'from-indigo-500 to-purple-600'],
                            ['Payments', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'from-blue-500 to-cyan-600'],
                            ['Security', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'from-green-500 to-emerald-600'],
                            ['Compliance', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'from-yellow-500 to-orange-600'],
                            ['Analytics', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'from-red-500 to-pink-600'],
                            ['API Gateway', 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', 'from-purple-500 to-indigo-600']
                        ] as [$name, $path, $gradient])
                            <div class="text-center group cursor-pointer">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br {{ $gradient }} rounded-xl mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"></path>
                                    </svg>
                                </div>
                                <h4 class="text-sm font-medium text-gray-300">{{ $name }}</h4>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Available Modules -->
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- GCU (Active) -->
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl p-6 text-white relative overflow-hidden">
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 bg-green-400 text-green-900 rounded-full text-xs font-bold">LIVE</span>
                        </div>
                        <div class="text-4xl font-bold mb-2">Ǥ</div>
                        <h3 class="text-xl font-bold mb-2">Global Currency Unit</h3>
                        <p class="text-indigo-100 text-sm mb-4">Democratic stablecoin backed by 6 currencies</p>
                        <a href="{{ route('gcu') }}" class="text-white font-semibold hover:underline">Learn more →</a>
                    </div>
                    
                    <!-- Exchange -->
                    <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl p-6 text-white relative overflow-hidden">
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 bg-green-400 text-green-900 rounded-full text-xs font-bold">LIVE</span>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Exchange</h3>
                        <p class="text-blue-100 text-sm mb-4">Multi-asset trading with BTC, ETH, and fiat pairs</p>
                        <a href="{{ route('exchange.index') }}" class="text-white font-semibold hover:underline">Start Trading →</a>
                    </div>
                    
                    <!-- Lending -->
                    <div class="hover-lift bg-gray-100 rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 bg-gray-300 text-gray-700 rounded-full text-xs font-bold">SOON</span>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Lending</h3>
                        <p class="text-gray-600 text-sm">P2P lending marketplace</p>
                    </div>
                    
                    <!-- Treasury -->
                    <div class="hover-lift bg-gray-100 rounded-xl p-6 relative overflow-hidden">
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 bg-gray-300 text-gray-700 rounded-full text-xs font-bold">SOON</span>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Treasury</h3>
                        <p class="text-gray-600 text-sm">Multi-bank cash management</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- GitHub CTA -->
        <section class="py-20 bg-gray-900 text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-8">
                    <svg class="w-12 h-12 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                </div>
                
                <h2 class="text-4xl font-bold mb-6">Join the Open Banking Revolution</h2>
                <p class="text-xl text-gray-300 mb-8">
                    Star us on GitHub and be part of the community building the future of finance
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="inline-flex items-center justify-center px-8 py-4 bg-white text-gray-900 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                        </svg>
                        Fork on GitHub
                    </a>
                    <a href="{{ route('developers') }}" class="inline-flex items-center justify-center px-8 py-4 border-2 border-white text-white rounded-lg text-lg font-semibold hover:bg-white hover:text-gray-900 transition">
                        Read the Docs
                    </a>
                </div>
                
                <!-- GitHub Stats -->
                <div class="grid grid-cols-3 gap-8 mt-12 max-w-2xl mx-auto">
                    <div>
                        <div class="text-3xl font-bold">MIT</div>
                        <div class="text-gray-400">License</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">100%</div>
                        <div class="text-gray-400">Open Source</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold">∞</div>
                        <div class="text-gray-400">Possibilities</div>
                    </div>
                </div>
            </div>
        </section>

        @include('partials.footer')
    </body>
</html>