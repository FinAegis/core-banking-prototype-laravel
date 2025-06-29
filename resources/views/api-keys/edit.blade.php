@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('api-keys.show', $apiKey) }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                ← Back to API Key
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Edit API Key</h1>
            <p class="mt-2 text-gray-600">Update settings for this API key</p>
        </div>

        <!-- Form -->
        <form action="{{ route('api-keys.update', $apiKey) }}" method="POST">
            @csrf
            @method('PUT')
            
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
                           value="{{ old('name', $apiKey->name) }}"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              rows="3"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $apiKey->description) }}</textarea>
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
                                   {{ in_array('read', old('permissions', $apiKey->permissions)) ? 'checked' : '' }}>
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
                                   {{ in_array('write', old('permissions', $apiKey->permissions)) ? 'checked' : '' }}>
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
                                   {{ in_array('delete', old('permissions', $apiKey->permissions)) ? 'checked' : '' }}>
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
                                   {{ in_array('*', old('permissions', $apiKey->permissions)) ? 'checked' : '' }}>
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

                <!-- IP Whitelist -->
                <div>
                    <label for="ip_whitelist" class="block text-sm font-medium text-gray-700 mb-2">
                        IP Whitelist
                    </label>
                    <textarea name="ip_whitelist" 
                              id="ip_whitelist" 
                              rows="4"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="192.168.1.1&#10;10.0.0.0/24">{{ old('ip_whitelist', $apiKey->allowed_ips ? implode("\n", $apiKey->allowed_ips) : '') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">Enter one IP address or CIDR range per line. Leave empty to allow all IPs.</p>
                    @error('ip_whitelist')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Current Settings Info -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Current Settings</h3>
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Created:</dt>
                            <dd class="text-gray-900">{{ $apiKey->created_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Last Used:</dt>
                            <dd class="text-gray-900">{{ $apiKey->last_used_at ? $apiKey->last_used_at->format('M d, Y g:i A') : 'Never' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Total Requests:</dt>
                            <dd class="text-gray-900">{{ number_format($apiKey->request_count) }}</dd>
                        </div>
                        @if($apiKey->expires_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Expires:</dt>
                            <dd class="text-gray-900">{{ $apiKey->expires_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="{{ route('api-keys.show', $apiKey) }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Update API Key
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