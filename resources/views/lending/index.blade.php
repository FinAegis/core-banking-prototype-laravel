<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Lending Platform') }}
            </h2>
            <a href="{{ route('lending.apply') }}" 
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                Apply for Loan
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Credit Score Card -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Your Credit Score</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-5xl font-bold">{{ $creditScore['score'] }}</span>
                            <span class="text-xl">{{ $creditScore['rating'] }}</span>
                        </div>
                        <p class="mt-2 text-sm opacity-90">
                            Last updated: {{ $creditScore['last_updated']->diffForHumans() }}
                        </p>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold mb-3">Score Factors</h4>
                        <div class="space-y-2">
                            @foreach($creditScore['factors'] as $factor => $score)
                                <div>
                                    <div class="flex justify-between text-xs">
                                        <span>{{ ucwords(str_replace('_', ' ', $factor)) }}</span>
                                        <span>{{ $score }}%</span>
                                    </div>
                                    <div class="w-full bg-white/20 rounded-full h-2">
                                        <div class="bg-white h-2 rounded-full" style="width: {{ $score }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Active Loans</p>
                    <p class="text-2xl font-bold">{{ $statistics['active_loans'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        of {{ $statistics['total_loans'] }} total
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Outstanding Balance</p>
                    <p class="text-2xl font-bold">${{ number_format($statistics['outstanding_balance'], 2) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Repaid</p>
                    <p class="text-2xl font-bold">${{ number_format($statistics['total_repaid'], 2) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">On-Time Payments</p>
                    <p class="text-2xl font-bold text-green-600">{{ $statistics['on_time_payments'] }}%</p>
                </div>
            </div>

            <!-- Active Loans -->
            @if($loans->where('status', 'active')->count() > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Active Loans</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Loan ID
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Outstanding
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Next Payment
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
                                    @foreach($loans->where('status', 'active') as $loan)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ substr($loan->loan_uuid, 0, 8) }}...
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($loan->principal_amount, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($loan->outstanding_balance, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @php
                                                    $nextPayment = collect($loan->repayment_schedule)
                                                        ->where('status', 'pending')
                                                        ->sortBy('due_date')
                                                        ->first();
                                                @endphp
                                                @if($nextPayment)
                                                    ${{ number_format($nextPayment['amount'], 2) }}
                                                    <br>
                                                    <span class="text-xs text-gray-500">
                                                        {{ \Carbon\Carbon::parse($nextPayment['due_date'])->format('M d, Y') }}
                                                    </span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('lending.loan', $loan->loan_uuid) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                                <a href="{{ route('lending.repay', $loan->loan_uuid) }}" 
                                                   class="text-green-600 hover:text-green-900">Repay</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Available Loan Products -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Available Loan Products</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($loanProducts as $product)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-lg mb-2">{{ $product['name'] }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    {{ $product['description'] }}
                                </p>
                                
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Amount</span>
                                        <span>${{ number_format($product['min_amount'], 0) }} - ${{ number_format($product['max_amount'], 0) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Term</span>
                                        <span>{{ $product['min_term'] }} - {{ $product['max_term'] }} months</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Interest Rate</span>
                                        <span class="font-medium">{{ $product['interest_rate'] }}% APR</span>
                                    </div>
                                    @if($product['collateral_required'])
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Collateral</span>
                                            <span class="text-amber-600">Required</span>
                                        </div>
                                        @if(isset($product['ltv_ratio']))
                                            <div class="flex justify-between">
                                                <span class="text-gray-600 dark:text-gray-400">LTV Ratio</span>
                                                <span>{{ $product['ltv_ratio'] }}%</span>
                                            </div>
                                        @endif
                                    @else
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Collateral</span>
                                            <span class="text-green-600">Not Required</span>
                                        </div>
                                    @endif
                                </div>
                                
                                <a href="{{ route('lending.apply', ['product' => $product['id']]) }}" 
                                   class="mt-4 block text-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                    Apply Now
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Loan History -->
            @if($loans->count() > 0)
                <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Loan History</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Term
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Interest Rate
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($loans as $loan)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $loan->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($loan->principal_amount, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $loan->term_months }} months
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $loan->interest_rate }}%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($loan->status === 'active')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Active
                                                    </span>
                                                @elseif($loan->status === 'paid_off')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        Paid Off
                                                    </span>
                                                @elseif($loan->status === 'defaulted')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Defaulted
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        {{ ucfirst($loan->status) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('lending.loan', $loan->loan_uuid) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">View Details</a>
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
</x-app-layout>