<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Welcome back, {{ Auth::user()->name }}!
                </span>
                <button onclick="startTour()" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Take Tour
                </button>
            </div>
        </div>
    </x-slot>

    <!-- First Time User Welcome Modal -->
    @if(!Auth::user()->hasCompletedOnboarding ?? true)
    <div id="welcome-modal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="relative bg-white rounded-lg max-w-2xl w-full p-8 shadow-xl">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 mb-4">
                        <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Welcome to FinAegis!</h3>
                    <p class="text-lg text-gray-600 mb-6">
                        We're excited to have you onboard. Let's set up your account and explore the platform together.
                    </p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-900">Account Created</h4>
                            <p class="text-sm text-gray-500">Your FinAegis account is ready to use</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                <span class="text-yellow-600 font-semibold">2</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-900">Complete KYC Verification</h4>
                            <p class="text-sm text-gray-500">Verify your identity to unlock all features</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                <span class="text-gray-600 font-semibold">3</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-900">Fund Your Account</h4>
                            <p class="text-sm text-gray-500">Deposit funds to start using GCU</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                <span class="text-gray-600 font-semibold">4</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-900">Explore Features</h4>
                            <p class="text-sm text-gray-500">Discover GCU voting, transfers, and more</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-center space-x-4">
                    <button onclick="closeWelcomeModal()" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Skip for Now
                    </button>
                    <button onclick="startOnboarding()" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        Start Setup
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Total Balance -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Balance</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    ${{ number_format(Auth::user()->accounts->sum(function($account) { return $account->getBalance('USD'); }) / 100, 2) }}
                                </p>
                            </div>
                            <div class="p-3 bg-indigo-100 rounded-full">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <span class="text-green-600">↑ 12.5%</span> from last month
                        </p>
                    </div>
                </div>
                
                <!-- GCU Balance -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">GCU Balance</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    Ǥ{{ number_format(Auth::user()->accounts->sum(function($account) { return $account->getBalance('GCU'); }) / 100, 2) }}
                                </p>
                            </div>
                            <div class="p-3 bg-purple-100 rounded-full">
                                <span class="text-2xl text-purple-600">Ǥ</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Democratic global currency
                        </p>
                    </div>
                </div>
                
                <!-- Voting Power -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Voting Power</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ number_format(Auth::user()->accounts->sum(function($account) { return $account->getBalance('GCU'); }) / 100) }}
                                </p>
                            </div>
                            <div class="p-3 bg-green-100 rounded-full">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Next vote: {{ now()->startOfMonth()->addMonth()->format('M j') }}
                        </p>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Transactions</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ Auth::user()->accounts->sum(function($account) { return $account->transactions()->count(); }) }}
                                </p>
                            </div>
                            <div class="p-3 bg-yellow-100 rounded-full">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            This month
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="{{ route('wallet.deposit') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-indigo-100 rounded-full mb-2 group-hover:bg-indigo-200 transition">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Deposit</span>
                        </a>
                        
                        <a href="{{ route('wallet.withdraw') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-purple-100 rounded-full mb-2 group-hover:bg-purple-200 transition">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Withdraw</span>
                        </a>
                        
                        <a href="{{ route('wallet.transfer') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-green-100 rounded-full mb-2 group-hover:bg-green-200 transition">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Transfer</span>
                        </a>
                        
                        <a href="{{ route('wallet.convert') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-yellow-100 rounded-full mb-2 group-hover:bg-yellow-200 transition">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Convert</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- GCU Wallet Component (2 columns) -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <x-gcu-wallet />
                    </div>
                </div>
                
                <!-- Help & Resources (1 column) -->
                <div class="space-y-6">
                    <!-- Getting Started -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Getting Started</h3>
                            <ul class="space-y-3">
                                <li>
                                    <a href="#" class="flex items-center text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Complete KYC verification
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('support.guides') }}" class="flex items-center text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        Read the user guide
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="flex items-center text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                        Watch video tutorials
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Need Help? -->
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-indigo-900 dark:text-indigo-100 mb-2">Need Help?</h3>
                        <p class="text-sm text-indigo-700 dark:text-indigo-300 mb-4">
                            Our support team is available 24/7 to assist you with any questions.
                        </p>
                        <div class="space-y-2">
                            <a href="{{ route('support.faq') }}" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200">
                                → Browse FAQs
                            </a>
                            <a href="{{ route('support.contact') }}" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200">
                                → Contact Support
                            </a>
                            <button onclick="alert('Live chat coming soon!')" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200">
                                → Start Live Chat
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Tour Script -->
    <script>
        // Show welcome modal for first-time users
        @if(!Auth::user()->hasCompletedOnboarding ?? true)
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('welcome-modal').style.display = 'block';
        });
        @endif

        function closeWelcomeModal() {
            document.getElementById('welcome-modal').style.display = 'none';
        }

        function startOnboarding() {
            closeWelcomeModal();
            startTour();
        }

        function startTour() {
            // This would integrate with a tour library like Intro.js or Shepherd.js
            alert('Interactive tour coming soon! This will guide you through all the features of FinAegis.');
        }
    </script>
</x-app-layout>
