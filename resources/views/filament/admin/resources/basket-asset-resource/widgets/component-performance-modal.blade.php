<div class="space-y-4">
    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Asset
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Weight
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Contribution
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Return
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($components as $component)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $component->asset_code }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $component->asset?->name ?? $component->asset_code }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                Start: {{ number_format($component->start_weight, 2) }}%
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                End: {{ number_format($component->end_weight, 2) }}%
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $component->contribution_percentage >= 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                {{ $component->formatted_contribution }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $component->return_percentage >= 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' }}">
                                {{ $component->formatted_return }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No component performance data available
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($components->isNotEmpty())
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 rounded-lg">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <p><strong>Top Contributor:</strong> 
                    {{ $components->sortByDesc('contribution_percentage')->first()->asset_code }} 
                    ({{ $components->sortByDesc('contribution_percentage')->first()->formatted_contribution }})
                </p>
                <p><strong>Worst Performer:</strong> 
                    {{ $components->sortBy('contribution_percentage')->first()->asset_code }} 
                    ({{ $components->sortBy('contribution_percentage')->first()->formatted_contribution }})
                </p>
            </div>
        </div>
    @endif
</div>