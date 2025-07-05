<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Generate New Blockchain Address') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <!-- Security Notice -->
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Important Security Information</h3>
                            <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>Your private keys are encrypted and stored securely</li>
                                    <li>Never share your password or backup phrase with anyone</li>
                                    <li>Make sure to backup your wallet after generating new addresses</li>
                                    <li>FinAegis cannot recover lost passwords or private keys</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Generation Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('wallet.blockchain.generate') }}">
                        @csrf

                        <!-- Blockchain Selection -->
                        <div class="mb-6">
                            <x-label for="chain" value="{{ __('Select Blockchain Network') }}" />
                            <div class="mt-2 grid grid-cols-2 gap-4">
                                @foreach($supportedChains as $chainKey => $chain)
                                    <label class="relative flex cursor-pointer rounded-lg border bg-white dark:bg-gray-700 p-4 shadow-sm focus:outline-none">
                                        <input type="radio" name="chain" value="{{ $chainKey }}" class="sr-only" required>
                                        <div class="flex flex-1">
                                            <div class="flex flex-col">
                                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $chain['name'] }}
                                                </span>
                                                <span class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $chain['symbol'] }}
                                                </span>
                                                <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                                    @if($chainKey === 'bitcoin')
                                                        The original cryptocurrency, ideal for value storage
                                                    @elseif($chainKey === 'ethereum')
                                                        Smart contract platform with DeFi capabilities
                                                    @elseif($chainKey === 'polygon')
                                                        Fast and low-cost Ethereum scaling solution
                                                    @else
                                                        High-performance blockchain for DeFi applications
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('chain')" class="mt-2" />
                        </div>

                        <!-- Address Label -->
                        <div class="mb-6">
                            <x-label for="label" value="{{ __('Address Label') }}" />
                            <x-input id="label" 
                                     type="text" 
                                     name="label" 
                                     class="mt-1 block w-full" 
                                     placeholder="e.g., Main Wallet, Trading Account, Savings"
                                     required />
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                A friendly name to help you identify this address
                            </p>
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                        </div>

                        <!-- Password Confirmation -->
                        <div class="mb-6">
                            <x-label for="password" value="{{ __('Confirm Your Password') }}" />
                            <x-input id="password" 
                                     type="password" 
                                     name="password" 
                                     class="mt-1 block w-full" 
                                     required />
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Enter your account password to authorize address generation
                            </p>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Advanced Options -->
                        <div class="mb-6">
                            <details class="group">
                                <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">
                                    Advanced Options
                                </summary>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   name="generate_multiple" 
                                                   value="1" 
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                Generate multiple addresses for enhanced privacy
                                            </span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   name="enable_notifications" 
                                                   value="1" 
                                                   checked
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                Enable transaction notifications for this address
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </details>
                        </div>

                        @if ($errors->has('error'))
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('error') }}</p>
                            </div>
                        @endif

                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('wallet.blockchain.index') }}" 
                               class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Generate Address
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Handle radio button styling
        document.querySelectorAll('input[type="radio"][name="chain"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Reset all
                document.querySelectorAll('input[type="radio"][name="chain"]').forEach(r => {
                    r.closest('label').classList.remove('border-indigo-600', 'ring-2', 'ring-indigo-600');
                    r.closest('label').classList.add('border');
                });
                
                // Highlight selected
                if (this.checked) {
                    this.closest('label').classList.add('border-indigo-600', 'ring-2', 'ring-indigo-600');
                    this.closest('label').classList.remove('border');
                }
            });
        });
    </script>
    @endpush
</x-app-layout>