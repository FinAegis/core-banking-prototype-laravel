<!-- Navigation -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <a href="/" class="flex items-center">
                    <span class="text-2xl font-bold text-indigo-600">Fin</span>
                    <span class="text-2xl font-bold text-purple-600">Aegis</span>
                </a>
                
                <!-- Main Navigation -->
                <div class="hidden md:ml-10 md:flex md:space-x-6">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('home') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Home</a>
                    
                    <!-- Products Dropdown -->
                    <div class="relative group">
                        <button class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium inline-flex items-center {{ request()->routeIs(['platform*', 'features*', 'ai-framework*']) ? 'text-indigo-600' : '' }}">
                            Products
                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-1">
                                <a href="{{ route('platform') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('platform*') ? 'bg-gray-50 text-indigo-600' : '' }}">Core Banking</a>
                                <a href="{{ route('features') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('features*') ? 'bg-gray-50 text-indigo-600' : '' }}">Banking Services</a>
                                <a href="{{ route('gcu') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('gcu*') ? 'bg-gray-50 text-indigo-600' : '' }}">Global Currency Unit</a>
                                <a href="{{ route('ai-framework') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('ai-framework*') ? 'bg-gray-50 text-indigo-600' : '' }}">AI Framework</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resources Dropdown -->
                    <div class="relative group">
                        <button class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium inline-flex items-center {{ request()->routeIs(['developers*', 'support*', 'about']) ? 'text-indigo-600' : '' }}">
                            Resources
                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-1">
                                <a href="{{ route('developers') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('developers*') ? 'bg-gray-50 text-indigo-600' : '' }}">Developers</a>
                                <a href="/api/documentation" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">API Docs</a>
                                <a href="{{ route('support') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('support*') ? 'bg-gray-50 text-indigo-600' : '' }}">Support</a>
                                <a href="{{ route('about') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('about') ? 'bg-gray-50 text-indigo-600' : '' }}">About Us</a>
                            </div>
                        </div>
                    </div>
                    
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('pricing') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Pricing</a>
                    <a href="{{ route('cgo') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('cgo*') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Invest</a>
                </div>
            </div>
            
            <!-- Right Navigation -->
            <div class="flex items-center space-x-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Logout</button>
                    </form>
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Login</a>
                    <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Get Started</a>
                @endguest
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-button" class="text-gray-700 hover:text-indigo-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="{{ route('home') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Home</a>
            
            <!-- Products Section -->
            <div class="border-t border-gray-200 pt-2 mt-2">
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Products</div>
                <a href="{{ route('platform') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Core Banking</a>
                <a href="{{ route('features') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Banking Services</a>
                <a href="{{ route('gcu') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Global Currency Unit</a>
                <a href="{{ route('ai-framework') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">AI Framework</a>
            </div>
            
            <!-- Resources Section -->
            <div class="border-t border-gray-200 pt-2 mt-2">
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Resources</div>
                <a href="{{ route('developers') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Developers</a>
                <a href="/api/documentation" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">API Docs</a>
                <a href="{{ route('support') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Support</a>
                <a href="{{ route('about') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">About Us</a>
            </div>
            
            <a href="{{ route('pricing') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Pricing</a>
            <a href="{{ route('cgo') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Invest</a>
            
            <hr class="my-2">
            @auth
                <a href="{{ route('dashboard') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Logout</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Login</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 text-base font-medium bg-indigo-600 text-white hover:bg-indigo-700 mx-3 rounded-lg text-center">Get Started</a>
            @endguest
        </div>
    </div>
</nav>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
</script>