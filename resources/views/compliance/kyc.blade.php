<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('KYC Verification') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="flex items-center mb-6">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Verify Your Identity
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                This helps us keep your account secure and comply with regulations
                            </p>
                        </div>
                    </div>

                    @if(auth()->user()->kyc_status === 'approved')
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-800 dark:text-green-200">
                                        Your identity has been verified. You have full access to all features.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @elseif(auth()->user()->kyc_status === 'pending' || auth()->user()->kyc_status === 'in_review')
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                        Your documents are being reviewed. This usually takes 1-2 business days.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="space-y-6">
                            <!-- KYC Level Selection -->
                            <div>
                                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Choose Your Verification Level
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div id="kyc-basic" onclick="selectKycLevel('basic')" class="kyc-level-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 cursor-pointer transition-all">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Basic</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Up to $10,000 daily limit</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• National ID</li>
                                            <li>• Selfie verification</li>
                                        </ul>
                                    </div>
                                    
                                    <div id="kyc-enhanced" onclick="selectKycLevel('enhanced')" class="kyc-level-card border-2 border-indigo-500 rounded-lg p-4 cursor-pointer transition-all">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Enhanced</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Up to $50,000 daily limit</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• Passport</li>
                                            <li>• Proof of address</li>
                                            <li>• Selfie verification</li>
                                        </ul>
                                    </div>
                                    
                                    <div id="kyc-full" onclick="selectKycLevel('full')" class="kyc-level-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 cursor-pointer transition-all">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Full</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No limits</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• All Enhanced docs</li>
                                            <li>• Bank statement</li>
                                            <li>• Income proof</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Upload -->
                            <div>
                                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Upload Your Documents
                                </h4>
                                <div class="space-y-4">
                                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <input type="file" id="kyc-file-upload" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileUpload(event)">
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <button type="button" onclick="document.getElementById('kyc-file-upload').click()" class="font-medium text-indigo-600 hover:text-indigo-500">
                                                Upload a file
                                            </button>
                                            or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            PNG, JPG, PDF up to 10MB
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Uploaded Files List -->
                                <div id="uploaded-files-list" class="mt-4 hidden">
                                    <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Uploaded Documents</h5>
                                    <ul id="files-list" class="space-y-2">
                                        <!-- Files will be added here dynamically -->
                                    </ul>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="button" onclick="skipKyc()" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-3">
                                    Skip for Now
                                </button>
                                <button type="button" onclick="submitKyc()" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                    Submit for Verification
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- KYC JavaScript -->
    <script>
        let selectedKycLevel = 'enhanced';
        let uploadedFiles = [];

        function selectKycLevel(level) {
            selectedKycLevel = level;
            
            // Reset all cards
            document.querySelectorAll('.kyc-level-card').forEach(card => {
                card.classList.remove('border-2', 'border-indigo-500');
                card.classList.add('border', 'border-gray-200', 'dark:border-gray-700');
            });
            
            // Highlight selected card
            const selectedCard = document.getElementById('kyc-' + level);
            selectedCard.classList.remove('border', 'border-gray-200', 'dark:border-gray-700');
            selectedCard.classList.add('border-2', 'border-indigo-500');
        }

        function handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size must be less than 10MB');
                    return;
                }
                
                uploadedFiles.push(file);
                displayUploadedFile(file);
                
                // Reset file input
                event.target.value = '';
            }
        }
        
        function displayUploadedFile(file) {
            const filesList = document.getElementById('files-list');
            const uploadedFilesContainer = document.getElementById('uploaded-files-list');
            
            // Show the container
            uploadedFilesContainer.classList.remove('hidden');
            
            // Create file item
            const fileItem = document.createElement('li');
            fileItem.className = 'flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg';
            fileItem.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${file.name}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                </div>
                <button onclick="removeFile('${file.name}')" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            `;
            
            filesList.appendChild(fileItem);
        }
        
        function removeFile(fileName) {
            uploadedFiles = uploadedFiles.filter(file => file.name !== fileName);
            
            // Rebuild the file list
            const filesList = document.getElementById('files-list');
            filesList.innerHTML = '';
            
            if (uploadedFiles.length === 0) {
                document.getElementById('uploaded-files-list').classList.add('hidden');
            } else {
                uploadedFiles.forEach(file => displayUploadedFile(file));
            }
        }

        function skipKyc() {
            if (confirm('Are you sure you want to skip KYC verification? You will have limited functionality.')) {
                window.location.href = '/dashboard';
            }
        }

        function submitKyc() {
            if (uploadedFiles.length === 0) {
                alert('Please upload at least one document to proceed with KYC verification.');
                return;
            }
            
            // In production, this would submit to the KYC API
            alert('KYC documents submitted for ' + selectedKycLevel + ' verification. You will be notified once verification is complete.');
            
            // For demo, mark KYC as pending
            fetch('/api/compliance/kyc/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    level: selectedKycLevel,
                    documents: uploadedFiles.map(f => f.name)
                })
            })
            .then(response => {
                if (response.ok) {
                    window.location.href = '/dashboard';
                } else {
                    // If API doesn't exist yet, just redirect
                    window.location.href = '/dashboard';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Redirect anyway for demo
                window.location.href = '/dashboard';
            });
        }

        // Drag and drop functionality
        const dropZone = document.querySelector('.border-dashed');
        if (dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/10');
            });
            
            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/10');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/10');
                
                const files = e.dataTransfer.files;
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size <= 10 * 1024 * 1024) {
                        uploadedFiles.push(file);
                        displayUploadedFile(file);
                    } else {
                        alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    }
                }
            });
        }
    </script>
</x-app-layout>