@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('api-keys.index') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                ← Back to API Keys
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Create API Key</h1>
            <p class="mt-2 text-gray-600">Generate a new API key for programmatic access</p>
        </div>

        <!-- Form -->
        <form action="{{ route('api-keys.store') }}" method="POST">
            @csrf
            
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Key Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="Production API Key"
                           value="{{ old('name') }}"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">A descriptive name to identify this key</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              rows="3"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Used for production application integration">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Permissions -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Permissions <span class="text-red-500">*</span>
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="read" 
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   {{ in_array('read', old('permissions', ['read'])) ? 'checked' : '' }}>
                            <span class="ml-2">
                                <span class="font-medium">Read</span>
                                <span class="text-sm text-gray-500">- View accounts, transactions, and other data</span>
                            </span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="write" 
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   {{ in_array('write', old('permissions', [])) ? 'checked' : '' }}>
                            <span class="ml-2">
                                <span class="font-medium">Write</span>
                                <span class="text-sm text-gray-500">- Create and update resources</span>
                            </span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="delete" 
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   {{ in_array('delete', old('permissions', [])) ? 'checked' : '' }}>
                            <span class="ml-2">
                                <span class="font-medium">Delete</span>
                                <span class="text-sm text-gray-500">- Remove resources</span>
                            </span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="*" 
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   onclick="toggleAllPermissions(this)"
                                   {{ in_array('*', old('permissions', [])) ? 'checked' : '' }}>
                            <span class="ml-2">
                                <span class="font-medium">All Permissions</span>
                                <span class="text-sm text-gray-500">- Full access to all resources</span>
                            </span>
                        </label>
                    </div>
                    @error('permissions')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expiration -->
                <div>
                    <label for="expires_in" class="block text-sm font-medium text-gray-700 mb-2">
                        Expiration
                    </label>
                    <select name="expires_in" 
                            id="expires_in"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="never" {{ old('expires_in', 'never') === 'never' ? 'selected' : '' }}>Never expires</option>
                        <option value="30" {{ old('expires_in') === '30' ? 'selected' : '' }}>30 days</option>
                        <option value="90" {{ old('expires_in') === '90' ? 'selected' : '' }}>90 days</option>
                        <option value="365" {{ old('expires_in') === '365' ? 'selected' : '' }}>1 year</option>
                    </select>
                    @error('expires_in')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- IP Whitelist -->
                <div>
                    <label for="ip_whitelist" class="block text-sm font-medium text-gray-700 mb-2">
                        IP Whitelist
                    </label>
                    <textarea name="ip_whitelist" 
                              id="ip_whitelist" 
                              rows="4"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="192.168.1.1&#10;10.0.0.0/24">{{ old('ip_whitelist') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">Enter one IP address or CIDR range per line. Leave empty to allow all IPs.</p>
                    @error('ip_whitelist')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Security Notice -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Security Notice</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>The API key will only be shown once after creation</li>
                                    <li>Store it securely and never share it publicly</li>
                                    <li>Use environment variables to store keys in your applications</li>
                                    <li>Revoke compromised keys immediately</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="{{ route('api-keys.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Create API Key
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllPermissions(checkbox) {
    const otherCheckboxes = document.querySelectorAll('input[name="permissions[]"]:not([value="*"])');
    otherCheckboxes.forEach(cb => {
        cb.checked = false;
        cb.disabled = checkbox.checked;
    });
}

// Check on page load
document.addEventListener('DOMContentLoaded', function() {
    const allPermCheckbox = document.querySelector('input[name="permissions[]"][value="*"]');
    if (allPermCheckbox && allPermCheckbox.checked) {
        toggleAllPermissions(allPermCheckbox);
    }
});
</script>
@endsection