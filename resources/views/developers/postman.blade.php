<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-orange-600 to-red-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        Postman Collection
                    </h1>
                    <p class="mt-6 text-xl text-orange-100 max-w-3xl mx-auto">
                        Ready-to-use Postman collection with all FinAegis API endpoints pre-configured for testing.
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            
            <!-- Quick Download Section -->
            <div class="text-center mb-16">
                <div class="bg-gradient-to-r from-orange-50 to-red-50 rounded-lg p-8 mb-8">
                    <div class="flex items-center justify-center mb-6">
                        <div class="w-20 h-20 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-10 h-10 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13.527.099C6.955-.744.942 3.9.099 10.473c-.843 6.572 3.8 12.584 10.373 13.428 6.573.843 12.587-3.801 13.428-10.374C24.744 6.955 20.101.943 13.527.099zM15.7 5.35c.146-.573.5-1.09 1.025-1.493a4.33 4.33 0 011.743-.635c.506-.097 1.021-.044 1.492.152a3.233 3.233 0 011.18.73c.323.323.573.717.725 1.14.07.195.107.4.107.608 0 .208-.037.413-.107.608a3.233 3.233 0 01-.725 1.14c-.323.323-.717.573-1.14.725a3.233 3.233 0 01-1.18.107c-.506-.097-1.021-.044-1.492.152a4.33 4.33 0 00-1.743.635c-.525.403-.879.92-1.025 1.493L12.56 10.62a1.666 1.666 0 00-.107-.608c0-.208.037-.413.107-.608.152-.423.402-.817.725-1.14.323-.323.717-.573 1.14-.725.195-.07.4-.107.608-.107.208 0 .413.037.608.107.423.152.817.402 1.14.725.323.323.573.717.725 1.14.07.195.107.4.107.608 0 .208-.037.413-.107.608-.152.423-.402.817-.725 1.14-.323.323-.717.573-1.14.725-.195.07-.4.107-.608.107-.208 0-.413-.037-.608-.107a3.233 3.233 0 01-1.14-.725 3.233 3.233 0 01-.725-1.14z"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Download Postman Collection</h2>
                    <p class="text-lg text-gray-600 mb-8">Get started immediately with our comprehensive API collection including examples and test scripts.</p>
                    
                    <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="/docs/postman/FinAegis-API.postman_collection.json" 
                           download="FinAegis-API.postman_collection.json"
                           class="bg-orange-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-orange-700 transition duration-200">
                            Download Collection
                        </a>
                        <a href="/docs/postman/FinAegis-Environment.postman_environment.json" 
                           download="FinAegis-Environment.postman_environment.json"
                           class="bg-white text-orange-600 border-2 border-orange-600 px-8 py-3 rounded-lg font-semibold hover:bg-orange-50 transition duration-200">
                            Download Environment
                        </a>
                    </div>
                </div>
            </div>

            <!-- Installation Guide -->
            <section class="mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Installation Guide</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <div class="border rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 font-bold">1</span>
                                </div>
                                <h3 class="text-xl font-semibold">Import Collection</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Open Postman and import the downloaded collection file.</p>
                            <div class="bg-gray-100 rounded p-3 text-sm font-mono">
                                File → Import → Select FinAegis-API.postman_collection.json
                            </div>
                        </div>

                        <div class="border rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 font-bold">2</span>
                                </div>
                                <h3 class="text-xl font-semibold">Setup Environment</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Import the environment file and configure your API key.</p>
                            <div class="bg-gray-100 rounded p-3 text-sm font-mono">
                                Environments → Import → Select FinAegis-Environment.postman_environment.json
                            </div>
                        </div>

                        <div class="border rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600 font-bold">3</span>
                                </div>
                                <h3 class="text-xl font-semibold">Configure API Key</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Set your API key in the environment variables.</p>
                            <div class="bg-gray-100 rounded p-3 text-sm font-mono">
                                Set api_key variable to your actual API key
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">Environment Variables</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="font-mono text-sm">base_url</span>
                                <span class="text-gray-600 text-sm">https://api.finaegis.org/v1</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="font-mono text-sm">sandbox_url</span>
                                <span class="text-gray-600 text-sm">https://api-sandbox.finaegis.org/v1</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="font-mono text-sm">api_key</span>
                                <span class="text-gray-600 text-sm">your_api_key_here</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                                <span class="font-mono text-sm">account_uuid</span>
                                <span class="text-gray-600 text-sm">set_after_account_creation</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="font-mono text-sm">transfer_uuid</span>
                                <span class="text-gray-600 text-sm">set_after_transfer_creation</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Collection Overview -->
            <section class="mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Collection Overview</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Authentication</h3>
                        <p class="text-gray-600 text-sm mb-3">API key validation and user authentication flows</p>
                        <span class="text-sm text-green-600 font-medium">3 requests</span>
                    </div>

                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Accounts</h3>
                        <p class="text-gray-600 text-sm mb-3">Account management, creation, and balance queries</p>
                        <span class="text-sm text-blue-600 font-medium">8 requests</span>
                    </div>

                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Transfers</h3>
                        <p class="text-gray-600 text-sm mb-3">Money transfers, status tracking, and confirmations</p>
                        <span class="text-sm text-purple-600 font-medium">12 requests</span>
                    </div>

                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Assets</h3>
                        <p class="text-gray-600 text-sm mb-3">Asset management, exchange rates, and conversions</p>
                        <span class="text-sm text-yellow-600 font-medium">6 requests</span>
                    </div>

                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Workflows</h3>
                        <p class="text-gray-600 text-sm mb-3">Complex workflows, batch operations, and sagas</p>
                        <span class="text-sm text-red-600 font-medium">15 requests</span>
                    </div>

                    <div class="border rounded-lg p-6">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v2a2 2 0 002 2h4a2 2 0 002-2v-2h2a2 2 0 002-2V9a2 2 0 00-2-2h-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v2H4a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold mb-2">Webhooks</h3>
                        <p class="text-gray-600 text-sm mb-3">Webhook configuration and event management</p>
                        <span class="text-sm text-indigo-600 font-medium">5 requests</span>
                    </div>
                </div>
            </section>

            <!-- Test Scripts -->
            <section class="mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Built-in Test Scripts</h2>
                
                <div class="bg-gray-50 rounded-lg p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Automated Testing Features</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Response status code validation</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">JSON schema validation</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Automatic variable extraction</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Response time assertions</span>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-4">Example Test Script</h3>
                            <div class="bg-gray-900 rounded p-4 overflow-x-auto">
                                <pre class="text-green-400 text-sm"><code>pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has data property", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('data');
});

pm.test("Account UUID is valid", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.data.uuid).to.match(/^acct_/);
    
    // Set account UUID for use in other requests
    pm.environment.set("account_uuid", jsonData.data.uuid);
});</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Support -->
            <section class="bg-orange-50 rounded-lg p-8">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Need Help?</h2>
                    <p class="text-gray-600 mb-6">Having trouble with the Postman collection? We're here to help.</p>
                    <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="{{ route('developers.show', 'api-docs') }}" class="bg-orange-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-orange-700 transition duration-200">
                            View API Docs
                        </a>
                        <a href="{{ route('developers.show', 'examples') }}" class="bg-white text-orange-600 border-2 border-orange-600 px-6 py-3 rounded-lg font-semibold hover:bg-orange-50 transition duration-200">
                            See Examples
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-guest-layout>