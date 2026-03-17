@props(['show' => true])

@if($show)
<div x-data="{
    show: false,
    dismissed: localStorage.getItem('github_cta_dismissed') === 'true',
    handleScroll() {
        if (this.dismissed) return;
        this.show = window.scrollY > 300;
    },
    dismiss() {
        this.show = false;
        this.dismissed = true;
        localStorage.setItem('github_cta_dismissed', 'true');
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
        <a href="{{ config('brand.github_url') }}"
           target="_blank"
           class="flex items-center px-6 py-3 bg-gradient-to-r from-gray-700 to-gray-900 text-white font-bold rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition duration-200 ease-out">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
            </svg>
            Star on GitHub
        </a>

        <!-- Close button -->
        <button @click="dismiss()"
                class="absolute -top-2 -right-2 bg-white dark:bg-gray-800 rounded-full p-1 shadow-md hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out"
                aria-label="Dismiss">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>
@endif
