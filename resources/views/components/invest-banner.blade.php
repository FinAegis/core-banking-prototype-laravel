@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'relative bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg shadow-lg overflow-hidden ' . $class]) }}>
    <div class="absolute inset-0 bg-black opacity-10 z-0"></div>

    <!-- Decorative elements with lower z-index -->
    <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-500 rounded-full opacity-20 z-0"></div>
    <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-purple-500 rounded-full opacity-20 z-0"></div>

    <div class="relative px-6 py-4 sm:px-8 sm:py-6 z-10">
        <div class="flex flex-col sm:flex-row items-center justify-between">
            <div class="mb-4 sm:mb-0 pr-8">
                <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                    Open Source Core Banking Infrastructure
                </h3>
                <p class="text-sm sm:text-base text-gray-300">
                    Explore the code, contribute on GitHub, or star the repo!
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ config('brand.github_url') }}"
                   target="_blank"
                   class="relative z-20 inline-flex items-center px-4 py-2 bg-white text-gray-900 font-medium rounded-lg hover:bg-gray-100 transition duration-150 ease-in-out shadow-md">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                    </svg>
                    View on GitHub
                </a>
            </div>
        </div>
    </div>

    <!-- Close button with higher z-index -->
    <button
        x-data
        @click="$el.closest('.invest-banner-container').remove()"
        class="absolute top-2 right-2 text-white hover:text-gray-300 transition duration-150 ease-in-out z-30"
        aria-label="Close banner">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>
