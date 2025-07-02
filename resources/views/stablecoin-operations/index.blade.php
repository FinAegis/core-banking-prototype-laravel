<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Stablecoin Operations') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('stablecoin-operations.mint') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Mint
                    </span>
                </a>
                <a href="{{ route('stablecoin-operations.burn') }}" 
                   class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Burn
                    </span>
                </a>
                <a href="{{ route('stablecoin-operations.history') }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    History
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Operation Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Minted (24h)</p>
                    <p class="text-2xl font-bold text-green-600">
                        ${{ number_format($statistics['total_minted_24h'] / 100, 0) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Burned (24h)</p>
                    <p class="text-2xl font-bold text-red-600">
                        ${{ number_format($statistics['total_burned_24h'] / 100, 0) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Net Change</p>
                    @php
                        $netChange = $statistics['total_minted_24h'] - $statistics['total_burned_24h'];
                    @endphp
                    <p class="text-2xl font-bold {{ $netChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $netChange >= 0 ? '+' : '' }}${{ number_format(abs($netChange) / 100, 0) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Collateral Locked</p>
                    <p class="text-2xl font-bold">${{ number_format($statistics['collateral_locked'] / 100, 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Operations Today</p>
                    <p class="text-2xl font-bold">{{ $statistics['operations_today'] }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold {{ $statistics['pending_requests'] > 0 ? 'text-yellow-600' : '' }}">
                        {{ $statistics['pending_requests'] }}
                    </p>
                </div>
            </div>

            <!-- Stablecoins Overview -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Stablecoins</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Stablecoin
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Total Supply
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Collateral Ratio
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($stablecoins as $stablecoin)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <p class="font-medium">{{ $stablecoin['symbol'] }}</p>
                                                <p class="text-sm text-gray-500">{{ $stablecoin['name'] }}</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            ${{ number_format($stablecoin['total_supply'] / 100, 0) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $stablecoin['collateral_ratio'] }}%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                {{ $stablecoin['active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $stablecoin['active'] ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            @if($stablecoin['active'])
                                                <a href="{{ route('stablecoin-operations.mint', ['stablecoin' => $stablecoin['symbol']]) }}" 
                                                   class="text-green-600 hover:text-green-900 mr-3">Mint</a>
                                                <a href="{{ route('stablecoin-operations.burn', ['stablecoin' => $stablecoin['symbol']]) }}" 
                                                   class="text-red-600 hover:text-red-900">Burn</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Collateral Status -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Collateral Status</h3>
                    <div class="space-y-4">
                        @foreach($collateral as $asset => $info)
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">{{ $asset }}</span>
                                    <span class="text-sm text-gray-600">
                                        {{ number_format($info['utilization'], 1) }}% utilized
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="h-3 rounded-full {{ $info['utilization'] < 70 ? 'bg-green-600' : ($info['utilization'] < 90 ? 'bg-yellow-600' : 'bg-red-600') }}"
                                         style="width: {{ $info['utilization'] }}%"></div>
                                </div>
                                <div class="flex justify-between text-sm text-gray-600 mt-1">
                                    <span>Locked: ${{ number_format($info['locked'] / 100, 0) }}</span>
                                    <span>Available: ${{ number_format($info['available'] / 100, 0) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Operations -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Recent Operations</h3>
                        <a href="{{ route('stablecoin-operations.history') }}" 
                           class="text-indigo-600 hover:text-indigo-900 text-sm">
                            View All →
                        </a>
                    </div>
                    
                    @if($recentOperations->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Time
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Stablecoin
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Operator
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentOperations as $operation)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $operation['created_at']->diffForHumans() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    {{ $operation['type'] === 'mint' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ ucfirst($operation['type']) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $operation['stablecoin'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($operation['amount'], 0) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $operation['operator'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    {{ ucfirst($operation['status']) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">No recent operations</p>
                    @endif
                </div>
            </div>
            
            <!-- Pending Requests -->
            @if($pendingRequests->count() > 0)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-4">
                            Pending Requests ({{ $pendingRequests->count() }})
                        </h3>
                        <!-- Pending requests table would go here -->
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>