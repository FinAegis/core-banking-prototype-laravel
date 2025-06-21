<div class="space-y-6">
    @if($report['discrepancies_found'] > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-red-900 mb-2">
                {{ $report['discrepancies_found'] }} Discrepancies Found
            </h3>
            <p class="text-red-700">
                Total discrepancy amount: ${{ number_format($report['total_discrepancy_amount'] / 100, 2) }}
            </p>
        </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-green-900">
                ✓ All Balances Reconciled Successfully
            </h3>
        </div>
    @endif
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="font-semibold text-gray-700">Report Date</h4>
            <p>{{ $report['date'] }}</p>
        </div>
        <div>
            <h4 class="font-semibold text-gray-700">Accounts Checked</h4>
            <p>{{ $report['accounts_checked'] }}</p>
        </div>
        <div>
            <h4 class="font-semibold text-gray-700">Start Time</h4>
            <p>{{ $report['start_time'] }}</p>
        </div>
        <div>
            <h4 class="font-semibold text-gray-700">Duration</h4>
            <p>{{ $report['duration_minutes'] ?? 0 }} minutes</p>
        </div>
    </div>
    
    @if(!empty($report['discrepancies']))
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Discrepancy Details</h3>
            
            <div class="space-y-4">
                @foreach($report['discrepancies'] as $discrepancy)
                    <div class="border rounded-lg p-4 
                        @if($discrepancy['type'] === 'balance_mismatch') bg-red-50 border-red-200
                        @else bg-yellow-50 border-yellow-200
                        @endif">
                        
                        <h4 class="font-semibold mb-2">
                            {{ ucwords(str_replace('_', ' ', $discrepancy['type'])) }}
                        </h4>
                        
                        <div class="text-sm space-y-1">
                            <p><span class="font-medium">Account:</span> {{ $discrepancy['account_uuid'] }}</p>
                            
                            @if($discrepancy['type'] === 'balance_mismatch')
                                <p><span class="font-medium">Asset:</span> {{ $discrepancy['asset_code'] }}</p>
                                <p><span class="font-medium">Internal Balance:</span> ${{ number_format($discrepancy['internal_balance'] / 100, 2) }}</p>
                                <p><span class="font-medium">External Balance:</span> ${{ number_format($discrepancy['external_balance'] / 100, 2) }}</p>
                                <p><span class="font-medium">Difference:</span> ${{ number_format($discrepancy['difference'] / 100, 2) }}</p>
                            @elseif($discrepancy['type'] === 'stale_data')
                                <p><span class="font-medium">Custodian:</span> {{ $discrepancy['custodian_id'] }}</p>
                                <p><span class="font-medium">Last Synced:</span> {{ $discrepancy['last_synced_at'] }}</p>
                            @else
                                <p>{{ $discrepancy['message'] ?? '' }}</p>
                            @endif
                            
                            <p class="text-xs text-gray-600 mt-2">
                                Detected at: {{ $discrepancy['detected_at'] }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    
    @if(!empty($report['recommendations']))
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Recommendations</h3>
            <ul class="list-disc list-inside space-y-1">
                @foreach($report['recommendations'] as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>