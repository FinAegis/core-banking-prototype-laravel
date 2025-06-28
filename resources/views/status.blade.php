<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-600 to-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="text-center">
                    <div class="flex justify-center items-center mb-4">
                        <div class="w-4 h-4 bg-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-400 rounded-full animate-pulse mr-3"></div>
                        <span class="text-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-100 text-lg font-medium">
                            @if($status['overall'] === 'operational')
                                All Systems Operational
                            @elseif($status['overall'] === 'degraded')
                                Some Systems Degraded
                            @else
                                Major Outage
                            @endif
                        </span>
                    </div>
                    <h1 class="text-4xl font-bold text-white sm:text-5xl">
                        System Status
                    </h1>
                    <p class="mt-4 text-xl text-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-100">
                        Real-time status of FinAegis platform services
                    </p>
                    <p class="mt-2 text-sm text-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-200">
                        Last updated: {{ $status['last_checked']->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Current Status Overview -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
                <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Platform Status</h3>
                    <p class="text-2xl font-bold text-{{ $status['overall'] === 'operational' ? 'green' : ($status['overall'] === 'degraded' ? 'yellow' : 'red') }}-600 mt-2 capitalize">{{ $status['overall'] }}</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Uptime</h3>
                    <p class="text-2xl font-bold text-green-600 mt-2">{{ $uptime['percentage'] }}%</p>
                    <p class="text-xs text-gray-500 mt-1">Last {{ $uptime['period'] }}</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Response Time</h3>
                    <p class="text-2xl font-bold text-blue-600 mt-2">{{ $status['response_time'] }}ms</p>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Security Status</h3>
                    <p class="text-2xl font-bold text-purple-600 mt-2">Secure</p>
                </div>
            </div>

            <!-- Service Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-12">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Service Status</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach($services as $service)
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-{{ $service['status'] === 'operational' ? 'green' : ($service['status'] === 'degraded' ? 'yellow' : 'red') }}-400 rounded-full mr-4"></div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ $service['name'] }}</h3>
                                <p class="text-gray-600">{{ $service['description'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{{ $service['status'] === 'operational' ? 'green' : ($service['status'] === 'degraded' ? 'yellow' : 'red') }}-100 text-{{ $service['status'] === 'operational' ? 'green' : ($service['status'] === 'degraded' ? 'yellow' : 'red') }}-800">
                                {{ ucfirst($service['status']) }}
                            </span>
                            <p class="text-xs text-gray-500 mt-1">{{ $service['uptime'] }} uptime</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- System Checks -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-12">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">System Health Checks</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($status['checks'] as $check => $result)
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                @if($result['status'] === 'operational')
                                    <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                @elseif($result['status'] === 'degraded')
                                    <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="ml-3">
                                <h4 class="text-lg font-medium text-gray-900 capitalize">{{ str_replace('_', ' ', $check) }}</h4>
                                <p class="text-gray-600">{{ $result['message'] }}</p>
                                @if(isset($result['response_time']))
                                    <p class="text-sm text-gray-500 mt-1">Response time: {{ $result['response_time'] }}ms</p>
                                @endif
                                @if(isset($result['usage']))
                                    <p class="text-sm text-gray-500 mt-1">Usage: {{ $result['usage'] }}</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Incidents -->
            @if(count($incidents) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Recent Incidents</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach($incidents as $incident)
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ $incident['title'] }}</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $incident['started_at']->format('M d, Y H:i') }} - 
                                    @if($incident['resolved_at'])
                                        {{ $incident['resolved_at']->format('M d, Y H:i') }}
                                    @else
                                        Ongoing
                                    @endif
                                </p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-{{ $incident['status'] === 'resolved' ? 'green' : ($incident['status'] === 'in_progress' ? 'yellow' : 'red') }}-100 text-{{ $incident['status'] === 'resolved' ? 'green' : ($incident['status'] === 'in_progress' ? 'yellow' : 'red') }}-800">
                                {{ ucfirst(str_replace('_', ' ', $incident['status'])) }}
                            </span>
                        </div>
                        
                        @if(count($incident['updates']) > 0)
                        <div class="mt-4 space-y-3">
                            @foreach($incident['updates'] as $update)
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-2 h-2 bg-gray-400 rounded-full mt-2"></div>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-600">{{ $update['message'] }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $update['created_at']->format('M d, Y H:i') }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- API Status Link -->
            <div class="mt-12 text-center">
                <p class="text-gray-600">
                    Need programmatic access? Check our 
                    <a href="{{ route('status.api') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">Status API</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>