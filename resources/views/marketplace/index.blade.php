@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'Plugin Marketplace - ' . $brand)

@section('seo')
    @include('partials.seo', [
        'title' => 'Plugin Marketplace - ' . $brand,
        'description' => 'Browse, search, and discover plugins for the ' . $brand . ' platform. Extend banking, compliance, DeFi, and more.',
        'keywords' => $brand . ', plugin marketplace, extensions, banking plugins, DeFi plugins, compliance plugins',
    ])
@endsection

@push('styles')
<style>
    .marketplace-gradient { background: linear-gradient(135deg, #7c3aed 0%, #2563eb 50%, #059669 100%); }
    .plugin-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .plugin-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(0,0,0,0.1); }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #f3f4f6; color: #6b7280; }
    .status-failed { background: #fee2e2; color: #991b1b; }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="marketplace-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute top-20 left-10 w-72 h-72 bg-purple-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-emerald-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Plugin Marketplace</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto mb-8">
                Extend {{ $brand }} with plugins for banking, compliance, DeFi, AI, and more.
            </p>

            <!-- Stats -->
            <div class="flex justify-center gap-8">
                <div>
                    <div class="text-3xl font-bold">{{ $stats['total'] }}</div>
                    <div class="text-sm text-white/60">Plugins</div>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $stats['active'] }}</div>
                    <div class="text-sm text-white/60">Active</div>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $stats['vendors'] }}</div>
                    <div class="text-sm text-white/60">Vendors</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Search & Filters -->
<section class="bg-white border-b border-slate-200 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <form method="GET" action="{{ route('marketplace.index') }}" class="flex flex-col sm:flex-row gap-3">
            <!-- Search -->
            <div class="flex-1 relative">
                <label for="search-plugins" class="sr-only">Search plugins</label>
                <input type="text" id="search-plugins" name="search" value="{{ request('search') }}"
                       placeholder="Search plugins..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 text-sm">
                <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Vendor filter -->
            <label for="filter-vendor" class="sr-only">Filter by vendor</label>
            <select id="filter-vendor" name="vendor" class="px-4 py-2.5 rounded-lg border border-slate-300 text-sm bg-white">
                <option value="">All Vendors</option>
                @foreach($vendors as $v)
                <option value="{{ $v }}" {{ request('vendor') === $v ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>

            <!-- Status filter -->
            <label for="filter-status" class="sr-only">Filter by status</label>
            <select id="filter-status" name="status" class="px-4 py-2.5 rounded-lg border border-slate-300 text-sm bg-white">
                <option value="">All Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>

            <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors">
                Search
            </button>

            @if(request()->hasAny(['search', 'vendor', 'status']))
            <a href="{{ route('marketplace.index') }}" class="px-4 py-2.5 text-slate-600 text-sm hover:text-slate-800">Clear</a>
            @endif
        </form>
    </div>
</section>

<!-- Plugin Grid -->
<section class="bg-slate-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($plugins->isEmpty())
        <div class="text-center py-20">
            <svg class="mx-auto w-16 h-16 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <h3 class="text-lg font-semibold text-slate-600 mb-1">No plugins found</h3>
            <p class="text-sm text-slate-500">Try a different search or filter.</p>
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($plugins as $plugin)
            <a href="{{ route('marketplace.show', ['vendor' => $plugin->vendor, 'name' => $plugin->name]) }}"
               class="plugin-card bg-white rounded-xl border border-slate-200 p-6 block">

                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm">
                            {{ strtoupper(substr($plugin->name, 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $plugin->display_name ?? $plugin->name }}</h3>
                            <p class="text-xs text-slate-500">{{ $plugin->vendor }}</p>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium status-{{ $plugin->status }}">
                        {{ ucfirst($plugin->status) }}
                    </span>
                </div>

                <!-- Description -->
                <p class="text-sm text-slate-600 mb-4 line-clamp-2">{{ $plugin->description ?? 'No description available.' }}</p>

                <!-- Footer -->
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>v{{ $plugin->version }}</span>
                    <span>{{ $plugin->license ?? 'MIT' }}</span>
                    @if($plugin->author)
                    <span>by {{ $plugin->author }}</span>
                    @endif
                </div>

                <!-- Permissions -->
                @if($plugin->permissions && count($plugin->permissions) > 0)
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach(array_slice($plugin->permissions, 0, 3) as $perm)
                    <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $perm }}</span>
                    @endforeach
                    @if(count($plugin->permissions) > 3)
                    <span class="text-[10px] text-slate-400">+{{ count($plugin->permissions) - 3 }} more</span>
                    @endif
                </div>
                @endif
            </a>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $plugins->links() }}
        </div>
        @endif
    </div>
</section>

<!-- CTA -->
<section class="bg-white py-12">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-2xl font-bold mb-3">Build Your Own Plugin</h2>
        <p class="text-slate-600 mb-6">17 hooks, 12 permissions, sandboxed execution, and security scanning built in.</p>
        <a href="{{ route('developers.show', 'plugins') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors">
            Plugin Development Guide
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>

@endsection
