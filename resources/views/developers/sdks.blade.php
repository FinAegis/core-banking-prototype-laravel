<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Official SDKs - Ready-to-use libraries for popular programming languages to accelerate your integration.">
        <meta name="keywords" content="FinAegis, SDK, API, JavaScript, Python, PHP, Go, Ruby, Java, integration">
        
        <title>SDKs - FinAegis Developer Documentation</title>

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
            .dev-gradient {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            }
            .code-block {
                font-family: 'Fira Code', monospace;
                font-size: 0.875rem;
                line-height: 1.5;
                overflow-x: auto;
                white-space: pre;
            }
            .code-container {
                position: relative;
                background: #1e293b;
                border-radius: 0.75rem;
                overflow: hidden;
            }
            .code-header {
                background: #0f172a;
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
                font-family: 'Figtree', sans-serif;
                color: #94a3b8;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .copy-button {
                background: #334155;
                padding: 0.375rem 0.75rem;
                border-radius: 0.375rem;
                color: #e2e8f0;
                font-size: 0.75rem;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                cursor: pointer;
                border: none;
            }
            .copy-button:hover {
                background: #475569;
                color: white;
            }
            .copy-button.copied {
                background: #10b981;
                color: white;
            }
            .terminal-dot {
                width: 0.75rem;
                height: 0.75rem;
                border-radius: 50%;
                display: inline-block;
            }
            .sdk-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            .sdk-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-color: #e5e7eb;
            }
            .floating-blob {
                position: absolute;
                filter: blur(80px);
                opacity: 0.3;
                animation: float 20s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% {
                    transform: translateY(0) rotate(0deg);
                }
                33% {
                    transform: translateY(-30px) rotate(120deg);
                }
                66% {
                    transform: translateY(20px) rotate(240deg);
                }
            }
            @keyframes blob {
                0%, 100% {
                    transform: translate(0, 0) scale(1);
                }
                25% {
                    transform: translate(20px, -50px) scale(1.1);
                }
                50% {
                    transform: translate(-20px, 20px) scale(0.9);
                }
                75% {
                    transform: translate(50px, 10px) scale(1.05);
                }
            }
            .animate-blob {
                animation: blob 10s infinite;
            }
            .animation-delay-2000 {
                animation-delay: 2s;
            }
            .animation-delay-4000 {
                animation-delay: 4s;
            }
        </style>
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 dev-gradient text-white relative overflow-hidden">
            <!-- Animated Background -->
            <div class="absolute inset-0">
                <div class="floating-blob w-96 h-96 bg-purple-600 rounded-full top-0 left-1/4"></div>
                <div class="floating-blob w-72 h-72 bg-blue-600 rounded-full bottom-0 right-1/4 animation-delay-2000"></div>
                <div class="floating-blob w-80 h-80 bg-indigo-600 rounded-full top-1/2 right-1/3 animation-delay-4000"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                        <span>Official SDKs Available</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6">
                        SDKs & Libraries
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-3xl mx-auto">
                        Integrate FinAegis into your application with our official SDKs. Type-safe, well-documented, and production-ready.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#sdks" class="bg-white text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Explore SDKs
                        </a>
                        <a href="{{ route('developers.show', 'api-reference') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                            API Reference
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- SDKs Grid -->
        <section id="sdks" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Available SDKs</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Choose your preferred programming language and start integrating with FinAegis in minutes
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <!-- JavaScript/Node.js SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M0 0h24v24H0V0zm22.034 18.276c-.175-1.095-.888-2.015-3.003-2.873-.736-.345-1.554-.585-1.797-1.14-.091-.33-.105-.51-.046-.705.15-.646.915-.84 1.515-.66.39.12.75.42.976.9 1.034-.676 1.034-.676 1.755-1.125-.27-.42-.404-.601-.586-.78-.63-.705-1.469-1.065-2.834-1.034l-.705.089c-.676.165-1.32.525-1.71 1.005-1.14 1.291-.811 3.541.569 4.471 1.365 1.02 3.361 1.244 3.616 2.205.24 1.17-.87 1.545-1.966 1.41-.811-.18-1.26-.586-1.755-1.336l-1.83 1.051c.21.48.45.689.81 1.109 1.74 1.756 6.09 1.666 6.871-1.004.029-.09.24-.705.074-1.65l.046.067zm-8.983-7.245h-2.248c0 1.938-.009 3.864-.009 5.805 0 1.232.063 2.363-.138 2.711-.33.689-1.18.601-1.566.48-.396-.196-.597-.466-.83-.855-.063-.105-.11-.196-.127-.196l-1.825 1.125c.305.63.75 1.172 1.324 1.517.855.51 2.004.675 3.207.405.783-.226 1.458-.691 1.811-1.411.51-.93.402-2.07.397-3.346.012-2.054 0-4.109 0-6.179l.004-.056z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">JavaScript/Node.js</h3>
                                <p class="text-gray-600">@finaegis/sdk</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">Full-featured SDK for Node.js applications and browser environments with TypeScript support.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Installation</span>
                                    <button onclick="copyCode(this, 'npm install @finaegis/sdk')" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-green-400">npm</span> <span class="text-blue-400">install</span> <span class="text-amber-400">@finaegis/sdk</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `import { FinAegis } from '@finaegis/sdk';

const client = new FinAegis({
  apiKey: 'your-api-key',
  environment: 'sandbox'
});

const accounts = await client.accounts.list();`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">import</span> { <span class="text-blue-400">FinAegis</span> } <span class="text-purple-400">from</span> <span class="text-amber-400">'@finaegis/sdk'</span>;

<span class="text-purple-400">const</span> <span class="text-blue-400">client</span> = <span class="text-purple-400">new</span> <span class="text-blue-400">FinAegis</span>({
  <span class="text-cyan-400">apiKey</span>: <span class="text-amber-400">'your-api-key'</span>,
  <span class="text-cyan-400">environment</span>: <span class="text-amber-400">'sandbox'</span>
});

<span class="text-purple-400">const</span> <span class="text-blue-400">accounts</span> = <span class="text-purple-400">await</span> <span class="text-blue-400">client</span>.<span class="text-cyan-400">accounts</span>.<span class="text-green-400">list</span>();</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-js" target="_blank" class="bg-yellow-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-yellow-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-yellow-600 hover:text-yellow-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Python SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14.25.18l.9.2.73.26.59.3.45.32.34.34.25.34.16.33.1.3.04.26.02.2-.01.13V8.5l-.05.63-.13.55-.21.46-.26.38-.3.31-.33.25-.35.19-.35.14-.33.1-.3.07-.26.04-.21.02H8.77l-.69.05-.59.14-.5.22-.41.27-.33.32-.27.35-.2.36-.15.37-.1.35-.07.32-.04.27-.02.21v3.06H3.17l-.21-.03-.28-.07-.32-.12-.35-.18-.36-.26-.36-.36-.35-.46-.32-.59-.28-.73-.21-.88-.14-1.05L0 11.97l.06-1.22.16-1.04.24-.87.32-.71.36-.57.4-.44.42-.33.42-.24.4-.16.36-.1.32-.05.24-.01h.16l.06.01h8.16v-.83H6.18l-.01-2.75-.02-.37.05-.34.11-.31.17-.28.25-.26.31-.23.38-.2.44-.18.51-.15.58-.12.64-.1.71-.06.77-.04.84-.02 1.27.05zm-6.3 1.98l-.23.33-.08.41.08.41.23.34.33.22.41.09.41-.09.33-.22.23-.34.08-.41-.08-.41-.23-.33-.33-.22-.41-.09-.41.09zm13.09 3.95l.28.06.32.12.35.18.36.27.36.35.35.47.32.59.28.73.21.88.14 1.04.05 1.23-.06 1.23-.16 1.04-.24.86-.32.71-.36.57-.4.45-.42.33-.42.24-.4.16-.36.09-.32.05-.24.02-.16-.01h-8.22v.82h5.84l.01 2.76.02.36-.05.34-.11.31-.17.29-.25.25-.31.24-.38.2-.44.17-.51.15-.58.13-.64.09-.71.07-.77.04-.84.01-1.27-.04-1.07-.14-.9-.2-.73-.25-.59-.3-.45-.33-.34-.34-.25-.34-.16-.33-.1-.3-.04-.25-.02-.2.01-.13v-5.34l.05-.64.13-.54.21-.46.26-.38.3-.32.33-.24.35-.2.35-.14.33-.1.3-.06.26-.04.21-.02.13-.01h5.84l.69-.05.59-.14.5-.21.41-.28.33-.32.27-.35.2-.36.15-.36.1-.35.07-.32.04-.28.02-.21V6.07h2.09l.14.01zm-6.47 14.25l-.23.33-.08.41.08.41.23.33.33.23.41.08.41-.08.33-.23.23-.33.08-.41-.08-.41-.23-.33-.33-.23-.41-.08-.41.08z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Python</h3>
                                <p class="text-gray-600">finaegis</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">Pythonic SDK with support for async/await and comprehensive type hints.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Installation</span>
                                    <button onclick="copyCode(this, 'pip install finaegis')" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-green-400">pip</span> <span class="text-blue-400">install</span> <span class="text-amber-400">finaegis</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `from finaegis import FinAegis

client = FinAegis(
    api_key='your-api-key',
    environment='sandbox'
)

accounts = client.accounts.list()`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">from</span> <span class="text-amber-400">finaegis</span> <span class="text-purple-400">import</span> <span class="text-blue-400">FinAegis</span>

<span class="text-blue-400">client</span> = <span class="text-blue-400">FinAegis</span>(
    <span class="text-cyan-400">api_key</span>=<span class="text-amber-400">'your-api-key'</span>,
    <span class="text-cyan-400">environment</span>=<span class="text-amber-400">'sandbox'</span>
)

<span class="text-blue-400">accounts</span> = <span class="text-blue-400">client</span>.<span class="text-cyan-400">accounts</span>.<span class="text-green-400">list</span>()</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-python" target="_blank" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-blue-600 hover:text-blue-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- PHP SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M7.01 10.207h-.944l-.515 2.648h.838c.556 0 .982-.122 1.292-.43.31-.307.464-.749.464-1.319 0-.838-.394-1.319-1.135-1.319zm.02 2.04c-.212 0-.384.005-.514.011l.324-1.617c.13-.007.28-.011.514-.011.458 0 .674.262.674.787 0 .524-.216.83-.998.83zm7.162 5.393c-.314.183-.688.275-1.122.275-.434 0-.801-.092-1.081-.275-.281-.183-.421-.419-.421-.708 0-.27.14-.506.421-.708.28-.183.647-.275 1.081-.275.434 0 .808.092 1.122.275.314.202.471.438.471.708 0 .289-.157.525-.471.708zm-6.79 0c-.314.183-.688.275-1.122.275-.434 0-.801-.092-1.081-.275-.281-.183-.421-.419-.421-.708 0-.27.14-.506.421-.708.28-.183.647-.275 1.081-.275.434 0 .808.092 1.122.275.314.202.471.438.471.708 0 .289-.157.525-.471.708zm0-12.837c-.314.183-.688.275-1.122.275-.434 0-.801-.092-1.081-.275-.281-.183-.421-.419-.421-.708 0-.27.14-.506.421-.708.28-.183.647-.275 1.081-.275.434 0 .808.092 1.122.275.314.202.471.438.471.708 0 .289-.157.525-.471.708zm8.82 5.46h.993l-.515 2.648h-.838c-.556 0-.982-.122-1.292-.43-.31-.307-.464-.749-.464-1.319 0-.838.394-1.319 1.135-1.319h.981zm-.019 2.04c.212 0 .384.005.514.011l-.324-1.617c-.13-.007-.28-.011-.514-.011-.458 0-.674.262-.674.787 0 .524.216.83.998.83zM24 12c0 6.627-5.373 12-12 12S0 18.627 0 12 5.373 0 12 0s12 5.373 12 12zm-19.5 0c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5-3.358-7.5-7.5-7.5-7.5 3.358-7.5 7.5z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">PHP</h3>
                                <p class="text-gray-600">finaegis/php-sdk</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">Modern PHP SDK compatible with PHP 8.0+ and PSR-4 autoloading.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Installation</span>
                                    <button onclick="copyCode(this, 'composer require finaegis/php-sdk')" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-green-400">composer</span> <span class="text-blue-400">require</span> <span class="text-amber-400">finaegis/php-sdk</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `use FinAegis\\Client;

$client = new Client([
    'api_key' => 'your-api-key',
    'environment' => 'sandbox'
]);

$accounts = $client->accounts()->list();`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">use</span> <span class="text-blue-400">FinAegis\Client</span>;

<span class="text-blue-400">$client</span> = <span class="text-purple-400">new</span> <span class="text-blue-400">Client</span>([
    <span class="text-amber-400">'api_key'</span> => <span class="text-amber-400">'your-api-key'</span>,
    <span class="text-amber-400">'environment'</span> => <span class="text-amber-400">'sandbox'</span>
]);

<span class="text-blue-400">$accounts</span> = <span class="text-blue-400">$client</span>-><span class="text-cyan-400">accounts</span>()-><span class="text-green-400">list</span>();</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-php" target="_blank" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-purple-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-purple-600 hover:text-purple-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Go SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-cyan-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-cyan-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M1.811 10.231c-.047 0-.058-.023-.035-.059l.246-.315c.023-.035.081-.058.128-.058h4.172c.046 0 .058.035.035.07l-.199.303c-.023.036-.082.07-.117.07zM.047 11.306c-.047 0-.059-.023-.035-.058l.245-.316c.023-.035.082-.058.129-.058h5.328c.047 0 .07.035.058.07l-.093.28c-.012.047-.058.07-.105.07zM2.828 12.381c-.047 0-.059-.035-.035-.07l.163-.292c.023-.035.070-.070.117-.070h2.337c.047 0 .070.035.070.082l-.023.28c0 .047-.047.082-.082.082zm8.967-2.978c-1.678.209-2.848.907-2.848 1.644 0 .878 1.063 1.191 2.313 1.191 1.295 0 2.590-.35 2.590-1.284 0-.537-.444-.932-1.227-1.098-.315-.070-.547-.14-.828-.453zm2.941-1.367c.697.814 1.017 1.944 1.017 3.1 0 2.037-1.018 3.265-2.711 3.451l-.35.046c-.117.012-.175.035-.175.105 0 .07.058.116.198.14l1.052.175c1.179.199 1.66.652 1.66 1.414 0 1.32-1.367 2.037-3.637 2.037-2.36 0-3.915-.65-3.915-1.74 0-.419.21-.816.595-1.145l.70-.594c.397-.35.595-.628.595-.977 0-.314-.117-.548-.362-.792l-.315-.302c-.14-.117-.233-.233-.233-.35 0-.093.047-.175.14-.175zm-.315-2.094c.175 0 .362.023.537.07.14.046.269.139.362.279.152.257.222.594.222 1.063 0 1.005-.302 1.678-.952 2.048-.14.082-.233.128-.233.198 0 .07.082.105.175.152.222.117.501.395.501.814 0 .582-.488 1.343-1.413 1.343-.222 0-.397-.035-.537-.07l-.362-.128c-.14-.082-.152-.117-.152-.199 0-.105.093-.210.245-.350.093-.093.175-.163.175-.233 0-.07-.058-.105-.14-.128-.35-.116-.619-.35-.795-.746-.163-.373-.21-.746-.21-1.145 0-.966.315-1.49.745-1.944.152-.151.339-.222.537-.222zm7.314-.77c.47 0 .967.117 1.274.35.245.175.35.42.35.746 0 .467-.315.84-.979 1.064-.117.046-.128.070-.01.128.35.175.559.49.559.932 0 .746-.49 1.18-1.413 1.343-.222.035-.432.046-.652.046h-2.8c-.315 0-.48-.117-.48-.373 0-.14.046-.303.105-.466l.152-.327c.175-.373.35-.699.735-.699h.477c.152 0 .245-.023.35-.082.117-.07.175-.175.175-.315 0-.222-.117-.35-.35-.35h-.735c-.35 0-.525-.116-.525-.373 0-.117.047-.233.105-.327l.152-.315c.175-.35.385-.641.77-.641zm-5.328 6.985c.315 0 .652.023.979.058.467.058.828.175 1.063.35.105.07.152.14.152.21 0 .105-.082.175-.233.175-.234 0-.5-.046-.792-.082-.792-.093-1.343-.14-1.898-.14-.467 0-.792.035-.979.105-.058.023-.117.046-.175.07-.093.035-.152.058-.21.058-.14 0-.175-.07-.175-.175 0-.233.175-.466.49-.652.35-.21.828-.35 1.413-.35zm.525-9.728c.315 0 .652.058.932.175.315.117.583.35.652.652.07.292.07.631.035.956-.047.467-.175.863-.35 1.226-.175.362-.42.699-.722.933-.175.117-.362.175-.548.175-.303 0-.652-.117-.956-.35-.35-.233-.583-.583-.652-.979-.07-.315-.035-.698.035-1.063.07-.467.21-.933.42-1.295.21-.35.49-.699.77-.886.14-.082.29-.12.445-.12zm9.61.14c.07.07.117.175.117.315 0 .14-.047.292-.128.385-.175.222-.42.315-.722.315-.245 0-.49-.07-.687-.175-.233-.117-.397-.292-.397-.548 0-.175.07-.35.21-.49.175-.175.42-.245.722-.245.245 0 .49.07.687.175.175.105.292.245.292.42zm-.595 3.382c.07.07.117.175.117.315 0 .14-.047.292-.128.385-.175.222-.42.315-.722.315-.245 0-.49-.07-.687-.175-.233-.117-.397-.292-.397-.548 0-.175.07-.35.21-.49.175-.175.42-.245.722-.245.245 0 .49.07.687.175.175.105.292.245.292.42z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Go</h3>
                                <p class="text-gray-600">finaegis-go</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">High-performance Go SDK with context support and comprehensive error handling.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Installation</span>
                                    <button onclick="copyCode(this, 'go get github.com/finaegis/finaegis-go')" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-green-400">go</span> <span class="text-blue-400">get</span> <span class="text-amber-400">github.com/finaegis/finaegis-go</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `import "github.com/finaegis/finaegis-go"

client := finaegis.NewClient(
    finaegis.WithAPIKey("your-api-key"),
    finaegis.WithEnvironment("sandbox"),
)

accounts, err := client.Accounts.List(ctx)`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">import</span> <span class="text-amber-400">"github.com/finaegis/finaegis-go"</span>

<span class="text-blue-400">client</span> := <span class="text-blue-400">finaegis</span>.<span class="text-green-400">NewClient</span>(
    <span class="text-blue-400">finaegis</span>.<span class="text-green-400">WithAPIKey</span>(<span class="text-amber-400">"your-api-key"</span>),
    <span class="text-blue-400">finaegis</span>.<span class="text-green-400">WithEnvironment</span>(<span class="text-amber-400">"sandbox"</span>),
)

<span class="text-blue-400">accounts</span>, <span class="text-blue-400">err</span> := <span class="text-blue-400">client</span>.<span class="text-cyan-400">Accounts</span>.<span class="text-green-400">List</span>(<span class="text-blue-400">ctx</span>)</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-go" target="_blank" class="bg-cyan-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-cyan-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-cyan-600 hover:text-cyan-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Ruby SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.156.083c3.033.525 3.893 2.598 3.829 4.77L24 4.822 22.635 22.71 4.89 23.926l.875-3.548L.022 17.848l9.745-2.78-.362 1.172 6.639-1.913-.687 2.23 5.624-1.617-.243.804L24 14.926V4.852c-.065-2.19-.903-4.353-3.844-4.77zM9.219 13.477c-.49-.378-1.147-.612-1.778-.612-.886 0-1.653.482-1.968 1.228l1.463.956c.15-.267.488-.434.85-.434.312 0 .612.123.736.323.166.271.036.587-.278.81-.344.237-.703.29-1.058.36-.446.087-.878.27-1.245.602-.328.296-.564.736-.564 1.315 0 1.157.828 2.14 2.1 2.14 1.266 0 2.099-.983 2.099-2.14 0-.479-.135-.881-.35-1.199zm11.547-5.34L17.86 6.87l-3.15 5.293-2.094-3.534L9.979 7.37 8.03 12.57l1.935 1.166 1.348-2.267 1.734 2.912 3.64-6.118 1.86 3.126h2.22z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Ruby</h3>
                                <p class="text-gray-600">finaegis-ruby</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">Idiomatic Ruby SDK with Rails integration and comprehensive test coverage.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Installation</span>
                                    <button onclick="copyCode(this, 'gem install finaegis')" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-green-400">gem</span> <span class="text-blue-400">install</span> <span class="text-amber-400">finaegis</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `require 'finaegis'

client = FinAegis::Client.new(
  api_key: 'your-api-key',
  environment: 'sandbox'
)

accounts = client.accounts.list`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">require</span> <span class="text-amber-400">'finaegis'</span>

<span class="text-blue-400">client</span> = <span class="text-blue-400">FinAegis</span>::<span class="text-blue-400">Client</span>.<span class="text-green-400">new</span>(
  <span class="text-cyan-400">api_key:</span> <span class="text-amber-400">'your-api-key'</span>,
  <span class="text-cyan-400">environment:</span> <span class="text-amber-400">'sandbox'</span>
)

<span class="text-blue-400">accounts</span> = <span class="text-blue-400">client</span>.<span class="text-cyan-400">accounts</span>.<span class="text-green-400">list</span></pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-ruby" target="_blank" class="bg-red-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-red-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-red-600 hover:text-red-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Java SDK -->
                    <div class="sdk-card bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-8 h-8 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8.851 18.56s-.917.534.653.714c1.902.218 2.874.187 4.969-.211 0 0 .552.346 1.321.646-4.699 2.013-10.633-.118-6.943-1.149m-.575-2.627s-1.028.761.542.924c2.032.209 3.636.227 6.413-.308 0 0 .384.389.987.602-5.679 1.661-12.007.13-7.942-1.218m4.84-4.458c1.158 1.333-.304 2.533-.304 2.533s2.939-1.518 1.589-3.418c-1.261-1.772-2.228-2.652 3.007-5.688 0-.001-8.216 2.051-4.292 6.573m6.214 9.029s.679.559-.747.991c-2.712.822-11.288 1.069-13.669.033-.856-.373.75-.89 1.254-.998.527-.114.828-.093.828-.093-.953-.671-6.156 1.317-2.643 1.887 9.58 1.553 17.462-.7 14.977-1.82M9.292 13.21s-4.362 1.036-1.544 1.412c1.189.159 3.561.123 5.77-.062 1.806-.152 3.618-.477 3.618-.477s-.637.272-1.098.587c-4.429 1.165-12.986.623-10.522-.568 2.082-1.006 3.776-.892 3.776-.892m7.824 4.374c4.503-2.34 2.421-4.589.968-4.285-.355.074-.515.138-.515.138s.132-.207.385-.297c2.875-1.011 5.086 2.981-.928 4.562 0-.001.07-.062.09-.118M14.401 0s2.494 2.494-2.365 6.33c-3.896 3.077-.888 4.832-.001 6.836-2.274-2.053-3.943-3.858-2.824-5.539 1.644-2.469 6.197-3.665 5.19-7.627M9.734 23.924c4.322.277 10.959-.153 11.116-2.198 0 0-.302.775-3.572 1.391-3.688.694-8.239.613-10.937.168 0-.001.553.457 3.393.639"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Java</h3>
                                <p class="text-gray-600">com.finaegis/sdk</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6">Enterprise-ready Java SDK with Spring Boot integration and Maven/Gradle support.</p>
                        
                        <div class="space-y-4 mb-6">
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Maven Installation</span>
                                    <button onclick="copyCode(this, `<dependency>
    <groupId>com.finaegis</groupId>
    <artifactId>sdk</artifactId>
    <version>1.0.0</version>
</dependency>`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-gray-400">&lt;dependency&gt;</span>
    <span class="text-gray-400">&lt;groupId&gt;</span><span class="text-amber-400">com.finaegis</span><span class="text-gray-400">&lt;/groupId&gt;</span>
    <span class="text-gray-400">&lt;artifactId&gt;</span><span class="text-amber-400">sdk</span><span class="text-gray-400">&lt;/artifactId&gt;</span>
    <span class="text-gray-400">&lt;version&gt;</span><span class="text-amber-400">1.0.0</span><span class="text-gray-400">&lt;/version&gt;</span>
<span class="text-gray-400">&lt;/dependency&gt;</span></pre>
                                </div>
                            </div>
                            
                            <div class="code-container">
                                <div class="code-header">
                                    <span>Quick Start</span>
                                    <button onclick="copyCode(this, `import com.finaegis.sdk.FinAegisClient;

FinAegisClient client = FinAegisClient.builder()
    .apiKey("your-api-key")
    .environment("sandbox")
    .build();

List<Account> accounts = client.accounts().list();`)" class="copy-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Copy
                                    </button>
                                </div>
                                <div class="p-4">
                                    <pre class="code-block"><span class="text-purple-400">import</span> <span class="text-blue-400">com.finaegis.sdk.FinAegisClient</span>;

<span class="text-blue-400">FinAegisClient</span> <span class="text-blue-400">client</span> = <span class="text-blue-400">FinAegisClient</span>.<span class="text-green-400">builder</span>()
    .<span class="text-green-400">apiKey</span>(<span class="text-amber-400">"your-api-key"</span>)
    .<span class="text-green-400">environment</span>(<span class="text-amber-400">"sandbox"</span>)
    .<span class="text-green-400">build</span>();

<span class="text-blue-400">List</span>&lt;<span class="text-blue-400">Account</span>&gt; <span class="text-blue-400">accounts</span> = <span class="text-blue-400">client</span>.<span class="text-cyan-400">accounts</span>().<span class="text-green-400">list</span>();</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-auto">
                            <a href="https://github.com/FinAegis/finaegis-java" target="_blank" class="bg-orange-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-orange-700 transition inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="{{ route('developers.show', 'api-reference') }}" class="text-orange-600 hover:text-orange-800 font-semibold text-sm inline-flex items-center">
                                Documentation 
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">SDK Features</h2>
                    <p class="text-xl text-gray-600">All SDKs include these powerful features</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Type Safety</h3>
                        <p class="text-gray-600">Full type definitions and compile-time safety where supported by the language</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Performance</h3>
                        <p class="text-gray-600">Optimized for speed with connection pooling and intelligent caching</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Security</h3>
                        <p class="text-gray-600">Built-in security best practices and secure credential management</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Error Handling</h3>
                        <p class="text-gray-600">Comprehensive error handling with detailed error messages and codes</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Developer Experience</h3>
                        <p class="text-gray-600">Intuitive APIs with comprehensive documentation and examples</p>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Auto-retry</h3>
                        <p class="text-gray-600">Automatic retries with exponential backoff for transient failures</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Coming Soon SDKs -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Coming Soon</h2>
                    <p class="text-xl text-gray-600">More SDKs are in development</p>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6 max-w-4xl mx-auto">
                    <div class="text-center p-6 bg-white rounded-xl shadow-sm">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm3.894 8.221c-.176-.099-.291-.243-.291-.392 0-.396.543-.573.988-.573.728 0 1.165.396 1.331.594l1.133-.551C18.327 6.452 17.451 6 16.27 6c-1.056 0-2.254.616-2.254 1.76 0 .66.33 1.111.792 1.452l1.375.858c.209.132.308.308.308.462 0 .429-.594.627-1.122.627-.891 0-1.243-.429-1.375-.693l-1.133.55c.528.847 1.375 1.364 2.508 1.364 1.221 0 2.376-.715 2.376-1.848 0-.693-.33-1.155-.825-1.496l-1.026-.617zm-7.788.011c-.176-.099-.291-.243-.291-.392 0-.396.543-.573.988-.573.728 0 1.165.396 1.331.594l1.133-.551C10.539 6.463 9.663 6.011 8.482 6.011c-1.056 0-2.254.616-2.254 1.76 0 .66.33 1.111.792 1.452l1.375.858c.209.132.308.308.308.462 0 .429-.594.627-1.122.627-.891 0-1.243-.429-1.375-.693l-1.133.55c.528.847 1.375 1.364 2.508 1.364 1.221 0 2.376-.715 2.376-1.848 0-.693-.33-1.155-.825-1.496l-1.026-.617z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-700">C#/.NET</h3>
                    </div>
                    
                    <div class="text-center p-6 bg-white rounded-xl shadow-sm">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M1.292 5.856l11.54-5.752c.766-.383 1.576-.383 2.336 0l11.54 5.752c.834.416 1.292 1.352 1.292 2.144v11.592c0 .792-.458 1.728-1.292 2.144l-11.54 5.752c-.366.183-.768.274-1.168.274s-.802-.091-1.168-.274L1.292 21.736C.458 21.32 0 20.384 0 19.592V8c0-.792.458-1.728 1.292-2.144zm1.968 3.636v9.024c0 .182.074.348.202.416l8.816 4.4c.164.082.356.082.52 0l8.816-4.4c.128-.068.202-.234.202-.416V9.492l-9.828 4.914c-.366.182-.774.274-1.168.274s-.802-.092-1.168-.274L2.024 9.492z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-700">Rust</h3>
                    </div>
                    
                    <div class="text-center p-6 bg-white rounded-xl shadow-sm">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M0 12c0 6.627 5.373 12 12 12s12-5.373 12-12S18.627 0 12 0 0 5.373 0 12zm9.75-2.952c1.017-.985 2.033-.677 2.72.385.688-1.063 1.703-1.37 2.72-.385 1.389 1.344.479 2.954-.828 4.024L12 16.247l-2.332-3.173c-1.307-1.07-2.218-2.68-.828-4.024z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-700">Swift</h3>
                    </div>
                    
                    <div class="text-center p-6 bg-white rounded-xl shadow-sm">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 5.173l2.335 4.817 5.305.732-3.861 3.71.942 5.27L12 17.178l-4.721 2.525.942-5.27-3.861-3.71 5.305-.732L12 5.173zm0-4.586L8.332 8.155 0 9.306l6.064 5.828-1.48 8.279L12 19.446l7.416 3.967-1.48-8.279L24 9.306l-8.332-1.151L12 .587z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-700">Kotlin</h3>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to integrate?</h2>
                <p class="text-xl text-purple-100 mb-8">
                    Choose your preferred language and start building with FinAegis today
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-purple-700 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Get API Keys
                    </a>
                    <a href="{{ route('developers.show', 'examples') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                        View Examples
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')
        
        <script>
            function copyCode(button, code) {
                navigator.clipboard.writeText(code).then(function() {
                    button.classList.add('copied');
                    button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Copied';
                    
                    setTimeout(function() {
                        button.classList.remove('copied');
                        button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg> Copy';
                    }, 2000);
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                });
            }
        </script>
    </body>
</html>