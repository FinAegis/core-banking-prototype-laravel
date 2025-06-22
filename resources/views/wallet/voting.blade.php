<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('GCU Governance Voting') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div id="voting-app">
                <gcu-voting-dashboard></gcu-voting-dashboard>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ mix('js/app.js') }}"></script>
    <script>
        // Initialize Vue app
        const { createApp } = Vue;
        
        createApp({
            components: {
                'gcu-voting-dashboard': GCUVotingDashboard
            }
        }).mount('#voting-app');
    </script>
    @endpush
</x-app-layout>