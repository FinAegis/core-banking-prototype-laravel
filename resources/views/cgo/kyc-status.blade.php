<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CGO KYC Verification') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->has('kyc_required'))
                <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                {{ $errors->first('kyc_required') }}
                            </p>
                            @if ($errors->has('investment_id'))
                                <p class="text-xs text-yellow-600 mt-1">
                                    Investment ID: {{ $errors->first('investment_id') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Your KYC Status</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Current Status -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Current Status</h4>
                            <div class="mt-2">
                                @if (auth()->user()->kyc_status === 'approved')
                                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        Verified
                                    </span>
                                @elseif (auth()->user()->kyc_status === 'pending')
                                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        Pending Review
                                    </span>
                                @elseif (auth()->user()->kyc_status === 'rejected')
                                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                        Not Verified
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- KYC Level -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">KYC Level</h4>
                            <div class="mt-2">
                                <span class="text-lg font-semibold text-gray-900">
                                    {{ ucfirst(auth()->user()->kyc_level ?? 'None') }}
                                </span>
                            </div>
                        </div>

                        <!-- Investment Limits -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Invested</h4>
                            <div class="mt-2">
                                <span class="text-lg font-semibold text-gray-900">
                                    ${{ number_format($totalInvested ?? 0, 2) }}
                                </span>
                            </div>
                        </div>

                        <!-- Available Limit -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Available Limit</h4>
                            <div class="mt-2">
                                <span class="text-lg font-semibold text-gray-900">
                                    @if ($availableLimit === null)
                                        Unlimited
                                    @else
                                        ${{ number_format($availableLimit, 2) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents -->
                    @if (!empty($requiredDocuments))
                        <div class="mt-8">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Required Documents</h4>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <p class="text-sm text-yellow-700 mb-2">
                                    Please submit the following documents to complete your KYC verification:
                                </p>
                                <ul class="list-disc list-inside text-sm text-yellow-700">
                                    @foreach ($requiredDocuments as $doc)
                                        <li>{{ ucwords(str_replace('_', ' ', $doc)) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <!-- Upload Documents Form -->
                    @if (auth()->user()->kyc_status !== 'approved' || auth()->user()->kyc_status === 'expired')
                        <div class="mt-8">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Submit KYC Documents</h4>
                            
                            <form action="{{ route('cgo.kyc.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                
                                @if (session('investment_id'))
                                    <input type="hidden" name="investment_id" value="{{ session('investment_id') }}">
                                @endif

                                <div id="document-fields">
                                    <div class="document-field border rounded-lg p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Document Type</label>
                                                <select name="documents[0][type]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                                                    <option value="">Select document type</option>
                                                    <option value="passport">Passport</option>
                                                    <option value="driving_license">Driving License</option>
                                                    <option value="national_id">National ID Card</option>
                                                    <option value="utility_bill">Utility Bill</option>
                                                    <option value="bank_statement">Bank Statement</option>
                                                    <option value="selfie">Selfie with ID</option>
                                                    <option value="proof_of_income">Proof of Income</option>
                                                    <option value="source_of_funds">Source of Funds</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Document File</label>
                                                <input type="file" name="documents[0][file]" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full" required>
                                                <p class="text-xs text-gray-500 mt-1">JPG, JPEG, PNG, or PDF. Max 10MB.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between">
                                    <button type="button" onclick="addDocumentField()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Add Another Document
                                    </button>
                                    
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Submit Documents
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <!-- Existing Documents -->
                    @if (!empty($documents) && count($documents) > 0)
                        <div class="mt-8">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Submitted Documents</h4>
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Document Type
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Uploaded
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Expires
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($documents as $doc)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $doc['type_label'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if ($doc['status'] === 'verified')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            Verified
                                                        </span>
                                                    @elseif ($doc['status'] === 'pending')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            Pending
                                                        </span>
                                                    @elseif ($doc['status'] === 'rejected')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Rejected
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $doc['uploaded_at']->format('M d, Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if ($doc['expires_at'])
                                                        {{ $doc['expires_at']->format('M d, Y') }}
                                                        @if ($doc['is_expired'])
                                                            <span class="text-red-600 text-xs">(Expired)</span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        let documentCount = 1;
        
        function addDocumentField() {
            const container = document.getElementById('document-fields');
            const newField = document.createElement('div');
            newField.className = 'document-field border rounded-lg p-4 mt-4';
            newField.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Document Type</label>
                        <select name="documents[${documentCount}][type]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                            <option value="">Select document type</option>
                            <option value="passport">Passport</option>
                            <option value="driving_license">Driving License</option>
                            <option value="national_id">National ID Card</option>
                            <option value="utility_bill">Utility Bill</option>
                            <option value="bank_statement">Bank Statement</option>
                            <option value="selfie">Selfie with ID</option>
                            <option value="proof_of_income">Proof of Income</option>
                            <option value="source_of_funds">Source of Funds</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Document File</label>
                        <input type="file" name="documents[${documentCount}][file]" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full" required>
                        <p class="text-xs text-gray-500 mt-1">JPG, JPEG, PNG, or PDF. Max 10MB.</p>
                    </div>
                </div>
            `;
            container.appendChild(newField);
            documentCount++;
        }
    </script>
</x-app-layout>