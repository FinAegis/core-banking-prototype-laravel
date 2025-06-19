<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Bank Partner Network
        </x-slot>
        
        <x-slot name="description">
            Multi-bank fund distribution for enhanced security and deposit insurance
        </x-slot>

        <div class="mb-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Total Deposit Insurance Coverage: <span class="font-bold text-primary-600">{{ $this->getTotalInsuranceCoverage() }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left p-2">Bank</th>
                        <th class="text-left p-2">Country</th>
                        <th class="text-left p-2">Type</th>
                        <th class="text-center p-2">Active Users</th>
                        <th class="text-center p-2">Avg. Allocation</th>
                        <th class="text-right p-2">Insurance</th>
                        <th class="text-left p-2">Features</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->getBankDistribution() as $bank)
                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-2 font-medium">{{ $bank['bank_name'] }}</td>
                            <td class="p-2">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-300">
                                    {{ $bank['country'] }}
                                </span>
                            </td>
                            <td class="p-2">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded
                                    @if($bank['type'] === 'Emi')
                                        text-blue-700 bg-blue-100 dark:bg-blue-900 dark:text-blue-300
                                    @else
                                        text-green-700 bg-green-100 dark:bg-green-900 dark:text-green-300
                                    @endif">
                                    {{ $bank['type'] }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                @if($bank['user_count'] > 0)
                                    <span class="font-medium">{{ $bank['user_count'] }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-2 text-center">
                                @if($bank['user_count'] > 0)
                                    <span class="font-medium">{{ $bank['avg_allocation'] }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-2 text-right font-medium">{{ $bank['deposit_insurance'] }}</td>
                            <td class="p-2 text-xs text-gray-600 dark:text-gray-400">{{ $bank['features'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            <p>Each bank provides government-backed deposit insurance protection. Diversifying funds across multiple banks maximizes coverage.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>