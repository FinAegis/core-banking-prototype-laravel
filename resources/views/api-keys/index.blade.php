@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">API Keys</h1>
                <p class="mt-2 text-gray-600">Manage your API keys for programmatic access</p>
            </div>
            <a href="{{ route('api-keys.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                Create New Key
            </a>
        </div>

        <!-- API Keys List -->
        @if($apiKeys->count() > 0)
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Key Preview
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Permissions
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Used
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($apiKeys as $apiKey)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $apiKey->name }}</div>
                                @if($apiKey->description)
                                <div class="text-sm text-gray-500">{{ Str::limit($apiKey->description, 50) }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ $apiKey->key_prefix }}...</code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex gap-1">
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
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($apiKey->last_used_at)
                                {{ $apiKey->last_used_at->diffForHumans() }}
                                <div class="text-xs">{{ $apiKey->requests_today }} requests today</div>
                            @else
                                Never
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($apiKey->is_active)
                                @if($apiKey->expires_at && $apiKey->expires_at->isPast())
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Expired
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                    @if($apiKey->expires_at)
                                    <div class="text-xs text-gray-500">
                                        Expires {{ $apiKey->expires_at->diffForHumans() }}
                                    </div>
                                    @endif
                                @endif
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Revoked
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('api-keys.show', $apiKey) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                View
                            </a>
                            @if($apiKey->is_active)
                            <a href="{{ route('api-keys.edit', $apiKey) }}" class="text-indigo-600 hover:text-indigo-900">
                                Edit
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No API keys</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by creating a new API key.</p>
            <div class="mt-6">
                <a href="{{ route('api-keys.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Create New Key
                </a>
            </div>
        </div>
        @endif

        <!-- Documentation Link -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">API Documentation</h3>
            <p class="text-blue-800 mb-4">Learn how to authenticate and make requests to our API.</p>
            <a href="{{ route('developers.show', 'api-docs') }}" class="text-blue-600 hover:text-blue-700 font-medium">
                View API Documentation →
            </a>
        </div>
    </div>
</div>
@endsection