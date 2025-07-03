<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My CGO Investments') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($investments->isEmpty())
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <p class="text-gray-500 text-center">You have not made any investments yet.</p>
                    <div class="text-center mt-4">
                        <a href="{{ route('cgo.invest') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            Make Your First Investment
                        </a>
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Your Investments</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Shares
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tier
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Documents
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($investments as $investment)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $investment->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $investment->currency }} {{ number_format($investment->amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($investment->shares_purchased, 4) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $investment->tier_color }}-100 text-{{ $investment->tier_color }}-800">
                                                {{ ucfirst($investment->tier) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $investment->status_color }}-100 text-{{ $investment->status_color }}-800">
                                                {{ ucfirst(str_replace('_', ' ', $investment->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <!-- Agreement Button -->
                                                @if($investment->status !== 'cancelled' && $investment->status !== 'refunded')
                                                    @if($investment->agreement_path)
                                                        <a href="{{ route('cgo.agreement.download', $investment->uuid) }}" class="text-indigo-600 hover:text-indigo-900">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            <span class="sr-only">Download Agreement</span>
                                                        </a>
                                                    @else
                                                        <button onclick="generateAgreement('{{ $investment->uuid }}')" class="text-gray-600 hover:text-gray-900">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            <span class="sr-only">Generate Agreement</span>
                                                        </button>
                                                    @endif
                                                @endif
                                                
                                                <!-- Certificate Button -->
                                                @if($investment->status === 'confirmed')
                                                    @if($investment->certificate_path)
                                                        <a href="{{ route('cgo.certificate.download', $investment->uuid) }}" class="text-green-600 hover:text-green-900">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                                            </svg>
                                                            <span class="sr-only">Download Certificate</span>
                                                        </a>
                                                    @else
                                                        <button onclick="generateCertificate('{{ $investment->uuid }}')" class="text-gray-600 hover:text-gray-900">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                                            </svg>
                                                            <span class="sr-only">Generate Certificate</span>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $investments->links() }}
                        </div>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Invested</h4>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['currency'] }} {{ number_format($summary['total_invested'], 2) }}</p>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Shares</h4>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($summary['total_shares'], 4) }}</p>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Ownership %</h4>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($summary['total_ownership'], 6) }}%</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function generateAgreement(investmentUuid) {
            if (!confirm('Generate investment agreement?')) return;
            
            fetch(`/cgo/agreement/${investmentUuid}/generate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Agreement generated successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error generating agreement');
                console.error(error);
            });
        }
        
        function generateCertificate(investmentUuid) {
            if (!confirm('Generate investment certificate?')) return;
            
            fetch(`/cgo/certificate/${investmentUuid}/generate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Certificate generated successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error generating certificate');
                console.error(error);
            });
        }
    </script>
</x-app-layout>