<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payment Verification') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($investments->isEmpty())
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No pending payments</h3>
                        <p class="mt-1 text-sm text-gray-500">All your investments have been processed.</p>
                        <div class="mt-6">
                            <a href="{{ route('cgo.investments') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                View All Investments
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="space-y-6">
                    @foreach($investments as $investment)
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg" id="investment-{{ $investment->id }}">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">
                                            Investment #{{ $investment->uuid }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            Created {{ $investment->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-gray-900">
                                            ${{ number_format($investment->amount / 100, 2) }}
                                        </p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($investment->payment_status === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($investment->payment_status === 'processing') bg-blue-100 text-blue-800
                                            @endif">
                                            {{ ucfirst($investment->payment_status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="px-6 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Payment Method Information -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 mb-3">Payment Information</h4>
                                        
                                        @if($investment->payment_method === 'bank_transfer')
                                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h3 class="text-sm font-medium text-blue-800">Bank Transfer</h3>
                                                        <div class="mt-2 text-sm text-blue-700">
                                                            <p>Please transfer the amount to:</p>
                                                            <dl class="mt-2 space-y-1">
                                                                <div>
                                                                    <dt class="inline font-medium">Bank:</dt>
                                                                    <dd class="inline">{{ config('cgo.bank_name', 'Example Bank') }}</dd>
                                                                </div>
                                                                <div>
                                                                    <dt class="inline font-medium">Account:</dt>
                                                                    <dd class="inline">{{ config('cgo.bank_account', '1234567890') }}</dd>
                                                                </div>
                                                                <div>
                                                                    <dt class="inline font-medium">Reference:</dt>
                                                                    <dd class="inline font-mono">{{ $investment->uuid }}</dd>
                                                                </div>
                                                            </dl>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($investment->payment_method === 'crypto')
                                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h3 class="text-sm font-medium text-orange-800">Cryptocurrency Payment</h3>
                                                        <div class="mt-2 text-sm text-orange-700">
                                                            @if($investment->crypto_payment_url)
                                                                <p>Complete your payment using the link below:</p>
                                                                <a href="{{ $investment->crypto_payment_url }}" target="_blank" class="mt-2 inline-flex items-center text-orange-600 hover:text-orange-500">
                                                                    Open Payment Page
                                                                    <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                                    </svg>
                                                                </a>
                                                            @else
                                                                <p>Payment details are being generated...</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($investment->payment_method === 'stripe')
                                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h3 class="text-sm font-medium text-purple-800">Card Payment</h3>
                                                        <div class="mt-2 text-sm text-purple-700">
                                                            <p>Processing your card payment...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Payment Timeline -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 mb-3">Timeline</h4>
                                        <div class="flow-root">
                                            <ul role="list" class="-mb-8" id="timeline-{{ $investment->id }}">
                                                <li>
                                                    <div class="relative pb-8">
                                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                                                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                                <div>
                                                                    <p class="text-sm text-gray-500">Investment initiated</p>
                                                                </div>
                                                                <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                                    {{ $investment->created_at->format('M d, H:i') }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-6 flex items-center justify-between">
                                    <div class="flex space-x-3">
                                        <button type="button" onclick="checkPaymentStatus({{ $investment->id }})" 
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Check Status
                                        </button>
                                        
                                        @if(in_array($investment->payment_method, ['bank_transfer', 'crypto']))
                                            <button type="button" onclick="resendInstructions({{ $investment->id }})"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                                Resend Instructions
                                            </button>
                                        @endif
                                    </div>
                                    
                                    <div class="text-sm text-gray-500">
                                        <span id="status-{{ $investment->id }}">
                                            Last checked: Never
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Help Section -->
                <div class="mt-8 bg-gray-50 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">Need Help?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            If you're experiencing issues with your payment or have questions about the verification process, please contact our support team.
                        </p>
                        <div class="mt-4">
                            <a href="{{ route('support.contact') }}" class="text-indigo-600 hover:text-indigo-500">
                                Contact Support →
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        let checking = {};
        
        function checkPaymentStatus(investmentId) {
            if (checking[investmentId]) return;
            
            checking[investmentId] = true;
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Checking...';
            
            fetch(`/cgo/payment-verification/${investmentId}/check`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Payment verified!
                    showNotification('success', data.message);
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                } else {
                    showNotification('info', data.message);
                    document.getElementById(`status-${investmentId}`).textContent = `Last checked: ${new Date().toLocaleTimeString()}`;
                }
            })
            .catch(error => {
                showNotification('error', 'Unable to check payment status');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
                checking[investmentId] = false;
            });
            
            // Also update timeline
            updateTimeline(investmentId);
        }
        
        function resendInstructions(investmentId) {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...';
            
            fetch(`/cgo/payment-verification/${investmentId}/resend`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                showNotification('error', 'Unable to send instructions');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
        
        function updateTimeline(investmentId) {
            fetch(`/cgo/payment-verification/${investmentId}/timeline`)
                .then(response => response.json())
                .then(timeline => {
                    const container = document.getElementById(`timeline-${investmentId}`);
                    if (!container) return;
                    
                    container.innerHTML = timeline.map((event, index) => `
                        <li>
                            <div class="relative pb-8">
                                ${index < timeline.length - 1 ? '<span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>' : ''}
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-${event.color}-400 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <!-- Icon would be dynamically loaded based on event.icon -->
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-500">${event.event}</p>
                                            <p class="text-xs text-gray-400">${event.description}</p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            ${new Date(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    `).join('');
                });
        }
        
        function showNotification(type, message) {
            // You can integrate with your notification system here
            // For now, using a simple alert
            const colors = {
                success: 'green',
                error: 'red',
                info: 'blue'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden z-50`;
            notification.innerHTML = `
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-${colors[type]}-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                ${type === 'success' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />' : 
                                  type === 'error' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' :
                                  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'}
                            </svg>
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm font-medium text-gray-900">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
        
        // Auto-check status every 30 seconds for pending payments
        @if($investments->isNotEmpty())
        setInterval(() => {
            @foreach($investments as $investment)
                @if($investment->payment_method !== 'bank_transfer')
                    if (!checking[{{ $investment->id }}]) {
                        checkPaymentStatus({{ $investment->id }});
                    }
                @endif
            @endforeach
        }, 30000);
        @endif
    </script>
    @endpush
</x-app-layout>