<div class="flex gap-1">
    @if(isset($getState()['circuit_breaker_metrics']))
        @foreach($getState()['circuit_breaker_metrics'] as $operation => $metrics)
            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                @if($metrics['state'] === 'closed') bg-green-100 text-green-700
                @elseif($metrics['state'] === 'half_open') bg-yellow-100 text-yellow-700
                @else bg-red-100 text-red-700
                @endif"
                title="{{ ucfirst($operation) }}: {{ $metrics['state'] }} ({{ $metrics['failure_count'] }} failures)">
                @if($operation === 'getBalance')
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @elseif($operation === 'initiateTransfer')
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                @else
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @endif
                {{ substr($operation, 0, 1) }}
            </span>
        @endforeach
    @else
        <span class="text-gray-500 text-xs">No data</span>
    @endif
</div>