<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-heroicon-o-building-office-2 class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Current Tenant
                    </p>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        {{ $currentTenantName }}
                    </p>
                </div>
            </div>

            @if(count($tenants) > 0)
                <div class="flex items-center gap-2">
                    @if($hasTenantContext)
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400">
                            <x-heroicon-s-check-circle class="w-3 h-3 mr-1" />
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400">
                            <x-heroicon-s-globe-alt class="w-3 h-3 mr-1" />
                            Platform View
                        </span>
                    @endif

                    @if(count($tenants) > 1)
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::button size="sm" color="gray">
                                    <x-heroicon-m-arrows-right-left class="w-4 h-4 mr-1" />
                                    Switch Tenant
                                </x-filament::button>
                            </x-slot>

                            <x-filament::dropdown.list>
                                @foreach($tenants as $id => $name)
                                    <x-filament::dropdown.list.item
                                        :href="request()->fullUrlWithQuery(['tenant' => $id])"
                                        :color="$id === $currentTenantId ? 'primary' : 'gray'"
                                        :icon="$id === $currentTenantId ? 'heroicon-o-check-circle' : 'heroicon-o-building-office'"
                                    >
                                        {{ $name }}
                                    </x-filament::dropdown.list.item>
                                @endforeach

                                @if($hasTenantContext)
                                    <x-filament::dropdown.list.item
                                        :href="request()->fullUrlWithQuery(['tenant' => 'clear'])"
                                        color="warning"
                                        icon="heroicon-o-globe-alt"
                                    >
                                        View All (Platform)
                                    </x-filament::dropdown.list.item>
                                @endif
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
