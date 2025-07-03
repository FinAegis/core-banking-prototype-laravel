@props(['show' => true])

@if($show && !auth()->user()->cgoInvestments()->exists())
<div x-data="{ 
    show: false,
    dismissed: false,
    handleScroll() {
        if (this.dismissed) return;
        this.show = window.scrollY > 300;
    }
}" 
    x-init="window.addEventListener('scroll', handleScroll)"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="transform translate-y-full"
    x-transition:enter-end="transform translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="transform translate-y-0"
    x-transition:leave-end="transform translate-y-full"
    class="fixed bottom-6 right-6 z-50">
    
    <div class="relative">
        <a href="{{ route('cgo.invest') }}" 
           class="flex items-center px-6 py-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-white font-bold rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-200 ease-out">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Invest in CGO
        </a>
        
        <!-- Close button -->
        <button @click="show = false; dismissed = true" 
                class="absolute -top-2 -right-2 bg-white dark:bg-gray-800 rounded-full p-1 shadow-md hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out"
                aria-label="Dismiss">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>
@endif