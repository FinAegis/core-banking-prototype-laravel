<!-- Navigation -->
<nav class="fixed w-full bg-white/95 backdrop-blur-sm border-b border-gray-100 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="/" class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">FinAegis</h1>
                </a>
            </div>
            
            <div class="hidden md:flex items-center space-x-8">
                <a href="/" class="text-gray-600 hover:text-gray-900 font-medium {{ request()->is('/') ? 'text-gray-900' : '' }}">Home</a>
                <a href="/features" class="text-gray-600 hover:text-gray-900 font-medium {{ request()->is('features*') ? 'text-gray-900' : '' }}">Features</a>
                <a href="/developers" class="text-gray-600 hover:text-gray-900 font-medium {{ request()->is('developers*') ? 'text-gray-900' : '' }}">Developers</a>
                <a href="/pricing" class="text-gray-600 hover:text-gray-900 font-medium {{ request()->is('pricing') ? 'text-gray-900' : '' }}">Pricing</a>
                <a href="/about" class="text-gray-600 hover:text-gray-900 font-medium {{ request()->is('about') ? 'text-gray-900' : '' }}">About</a>
            </div>
            
            <div class="flex items-center space-x-4">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-gray-900 font-medium">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 font-medium">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-700 transition">Get Started</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </div>
</nav>