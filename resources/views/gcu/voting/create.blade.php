<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create Voting Proposal') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-6">
                <a href="{{ route('gcu.voting.index') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                    ← Back to Voting
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <form action="{{ route('gcu.voting.store') }}" method="POST" class="p-6 lg:p-8">
                    @csrf

                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Proposal Title
                            </label>
                            <input type="text" name="title" id="title" required
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                   value="{{ old('title') }}"
                                   placeholder="Monthly GCU Composition Adjustment - September 2024">
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea name="description" id="description" rows="3" required
                                      class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                      placeholder="Describe the proposed changes...">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Rationale -->
                        <div>
                            <label for="rationale" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Rationale
                            </label>
                            <textarea name="rationale" id="rationale" rows="4" required
                                      class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                      placeholder="Explain why these changes are beneficial...">{{ old('rationale') }}</textarea>
                            @error('rationale')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Composition -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                                Proposed Composition
                            </label>
                            
                            <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg mb-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Current Composition:</p>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                                    @foreach($currentComposition as $currency => $percentage)
                                        <div>
                                            <span class="font-medium">{{ $currency }}:</span> {{ $percentage }}%
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="space-y-4">
                                @php
                                    $currencies = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'CHF' => 'Swiss Franc', 'JPY' => 'Japanese Yen', 'XAU' => 'Gold'];
                                @endphp
                                
                                @foreach($currencies as $code => $name)
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center space-x-2 w-32">
                                        <span class="font-medium">{{ $code }}</span>
                                        <span class="text-sm text-gray-500">{{ $name }}</span>
                                    </label>
                                    <input type="number" 
                                           name="composition[{{ $code }}]" 
                                           id="composition_{{ $code }}"
                                           class="focus:ring-indigo-500 focus:border-indigo-500 block w-24 shadow-sm sm:text-sm border-gray-300 rounded-md"
                                           min="0" max="100" step="0.1"
                                           value="{{ old('composition.'.$code, $currentComposition[$code] ?? 0) }}"
                                           onchange="updateTotal()">
                                    <span class="text-sm text-gray-500">%</span>
                                </div>
                                @endforeach
                                
                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Total:</span>
                                        <span id="composition-total" class="font-bold text-lg">0%</span>
                                    </div>
                                    <p id="composition-error" class="mt-1 text-sm text-red-600 hidden">
                                        Total must equal exactly 100%
                                    </p>
                                </div>
                            </div>
                            @error('composition')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Voting Period -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="voting_starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Voting Starts
                                </label>
                                <input type="datetime-local" name="voting_starts_at" id="voting_starts_at" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                       value="{{ old('voting_starts_at', now()->addDay()->format('Y-m-d\TH:i')) }}">
                                @error('voting_starts_at')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="voting_ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Voting Ends
                                </label>
                                <input type="datetime-local" name="voting_ends_at" id="voting_ends_at" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                       value="{{ old('voting_ends_at', now()->addDays(8)->format('Y-m-d\TH:i')) }}">
                                @error('voting_ends_at')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Voting Requirements -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="minimum_participation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Minimum Participation (%)
                                </label>
                                <input type="number" name="minimum_participation" id="minimum_participation" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                       min="1" max="100" step="0.1"
                                       value="{{ old('minimum_participation', 10) }}">
                                <p class="mt-1 text-xs text-gray-500">Minimum percentage of GCU holders that must vote</p>
                                @error('minimum_participation')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="minimum_approval" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Minimum Approval (%)
                                </label>
                                <input type="number" name="minimum_approval" id="minimum_approval" required
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                       min="1" max="100" step="0.1"
                                       value="{{ old('minimum_approval', 60) }}">
                                <p class="mt-1 text-xs text-gray-500">Minimum percentage of votes that must be "For"</p>
                                @error('minimum_approval')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                            <a href="{{ route('gcu.voting.index') }}" 
                               class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition">
                                Create Proposal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateTotal() {
            const inputs = document.querySelectorAll('input[name^="composition["]');
            let total = 0;
            
            inputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const totalElement = document.getElementById('composition-total');
            const errorElement = document.getElementById('composition-error');
            
            totalElement.textContent = total.toFixed(1) + '%';
            
            if (Math.abs(total - 100) < 0.01) {
                totalElement.classList.remove('text-red-600');
                totalElement.classList.add('text-green-600');
                errorElement.classList.add('hidden');
            } else {
                totalElement.classList.remove('text-green-600');
                totalElement.classList.add('text-red-600');
                errorElement.classList.remove('hidden');
            }
        }
        
        // Calculate initial total
        updateTotal();
    </script>
</x-app-layout>