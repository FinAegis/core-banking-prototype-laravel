<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Plugins</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $this->stats['total'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</div>
                <div class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->stats['active'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive</div>
                <div class="mt-1 text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $this->stats['inactive'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed</div>
                <div class="mt-1 text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $this->stats['failed'] }}</div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input
                        wire:model.live.debounce.300ms="search"
                        id="search"
                        type="text"
                        placeholder="Search by name, vendor, or description..."
                        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label for="statusFilter" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select
                        wire:model.live="statusFilter"
                        id="statusFilter"
                        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <x-filament::button
                        wire:click="resetFilters"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-x-mark"
                    >
                        Clear
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Plugin Cards --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->plugins as $plugin)
                <div
                    class="flex flex-col rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    wire:key="plugin-{{ $plugin->id }}"
                >
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between border-b border-gray-100 p-4 dark:border-gray-800">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $plugin->display_name ?: $plugin->name }}
                                </h3>
                                {{-- Status Badge --}}
                                @if ($plugin->isActive())
                                    <span class="inline-flex items-center gap-x-1 rounded-md bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                        <x-heroicon-m-check-circle class="h-3 w-3" />
                                        Active
                                    </span>
                                @elseif ($plugin->isInactive())
                                    <span class="inline-flex items-center gap-x-1 rounded-md bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30">
                                        <x-heroicon-m-pause-circle class="h-3 w-3" />
                                        Inactive
                                    </span>
                                @elseif ($plugin->isFailed())
                                    <span class="inline-flex items-center gap-x-1 rounded-md bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                        <x-heroicon-m-exclamation-triangle class="h-3 w-3" />
                                        Failed
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $plugin->vendor }}/{{ $plugin->name }}
                                <span class="font-mono">v{{ $plugin->version }}</span>
                            </p>
                        </div>

                        {{-- Security Scan Indicator --}}
                        @if (isset($this->scanResults[$plugin->getFullName()]))
                            @if ($this->scanResults[$plugin->getFullName()]['safe'])
                                <span class="ml-2 flex-shrink-0" title="Security scan passed">
                                    <x-heroicon-m-shield-check class="h-5 w-5 text-success-500" />
                                </span>
                            @else
                                <span class="ml-2 flex-shrink-0" title="Security issues found">
                                    <x-heroicon-m-shield-exclamation class="h-5 w-5 text-danger-500" />
                                </span>
                            @endif
                        @endif
                    </div>

                    {{-- Card Body --}}
                    <div class="flex-1 p-4">
                        @if ($plugin->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ $plugin->description }}
                            </p>
                        @endif

                        {{-- Metadata --}}
                        <div class="mt-3 space-y-1.5">
                            @if ($plugin->author)
                                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <x-heroicon-m-user class="h-3.5 w-3.5" />
                                    <span>{{ $plugin->author }}</span>
                                </div>
                            @endif
                            @if ($plugin->license)
                                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <x-heroicon-m-document-text class="h-3.5 w-3.5" />
                                    <span>{{ $plugin->license }}</span>
                                </div>
                            @endif
                            @if ($plugin->installed_at)
                                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <x-heroicon-m-calendar class="h-3.5 w-3.5" />
                                    <span>Installed {{ $plugin->installed_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Permissions --}}
                        @if (!empty($plugin->permissions))
                            <div class="mt-3">
                                <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Permissions</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ((array) $plugin->permissions as $permission)
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                            @if (is_string($permission))
                                                {{ $permission }}
                                            @else
                                                {{ json_encode($permission) }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Dependencies --}}
                        @if (!empty($plugin->dependencies))
                            <div class="mt-3">
                                <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Dependencies</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ((array) $plugin->dependencies as $key => $dep)
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-1.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                            @if (is_string($key))
                                                {{ $key }}: {{ $dep }}
                                            @else
                                                {{ $dep }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Security Scan Results --}}
                        @if (isset($this->scanResults[$plugin->getFullName()]) && !$this->scanResults[$plugin->getFullName()]['safe'])
                            <div class="mt-3 rounded-lg bg-danger-50 p-2.5 dark:bg-danger-400/10">
                                <div class="mb-1 flex items-center gap-1 text-xs font-medium text-danger-700 dark:text-danger-400">
                                    <x-heroicon-m-shield-exclamation class="h-3.5 w-3.5" />
                                    Security Issues ({{ count($this->scanResults[$plugin->getFullName()]['issues']) }})
                                </div>
                                <div class="max-h-32 space-y-1 overflow-y-auto">
                                    @foreach ($this->scanResults[$plugin->getFullName()]['issues'] as $issue)
                                        <div class="text-xs text-danger-600 dark:text-danger-300">
                                            <span class="font-medium">{{ $issue['type'] }}</span>
                                            in <span class="font-mono">{{ $issue['file'] }}:{{ $issue['line'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Card Footer / Actions --}}
                    <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                        {{-- Security Scan --}}
                        <x-filament::button
                            wire:click="scanPlugin('{{ $plugin->vendor }}', '{{ $plugin->name }}', '{{ $plugin->path }}')"
                            color="gray"
                            size="xs"
                            icon="heroicon-m-shield-check"
                        >
                            Scan
                        </x-filament::button>

                        {{-- Enable / Disable --}}
                        @if ($plugin->isActive())
                            @unless ($plugin->isSystem())
                                <x-filament::button
                                    wire:click="disablePlugin('{{ $plugin->vendor }}', '{{ $plugin->name }}')"
                                    wire:confirm="Are you sure you want to disable this plugin?"
                                    color="warning"
                                    size="xs"
                                    icon="heroicon-m-pause"
                                >
                                    Disable
                                </x-filament::button>
                            @endunless
                        @elseif ($plugin->isInactive())
                            <x-filament::button
                                wire:click="enablePlugin('{{ $plugin->vendor }}', '{{ $plugin->name }}')"
                                wire:confirm="Are you sure you want to enable this plugin?"
                                color="success"
                                size="xs"
                                icon="heroicon-m-play"
                            >
                                Enable
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-heroicon-o-puzzle-piece class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No plugins found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if ($this->search !== '' || $this->statusFilter !== '')
                            No plugins match your search criteria. Try adjusting your filters.
                        @else
                            No plugins are installed. Use "Discover Plugins" to scan the filesystem for available plugins.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Showing {{ $this->plugins->count() }} of {{ $this->stats['total'] }} plugins.
                System plugins cannot be disabled.
            </p>
        </div>
    </div>
</x-filament-panels::page>
