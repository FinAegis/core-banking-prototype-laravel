<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Modules</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $this->stats['total'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Installed</div>
                <div class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->stats['installed'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Available</div>
                <div class="mt-1 text-2xl font-bold text-info-600 dark:text-info-400">{{ $this->stats['available'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Disabled</div>
                <div class="mt-1 text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $this->stats['disabled'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Core</div>
                <div class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $this->stats['core'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Optional</div>
                <div class="mt-1 text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $this->stats['optional'] }}</div>
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
                        placeholder="Search by name or description..."
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
                        <option value="installed">Installed</option>
                        <option value="available">Available</option>
                        <option value="disabled">Disabled</option>
                        <option value="missing_dependencies">Missing Dependencies</option>
                    </select>
                </div>
                <div class="w-full sm:w-40">
                    <label for="typeFilter" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select
                        wire:model.live="typeFilter"
                        id="typeFilter"
                        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                    >
                        <option value="">All Types</option>
                        <option value="core">Core</option>
                        <option value="optional">Optional</option>
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

        {{-- Module Table --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full table-auto divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Module
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Version
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Dependencies
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Routes
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->modules as $module)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50" wire:key="module-{{ $module->name }}">
                                {{-- Name & Description --}}
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ $module->displayName }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $module->name }}
                                        </span>
                                        @if ($module->description)
                                            <span class="mt-0.5 line-clamp-1 text-xs text-gray-400 dark:text-gray-500">
                                                {{ $module->description }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Version --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm font-mono text-gray-700 dark:text-gray-300">
                                        {{ $module->version }}
                                    </span>
                                </td>

                                {{-- Type Badge --}}
                                <td class="px-4 py-3">
                                    @if ($module->type->value === 'core')
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                            Core
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                            Optional
                                        </span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-4 py-3">
                                    @switch($module->status->value)
                                        @case('installed')
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                                <x-heroicon-m-check-circle class="h-3.5 w-3.5" />
                                                Installed
                                            </span>
                                            @break
                                        @case('available')
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-info-50 px-2 py-1 text-xs font-medium text-info-700 ring-1 ring-inset ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30">
                                                <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" />
                                                Available
                                            </span>
                                            @break
                                        @case('disabled')
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30">
                                                <x-heroicon-m-pause-circle class="h-3.5 w-3.5" />
                                                Disabled
                                            </span>
                                            @break
                                        @case('missing_dependencies')
                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                <x-heroicon-m-exclamation-triangle class="h-3.5 w-3.5" />
                                                Missing Dependencies
                                            </span>
                                            @break
                                    @endswitch
                                </td>

                                {{-- Dependencies Count --}}
                                <td class="px-4 py-3 text-center">
                                    @if (count($module->dependencies) > 0)
                                        <span
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                            title="{{ implode(', ', $module->dependencies) }}"
                                            x-data x-tooltip.raw="{{ implode(', ', $module->dependencies) }}"
                                        >
                                            {{ count($module->dependencies) }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-600">0</span>
                                    @endif
                                </td>

                                {{-- Has Routes --}}
                                <td class="px-4 py-3 text-center">
                                    @if ($this->hasRoutes($module))
                                        <x-heroicon-m-check class="mx-auto h-5 w-5 text-success-500" />
                                    @else
                                        <x-heroicon-m-minus class="mx-auto h-5 w-5 text-gray-300 dark:text-gray-600" />
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        {{-- Verify Button --}}
                                        <x-filament::button
                                            wire:click="verifyModule('{{ $module->name }}')"
                                            color="gray"
                                            size="xs"
                                            icon="heroicon-m-shield-check"
                                            tooltip="Verify module health"
                                        >
                                            Verify
                                        </x-filament::button>

                                        @if ($module->status === \App\Infrastructure\Domain\Enums\DomainStatus::DISABLED)
                                            {{-- Enable Button --}}
                                            <x-filament::button
                                                wire:click="enableModule('{{ $module->name }}')"
                                                wire:confirm="Are you sure you want to enable this module?"
                                                color="success"
                                                size="xs"
                                                icon="heroicon-m-play"
                                                tooltip="Enable this module"
                                            >
                                                Enable
                                            </x-filament::button>
                                        @elseif ($module->status === \App\Infrastructure\Domain\Enums\DomainStatus::INSTALLED && !$module->type->isRequired())
                                            {{-- Disable Button (not for core domains) --}}
                                            <x-filament::button
                                                wire:click="disableModule('{{ $module->name }}')"
                                                wire:confirm="Are you sure you want to disable this module? Its database tables and data will be kept."
                                                color="warning"
                                                size="xs"
                                                icon="heroicon-m-pause"
                                                tooltip="Disable this module"
                                            >
                                                Disable
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No modules found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Table Footer --}}
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Showing {{ $this->modules->count() }} of {{ $this->stats['total'] }} modules.
                    Core modules cannot be disabled.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
