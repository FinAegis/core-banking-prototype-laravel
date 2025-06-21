<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Bank Health Monitor
        </x-slot>
        
        <x-slot name="description">
            Real-time health status of bank connectors (Last update: {{ $lastUpdate }})
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($healthData as $custodian => $health)
                <div class="border rounded-lg p-4 
                    @if($health['status'] === 'healthy') bg-green-50 border-green-200
                    @elseif($health['status'] === 'degraded') bg-yellow-50 border-yellow-200
                    @else bg-red-50 border-red-200
                    @endif">
                    
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold">{{ ucfirst($custodian) }}</h3>
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium
                            @if($health['status'] === 'healthy') bg-green-100 text-green-700
                            @elseif($health['status'] === 'degraded') bg-yellow-100 text-yellow-700
                            @else bg-red-100 text-red-700
                            @endif">
                            {{ ucfirst($health['status']) }}
                        </span>
                    </div>
                    
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Available:</span>
                            <span class="font-medium">
                                @if($health['available'])
                                    <span class="text-green-600">Yes</span>
                                @else
                                    <span class="text-red-600">No</span>
                                @endif
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Failure Rate:</span>
                            <span class="font-medium">{{ $health['overall_failure_rate'] ?? 0 }}%</span>
                        </div>
                        
                        @if(isset($health['circuit_breaker_metrics']))
                            <div class="mt-2 pt-2 border-t">
                                <div class="text-xs text-gray-600 mb-1">Circuit Breakers:</div>
                                @foreach($health['circuit_breaker_metrics'] as $operation => $metrics)
                                    <div class="flex justify-between text-xs">
                                        <span>{{ ucfirst($operation) }}:</span>
                                        <span class="
                                            @if($metrics['state'] === 'closed') text-green-600
                                            @elseif($metrics['state'] === 'half_open') text-yellow-600
                                            @else text-red-600
                                            @endif">
                                            {{ ucfirst($metrics['state']) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        
                        @if(!empty($health['recommendations']))
                            <div class="mt-2 pt-2 border-t">
                                <div class="text-xs text-gray-600 mb-1">Recommendations:</div>
                                @foreach($health['recommendations'] as $recommendation)
                                    <div class="text-xs text-gray-700">• {{ $recommendation }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        @if(empty($healthData))
            <div class="text-center py-8 text-gray-500">
                No bank connectors configured
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>