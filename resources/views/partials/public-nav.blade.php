<!-- Navigation -->
<nav class="bg-white shadow-lg fixed w-full z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <a href="/" class="flex items-center">
                    <span class="text-2xl font-bold text-indigo-600">Fin</span>
                    <span class="text-2xl font-bold text-purple-600">Aegis</span>
                </a>
                
                <!-- Main Navigation -->
                <div class="hidden md:ml-10 md:flex md:space-x-8">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('home') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Home</a>
                    <a href="{{ route('about') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('about') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">About</a>
                    <a href="{{ route('features') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('features*') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Features</a>
                    <a href="{{ route('pricing') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('pricing') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Pricing</a>
                    <a href="{{ route('developers') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('developers*') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Developers</a>
                    <a href="{{ route('support') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('support*') ? 'text-indigo-600 border-b-2 border-indigo-600' : '' }}">Support</a>
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
                @else
                    <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Login</a>
                    <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Get Started</a>
                @endauth
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
            <a href="{{ route('about') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">About</a>
            <a href="{{ route('features') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Features</a>
            <a href="{{ route('pricing') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Pricing</a>
            <a href="{{ route('developers') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Developers</a>
            <a href="{{ route('support') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Support</a>
            <hr class="my-2">
            @auth
                <a href="{{ route('dashboard') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50">Login</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 text-base font-medium bg-indigo-600 text-white hover:bg-indigo-700 mx-3 rounded-lg text-center">Get Started</a>
            @endauth
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