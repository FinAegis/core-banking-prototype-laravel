<!-- Platform Banners (Alpha + CGO) -->
<div class="relative z-50">
    <!-- Alpha Testing Banner (From main branch) - Only show on non-CGO pages -->
    @unless(request()->routeIs('cgo') || request()->routeIs('cgo.*'))
    <div class="bg-gradient-to-r from-red-600 to-orange-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-2">
                <div class="flex items-center justify-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span class="font-bold text-sm">ALPHA TESTING</span>
                    </div>
                    <div class="hidden sm:flex items-center space-x-2 text-xs">
                        <span class="px-2 py-0.5 bg-white/20 backdrop-blur-sm rounded-full">No Real Transactions</span>
                        <span class="px-2 py-0.5 bg-white/20 backdrop-blur-sm rounded-full">Demo Only</span>
                        <span class="px-2 py-0.5 bg-white/20 backdrop-blur-sm rounded-full">Prototype Mode</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endunless
    
    <!-- CGO (Continuous Growth Offering) Banner -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-2 px-4 text-center">
        <div class="max-w-7xl mx-auto flex items-center justify-center space-x-4">
            <p class="font-semibold text-sm sm:text-base">
                🚀 Invest in FinAegis CGO - Continuous Growth Offering
            </p>
            <a href="{{ route('cgo') }}" class="bg-white text-indigo-600 px-4 py-1 rounded-full text-sm font-bold hover:bg-gray-100 transition">
                Learn More
            </a>
        </div>
    </div>
</div>