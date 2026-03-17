@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', ($plugin->display_name ?? $plugin->name) . ' - Plugin Marketplace - ' . $brand)

@section('seo')
    @include('partials.seo', [
        'title' => ($plugin->display_name ?? $plugin->name) . ' - ' . $brand . ' Plugin',
        'description' => $plugin->description ?? $brand . ' plugin by ' . $plugin->vendor,
        'keywords' => $brand . ', plugin, ' . $plugin->name . ', ' . $plugin->vendor,
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #f3f4f6; color: #6b7280; }
    .status-failed { background: #fee2e2; color: #991b1b; }
</style>
@endpush

@section('content')

<!-- Breadcrumb -->
<section class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('marketplace.index') }}" class="hover:text-slate-700">Marketplace</a>
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-700">{{ $plugin->display_name ?? $plugin->name }}</span>
        </nav>
    </div>
</section>

<!-- Plugin Header -->
<section class="bg-white py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start gap-6">
            <div class="w-16 h-16 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xl flex-shrink-0">
                {{ strtoupper(substr($plugin->name, 0, 2)) }}
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-3xl font-bold">{{ $plugin->display_name ?? $plugin->name }}</h1>
                    <span class="text-sm px-2.5 py-0.5 rounded-full font-medium status-{{ $plugin->status }}">
                        {{ ucfirst($plugin->status) }}
                    </span>
                    @if($plugin->is_system)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">System</span>
                    @endif
                </div>
                <p class="text-slate-500 mb-4">
                    by <span class="font-medium text-slate-700">{{ $plugin->vendor }}</span>
                    &middot; v{{ $plugin->version }}
                    @if($plugin->license)
                    &middot; {{ $plugin->license }}
                    @endif
                </p>
                <p class="text-slate-600 max-w-2xl">{{ $plugin->description ?? 'No description available.' }}</p>
            </div>
        </div>
    </div>
</section>

<!-- Details Grid -->
<section class="bg-slate-50 py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Info -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 md:col-span-2">
                <h2 class="font-semibold text-lg mb-4">Plugin Information</h2>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-slate-500">Vendor</dt>
                        <dd class="font-medium">{{ $plugin->vendor }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Name</dt>
                        <dd class="font-medium code-font">{{ $plugin->getFullName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Version</dt>
                        <dd class="font-medium">{{ $plugin->version }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Entry Point</dt>
                        <dd class="font-medium code-font text-xs">{{ $plugin->entry_point ?? 'ServiceProvider' }}</dd>
                    </div>
                    @if($plugin->author)
                    <div>
                        <dt class="text-slate-500">Author</dt>
                        <dd class="font-medium">{{ $plugin->author }}</dd>
                    </div>
                    @endif
                    @if($plugin->homepage && str_starts_with($plugin->homepage, 'https://'))
                    <div>
                        <dt class="text-slate-500">Homepage</dt>
                        <dd><a href="{{ $plugin->homepage }}" class="text-purple-600 hover:underline text-xs" target="_blank" rel="noopener noreferrer">{{ $plugin->homepage }}</a></dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-slate-500">Installed</dt>
                        <dd class="font-medium">{{ $plugin->installed_at?->format('M j, Y') ?? 'N/A' }}</dd>
                    </div>
                    @if($plugin->activated_at)
                    <div>
                        <dt class="text-slate-500">Last Activated</dt>
                        <dd class="font-medium">{{ $plugin->activated_at->format('M j, Y') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Install -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-semibold text-lg mb-4">Installation</h2>
                <div class="bg-slate-900 rounded-lg p-4 text-xs text-green-400 overflow-x-auto code-font">
                    <div class="text-slate-500"># Install</div>
                    <div>php artisan plugin:install \</div>
                    <div class="pl-4">plugins/{{ $plugin->vendor }}/{{ $plugin->name }}/plugin.json</div>
                    <div class="mt-2 text-slate-500"># Enable</div>
                    <div>php artisan plugin:enable \</div>
                    <div class="pl-4">{{ $plugin->vendor }} {{ $plugin->name }}</div>
                </div>
            </div>
        </div>

        <!-- Permissions -->
        @if($plugin->permissions && count($plugin->permissions) > 0)
        <div class="bg-white rounded-xl border border-slate-200 p-6 mt-6">
            <h2 class="font-semibold text-lg mb-4">Required Permissions</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($plugin->permissions as $perm)
                <span class="text-sm bg-purple-50 text-purple-700 px-3 py-1 rounded-lg code-font">{{ $perm }}</span>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Dependencies -->
        @if($plugin->dependencies && count($plugin->dependencies) > 0)
        <div class="bg-white rounded-xl border border-slate-200 p-6 mt-6">
            <h2 class="font-semibold text-lg mb-4">Dependencies</h2>
            <div class="space-y-2">
                @foreach($plugin->dependencies as $dep => $constraint)
                <div class="flex items-center gap-3 text-sm">
                    <code class="code-font bg-slate-100 px-2 py-0.5 rounded">{{ $dep }}</code>
                    <span class="text-slate-500">{{ $constraint }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>

<!-- Back -->
<section class="bg-white py-8">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('marketplace.index') }}" class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Marketplace
        </a>
    </div>
</section>

@endsection
