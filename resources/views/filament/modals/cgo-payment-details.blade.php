<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-sm font-medium text-gray-500">Investment ID</h4>
            <p class="mt-1 text-sm text-gray-900">{{ $investment->uuid }}</p>
        </div>
        
        <div>
            <h4 class="text-sm font-medium text-gray-500">Status</h4>
            <p class="mt-1">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $investment->payment_status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                       ($investment->payment_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                       ($investment->payment_status === 'completed' ? 'bg-green-100 text-green-800' : 
                       'bg-red-100 text-red-800')) }}">
                    {{ ucfirst($investment->payment_status) }}
                </span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-sm font-medium text-gray-500">Investor</h4>
            <p class="mt-1 text-sm text-gray-900">{{ $investment->user->name }}</p>
            <p class="text-xs text-gray-500">{{ $investment->user->email }}</p>
        </div>
        
        <div>
            <h4 class="text-sm font-medium text-gray-500">Amount</h4>
            <p class="mt-1 text-lg font-semibold text-gray-900">${{ number_format($investment->amount / 100, 2) }}</p>
            <p class="text-xs text-gray-500">{{ ucfirst($investment->tier) }} tier</p>
        </div>
    </div>

    <div class="border-t pt-4">
        <h4 class="text-sm font-medium text-gray-500 mb-2">Payment Information</h4>
        
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Method:</dt>
                <dd class="text-sm text-gray-900">
                    {{ match($investment->payment_method) {
                        'stripe' => 'Credit/Debit Card',
                        'crypto' => 'Cryptocurrency',
                        'bank_transfer' => 'Bank Transfer',
                        default => ucfirst($investment->payment_method)
                    } }}
                </dd>
            </div>
            
            @if($investment->payment_method === 'stripe' && $investment->stripe_payment_intent_id)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Stripe Payment Intent:</dt>
                    <dd class="text-sm text-gray-900 font-mono">{{ $investment->stripe_payment_intent_id }}</dd>
                </div>
            @endif
            
            @if($investment->payment_method === 'crypto')
                @if($investment->coinbase_charge_id)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Coinbase Charge ID:</dt>
                        <dd class="text-sm text-gray-900 font-mono">{{ $investment->coinbase_charge_id }}</dd>
                    </div>
                @endif
                @if($investment->coinbase_charge_code)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Charge Code:</dt>
                        <dd class="text-sm text-gray-900 font-mono">{{ $investment->coinbase_charge_code }}</dd>
                    </div>
                @endif
                @if($investment->crypto_address)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Crypto Address:</dt>
                        <dd class="text-sm text-gray-900 font-mono text-xs">{{ $investment->crypto_address }}</dd>
                    </div>
                @endif
            @endif
            
            @if($investment->payment_method === 'bank_transfer' && $investment->bank_transfer_reference)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Bank Reference:</dt>
                    <dd class="text-sm text-gray-900 font-mono">{{ $investment->bank_transfer_reference }}</dd>
                </div>
            @endif
            
            @if($investment->amount_paid)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Amount Paid:</dt>
                    <dd class="text-sm text-gray-900">${{ number_format($investment->amount_paid / 100, 2) }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="border-t pt-4">
        <h4 class="text-sm font-medium text-gray-500 mb-2">Timeline</h4>
        
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Created:</dt>
                <dd class="text-sm text-gray-900">{{ $investment->created_at->format('M d, Y H:i') }}</dd>
            </div>
            
            @if($investment->payment_pending_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Payment Pending:</dt>
                    <dd class="text-sm text-gray-900">{{ $investment->payment_pending_at->format('M d, Y H:i') }}</dd>
                </div>
            @endif
            
            @if($investment->payment_completed_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Payment Completed:</dt>
                    <dd class="text-sm text-gray-900">{{ $investment->payment_completed_at->format('M d, Y H:i') }}</dd>
                </div>
            @endif
            
            @if($investment->payment_failed_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Payment Failed:</dt>
                    <dd class="text-sm text-gray-900">{{ $investment->payment_failed_at->format('M d, Y H:i') }}</dd>
                </div>
            @endif
        </dl>
        
        @if($investment->payment_failure_reason)
            <div class="mt-3">
                <h4 class="text-sm font-medium text-gray-500">Failure Reason</h4>
                <p class="mt-1 text-sm text-red-600">{{ $investment->payment_failure_reason }}</p>
            </div>
        @endif
    </div>

    @if($investment->kyc_verified_at)
        <div class="border-t pt-4">
            <h4 class="text-sm font-medium text-gray-500 mb-2">KYC Information</h4>
            
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">KYC Level:</dt>
                    <dd class="text-sm text-gray-900">{{ ucfirst($investment->kyc_level ?? 'Not verified') }}</dd>
                </div>
                
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Verified At:</dt>
                    <dd class="text-sm text-gray-900">{{ $investment->kyc_verified_at->format('M d, Y H:i') }}</dd>
                </div>
                
                @if($investment->risk_assessment)
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Risk Score:</dt>
                        <dd class="text-sm text-gray-900">{{ $investment->risk_assessment }}/100</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    @if($investment->metadata && isset($investment->metadata['manual_verification']))
        <div class="border-t pt-4">
            <h4 class="text-sm font-medium text-gray-500 mb-2">Manual Verification</h4>
            
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Verified By:</dt>
                    <dd class="text-sm text-gray-900">User #{{ $investment->metadata['manual_verification']['verified_by'] }}</dd>
                </div>
                
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">Verified At:</dt>
                    <dd class="text-sm text-gray-900">
                        {{ \Carbon\Carbon::parse($investment->metadata['manual_verification']['verified_at'])->format('M d, Y H:i') }}
                    </dd>
                </div>
                
                @if(!empty($investment->metadata['manual_verification']['notes']))
                    <div>
                        <dt class="text-sm text-gray-500">Notes:</dt>
                        <dd class="text-sm text-gray-900 mt-1">{{ $investment->metadata['manual_verification']['notes'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif
</div>