@extends('layouts.public')

@section('title', 'System status of ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'System status of ' . config('brand.name', 'Zelta'),
        'description' => 'This page shows whether ' . config('brand.name', 'Zelta') . ' services are working right now.',
        'keywords' => config('brand.name', 'Zelta') . ' status, system status, platform uptime, service availability',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'System Status', 'url' => url('/status')]
    ]" />
@endsection

@section('content')
    <!-- Hero / Status Header -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/4 right-1/4 w-72 h-72 bg-blue-500/8 rounded-full blur-[100px]"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            @include('partials.breadcrumb', ['items' => [['name' => 'System Status', 'url' => url('/status')]]])
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    @if($status['overall'] === 'operational')
                        <div class="w-12 h-12 bg-emerald-500/20 border border-emerald-500/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    @elseif($status['overall'] === 'degraded')
                        <div class="w-12 h-12 bg-amber-500/20 border border-amber-500/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/>
                            </svg>
                        </div>
                    @else
                        <div class="w-12 h-12 bg-red-500/20 border border-red-500/30 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                    @endif
                    <div>
                        <h1 class="font-display text-3xl md:text-4xl font-extrabold text-white">
                            @if($status['overall'] === 'operational')
                                All Checked Services Are Working
                            @elseif($status['overall'] === 'degraded')
                                Some Services Have Problems
                            @else
                                Some Services Are Down
                            @endif
                        </h1>
                        <p class="text-sm text-slate-400 mt-1">Updated {{ $status['last_checked']->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-center">
                        <div class="font-display text-2xl font-bold text-white">{{ $uptime['percentage'] !== null ? $uptime['percentage'] . '%' : 'No data' }}</div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider">Checks passed in 30 days</div>
                    </div>
                    <div class="text-center">
                        <div class="font-display text-2xl font-bold text-white">{{ $status['response_time'] ?? 'No data' }}</div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider">Database answer time in milliseconds</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Services -->
    <section class="py-12 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @php
                $grouped = collect($services)->groupBy('category');
                $statusLabels = [
                    'operational'   => 'Operational',
                    'degraded'      => 'Has problems',
                    'down'          => 'Down',
                    'unknown'       => 'No recent check',
                    'not_monitored' => 'Not monitored',
                ];
                $dotClasses = [
                    'operational' => 'bg-emerald-500',
                    'degraded'    => 'bg-amber-500',
                    'down'        => 'bg-red-500',
                ];
                $badgeClasses = [
                    'operational' => 'badge-success',
                    'degraded'    => 'badge-warning',
                    'down'        => 'badge-accent',
                ];
            @endphp

            @foreach($grouped as $category => $categoryServices)
            <div class="mb-8 animate-on-scroll">
                <h2 class="font-display text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3 px-1">{{ $category }}</h2>
                <div class="card-feature !p-0 overflow-hidden divide-y divide-slate-100">
                    @foreach($categoryServices as $service)
                    <div class="px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full {{ $dotClasses[$service['status']] ?? 'border border-slate-300 bg-white' }}"></div>
                            <div>
                                <span class="text-sm font-medium text-slate-900">{{ $service['name'] }}</span>
                                <span class="text-sm text-slate-400 ml-2 hidden sm:inline">{{ $service['description'] }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($service['uptime'] !== null)
                                <span class="text-xs text-slate-400 hidden sm:inline font-mono">{{ $service['uptime'] }}</span>
                            @endif
                            <span class="badge {{ $badgeClasses[$service['status']] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $statusLabels[$service['status']] ?? 'Not monitored' }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            <div class="mb-8 px-1 space-y-2 text-sm text-slate-500">
                <p>Not monitored means that no automatic check covers this service yet. This page cannot tell whether it is working.</p>
                <p>No recent check means that the automatic check has not reported in the last 15 minutes.</p>
                <p>The percentage next to a service is the share of its checks that passed in the last 30 days.</p>
            </div>

            <!-- System Health Checks -->
            <div class="mb-8 animate-on-scroll">
                <h2 class="font-display text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3 px-1">System Health</h2>
                <div class="card-feature !p-0 overflow-hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                        @foreach($status['checks'] as $check => $result)
                        <div class="px-5 py-4 flex items-start gap-3">
                            @if($result['status'] === 'operational')
                                <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @elseif($result['status'] === 'degraded')
                                <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                            <div>
                                <div class="font-display text-sm font-medium text-slate-900">{{ $result['name'] ?? ucfirst(str_replace('_', ' ', $check)) }}</div>
                                <div class="text-xs text-slate-500">{{ $result['message'] }}</div>
                                @if(isset($result['response_time']))
                                    <div class="text-xs text-slate-400 mt-0.5">It answered in {{ $result['response_time'] }} milliseconds.</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Incidents -->
            @if(count($incidents) > 0)
            <div class="mb-8 animate-on-scroll">
                <h2 class="font-display text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3 px-1">Recent Incidents</h2>
                <div class="card-feature !p-0 overflow-hidden divide-y divide-slate-100">
                    @foreach($incidents as $incident)
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-display text-sm font-medium text-slate-900">{{ $incident['title'] }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $incident['started_at']->format('M d, Y H:i') }}
                                    @if($incident['resolved_at'])
                                        &mdash; {{ $incident['resolved_at']->format('M d, Y H:i') }}
                                    @else
                                        &mdash; Ongoing
                                    @endif
                                </p>
                            </div>
                            <span class="badge {{ $incident['status'] === 'resolved' ? 'badge-success' : ($incident['status'] === 'in_progress' ? 'badge-warning' : 'badge-accent') }}">
                                {{ ucfirst(str_replace('_', ' ', $incident['status'])) }}
                            </span>
                        </div>

                        @if(count($incident['updates']) > 0)
                        <div class="ml-4 border-l-2 border-slate-200 pl-4 mt-3 space-y-2">
                            @foreach($incident['updates'] as $update)
                            <div>
                                <p class="text-xs text-slate-600">{{ $update['message'] }}</p>
                                <p class="text-xs text-slate-400">{{ $update['created_at']->format('M d, H:i') }}</p>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Footer -->
            <div class="text-center py-6">
                <p class="text-sm text-slate-500">
                    Programs can read the same status as data.
                    <a href="{{ route('status.api') }}" class="text-blue-600 hover:text-blue-700 font-semibold">Open the status data</a>
                </p>
            </div>
        </div>
    </section>
@endsection
