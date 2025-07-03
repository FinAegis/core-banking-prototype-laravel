@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'relative bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg shadow-lg overflow-hidden ' . $class]) }}>
    <div class="absolute inset-0 bg-black opacity-10"></div>
    <div class="relative px-6 py-4 sm:px-8 sm:py-6">
        <div class="flex flex-col sm:flex-row items-center justify-between">
            <div class="mb-4 sm:mb-0">
                <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                    Join the CGO Investment Round
                </h3>
                <p class="text-sm sm:text-base text-purple-100">
                    Be part of the future of decentralized finance. Limited spots available!
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('cgo') }}" 
                   class="inline-flex items-center px-4 py-2 bg-white text-purple-600 font-medium rounded-lg hover:bg-purple-50 transition duration-150 ease-in-out shadow-md">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Learn More
                </a>
                @auth
                    @if(auth()->user()->hasVerifiedEmail())
                        <a href="{{ route('cgo.invest') }}" 
                           class="inline-flex items-center px-4 py-2 bg-yellow-400 text-purple-900 font-bold rounded-lg hover:bg-yellow-300 transition duration-150 ease-in-out shadow-md">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Invest Now
                        </a>
                    @endif
                @endauth
            </div>
        </div>
        
        <!-- Decorative elements -->
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-purple-500 rounded-full opacity-20"></div>
        <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-indigo-500 rounded-full opacity-20"></div>
    </div>
    
    <!-- Close button -->
    <button 
        x-data
        @click="$el.closest('.invest-banner-container').remove()"
        class="absolute top-2 right-2 text-white hover:text-purple-200 transition duration-150 ease-in-out"
        aria-label="Close banner">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>