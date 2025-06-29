@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('api-keys.index') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                ← Back to API Keys
            </a>
            <div class="mt-2 flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $apiKey->name }}</h1>
                    @if($apiKey->description)
                    <p class="mt-2 text-gray-600">{{ $apiKey->description }}</p>
                    @endif
                </div>
                <div class="flex space-x-2">
                    @if($apiKey->is_active)
                        <a href="{{ route('api-keys.edit', $apiKey) }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Edit
                        </a>
                        <form action="{{ route('api-keys.regenerate', $apiKey) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                    onclick="return confirm('Are you sure? The current key will be revoked and a new one will be generated.')"
                                    class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                                Regenerate
                            </button>
                        </form>
                        <form action="{{ route('api-keys.destroy', $apiKey) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    onclick="return confirm('Are you sure? This action cannot be undone.')"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Revoke
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- New API Key Display -->
        @if(session('new_api_key'))
        <div class="mb-8 bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-green-800">Your API Key</h3>
                    <div class="mt-2">
                        <div class="flex items-center space-x-2">
                            <code class="flex-1 bg-white px-3 py-2 rounded border border-green-300 text-sm font-mono" id="api-key">{{ session('new_api_key') }}</code>
                            <button onclick="copyApiKey()" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm">
                                Copy
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-green-700">
                            <strong>Important:</strong> This is the only time you'll see this key. Please copy it now and store it securely.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Key Details -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Key Details</h2>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Key Preview</dt>
                            <dd class="mt-1">
                                <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ $apiKey->key_prefix }}...</code>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($apiKey->is_active)
                                    @if($apiKey->expires_at && $apiKey->expires_at->isPast())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Expired
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    @endif
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Revoked
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Permissions</dt>
                            <dd class="mt-1 flex gap-2">
                                @foreach($apiKey->permissions as $permission)
                                <span class="inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($permission === '*') bg-purple-100 text-purple-800
                                    @elseif($permission === 'write') bg-yellow-100 text-yellow-800
                                    @elseif($permission === 'delete') bg-red-100 text-red-800
                                    @else bg-green-100 text-green-800
                                    @endif px-2">
                                    {{ $permission }}
                                </span>
                                @endforeach
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $apiKey->created_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        @if($apiKey->expires_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expires</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $apiKey->expires_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        @endif
                        @if($apiKey->allowed_ips)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">IP Whitelist</dt>
                            <dd class="mt-1">
                                <ul class="text-sm text-gray-900 space-y-1">
                                    @foreach($apiKey->allowed_ips as $ip)
                                    <li><code class="bg-gray-100 px-1 rounded">{{ $ip }}</code></li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                    </div>
                    @if($recentLogs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Time
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Method
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Path
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Response Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($recentLogs as $log)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log->created_at->format('M d, g:i A') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($log->method === 'GET') bg-blue-100 text-blue-800
                                            @elseif($log->method === 'POST') bg-green-100 text-green-800
                                            @elseif($log->method === 'PUT' || $log->method === 'PATCH') bg-yellow-100 text-yellow-800
                                            @elseif($log->method === 'DELETE') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ $log->method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <code class="text-xs">{{ $log->path }}</code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($log->response_code >= 200 && $log->response_code < 300) bg-green-100 text-green-800
                                            @elseif($log->response_code >= 400) bg-red-100 text-red-800
                                            @else bg-yellow-100 text-yellow-800
                                            @endif">
                                            {{ $log->response_code }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log->formatted_response_time }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-6 text-center text-gray-500">
                        No activity recorded yet
                    </div>
                    @endif
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Usage Statistics -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Usage Statistics</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Requests</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($stats['total_requests']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Today</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($stats['requests_today']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">This Month</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($stats['requests_this_month']) }}</dd>
                        </div>
                        @if($stats['avg_response_time'])
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Avg Response Time (7d)</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ round($stats['avg_response_time']) }}ms</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Error Rate (7d)</dt>
                            <dd class="mt-1 text-lg font-semibold {{ $stats['error_rate'] > 5 ? 'text-red-600' : 'text-gray-900' }}">
                                {{ number_format($stats['error_rate'], 1) }}%
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Code Example -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Example Usage</h3>
                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                        <pre class="text-green-400 text-sm"><code>curl -H "Authorization: Bearer {{ $apiKey->key_prefix }}..." \
     -H "Content-Type: application/json" \
     {{ url('/api/v2/accounts') }}</code></pre>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">
                        See the <a href="{{ route('developers.show', 'api-docs') }}" class="text-indigo-600 hover:text-indigo-700">API documentation</a> for more examples.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    const apiKey = document.getElementById('api-key').textContent;
    navigator.clipboard.writeText(apiKey).then(function() {
        alert('API key copied to clipboard!');
    });
}
</script>
@endsection