<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('CGO Investment Demo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Demo Environment Notice -->
            <div class="bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-500 rounded-lg p-6 mb-8">
                <h4 class="font-bold text-amber-800 dark:text-amber-200 mb-2 flex items-center text-lg">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Demo Interface Only
                </h4>
                <p class="text-amber-700 dark:text-amber-300">
                    This page demonstrates how a Continuous Growth Offering (CGO) investment flow would work.
                    <strong>This is not a real investment opportunity.</strong>
                    The form below is for educational purposes only and does not process actual transactions.
                </p>
            </div>

            <!-- Educational Overview -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">Understanding the CGO Model</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The Continuous Growth Offering (CGO) is a conceptual funding model designed for open-source projects.
                        Unlike traditional funding rounds with fixed valuations, a CGO creates ongoing alignment between
                        project success and investor returns.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Key Characteristics</h4>
                            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Progressive pricing tied to milestones
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Transparent round-based allocation
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Community governance participation
                                </li>
                            </ul>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Implementation Notes</h4>
                            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    Requires securities law compliance
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    KYC/AML verification needed
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 mr-2 mt-0.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    Proper entity structure required
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demo Investment Round Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sample Investment Round</h3>
                        <span class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm rounded-full font-medium">
                            Demo Data
                        </span>
                    </div>

                    @if($currentRound)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Round Number</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">#{{ $currentRound->round_number }}</p>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Share Price (Simulated)</p>
                                <p class="text-2xl font-bold text-indigo-600">${{ number_format($currentRound->share_price, 2) }}</p>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Available Shares</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($currentRound->remaining_shares, 0) }}</p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-6">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <span>Round Progress (Simulated)</span>
                                <span>{{ number_format($currentRound->progress_percentage, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ $currentRound->progress_percentage }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-600 dark:text-gray-400">No demo round data available.</p>
                    @endif
                </div>
            </div>

            <!-- Demo Investment Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Demo Investment Calculator</h3>
                        <span class="px-3 py-1 bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 text-sm rounded-full font-medium">
                            Non-Functional
                        </span>
                    </div>

                    <form id="investmentForm" onsubmit="return handleDemoSubmit(event)">
                        <!-- Investment Amount -->
                        <div class="mb-6">
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Investment Amount (USD) - Demo Only
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" name="amount" id="amount" min="100" step="0.01"
                                    class="pl-8 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="1,000.00"
                                    value="{{ old('amount') }}">
                            </div>

                            <!-- Live Calculation -->
                            <div id="shareCalculation" class="mt-3 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg hidden">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    This would represent approximately <span id="shareCount" class="font-bold text-indigo-600">0</span> shares
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                    Theoretical ownership: <span id="ownershipPercentage">0.0000%</span>
                                </p>
                            </div>
                        </div>

                        <!-- Investment Tier Display -->
                        <div id="tierDisplay" class="mb-6 p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hidden">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                Tier Example: <span id="tierName"></span>
                            </h4>
                            <ul id="tierBenefits" class="text-sm text-gray-600 dark:text-gray-400 space-y-1"></ul>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="bg-gray-400 text-white px-6 py-3 rounded-lg font-semibold cursor-not-allowed" disabled>
                                Demo Only - No Real Investment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Interested in the Project? -->
            <div class="mt-8 bg-gradient-to-r from-gray-800 to-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 text-center">
                    <h3 class="text-xl font-bold text-white mb-3">Interested in the FinAegis Project?</h3>
                    <p class="text-gray-300 mb-6 max-w-2xl mx-auto">
                        This is an open-source core banking platform. Explore the code, contribute features, or star the repository to show your support.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel"
                           target="_blank"
                           class="inline-flex items-center px-6 py-3 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                            </svg>
                            View on GitHub
                        </a>
                        <a href="{{ route('developers.index') }}"
                           class="inline-flex items-center px-6 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white/10 transition">
                            Developer Documentation
                        </a>
                    </div>
                </div>
            </div>

            <!-- Investment History (Demo) -->
            @if(isset($userInvestments) && $userInvestments->count() > 0)
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Demo Investment History</h3>
                        <span class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm rounded-full font-medium">
                            Sample Data
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shares</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($userInvestments as $investment)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $investment->created_at->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">${{ number_format($investment->amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ number_format($investment->shares_purchased, 4) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Demo
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script>
        const sharePrice = {{ $currentRound->share_price ?? 10 }};
        const totalShares = 1000000; // 1 million total shares

        document.getElementById('amount').addEventListener('input', function(e) {
            const amount = parseFloat(e.target.value) || 0;

            if (amount >= 100) {
                const shares = amount / sharePrice;
                const ownership = (shares / totalShares) * 100;

                document.getElementById('shareCount').textContent = shares.toFixed(4);
                document.getElementById('ownershipPercentage').textContent = ownership.toFixed(6) + '%';
                document.getElementById('shareCalculation').classList.remove('hidden');

                // Update tier display
                let tier = 'bronze';
                let tierBenefits = ['Digital ownership certificate', 'Early access to new features', 'Monthly investor updates'];

                if (amount >= 10000) {
                    tier = 'gold';
                    tierBenefits = [...tierBenefits, 'Physical certificate option', 'Voting rights', 'Quarterly calls', 'Direct team access', 'Advisory board consideration', 'Lifetime premium features'];
                } else if (amount >= 1000) {
                    tier = 'silver';
                    tierBenefits = [...tierBenefits, 'Physical certificate option', 'Voting rights on platform decisions', 'Quarterly investor calls'];
                }

                document.getElementById('tierName').textContent = tier.charAt(0).toUpperCase() + tier.slice(1);
                document.getElementById('tierBenefits').innerHTML = tierBenefits.map(b => `<li>• ${b}</li>`).join('');
                document.getElementById('tierDisplay').classList.remove('hidden');
            } else {
                document.getElementById('shareCalculation').classList.add('hidden');
                document.getElementById('tierDisplay').classList.add('hidden');
            }
        });

        function handleDemoSubmit(event) {
            event.preventDefault();
            alert('This is a demo interface. No real investment functionality is available.');
            return false;
        }
    </script>
</x-app-layout>
