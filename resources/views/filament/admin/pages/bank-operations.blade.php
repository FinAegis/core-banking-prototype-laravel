<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">System Health</h3>
                @php
                    $healthData = $this->getHealthMonitor()->getAllCustodiansHealth();
                    $healthy = collect($healthData)->where('status', 'healthy')->count();
                    $total = count($healthData);
                    $percentage = $total > 0 ? round(($healthy / $total) * 100) : 0;
                @endphp
                <div class="text-3xl font-bold 
                    @if($percentage === 100) text-green-600
                    @elseif($percentage >= 80) text-yellow-600
                    @else text-red-600
                    @endif">
                    {{ $percentage }}%
                </div>
                <p class="text-sm text-gray-600">{{ $healthy }}/{{ $total }} banks operational</p>
            </x-filament::card>
            
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">Active Connections</h3>
                @php
                    $registry = $this->getCustodianRegistry();
                    $connectors = $registry->getAllConnectorNames();
                @endphp
                <div class="text-3xl font-bold text-primary-600">
                    {{ count($connectors) }}
                </div>
                <p class="text-sm text-gray-600">Configured bank connectors</p>
            </x-filament::card>
            
            <x-filament::card>
                <h3 class="text-lg font-semibold mb-2">Next Actions</h3>
                <div class="space-y-2">
                    <x-filament::button
                        wire:click="$dispatch('open-modal', { id: 'run-reconciliation' })"
                        size="sm"
                        color="primary">
                        Run Reconciliation
                    </x-filament::button>
                    
                    <x-filament::button
                        wire:click="$refresh"
                        size="sm"
                        color="gray">
                        Refresh Status
                    </x-filament::button>
                </div>
            </x-filament::card>
        </div>
        
        <div>
            <h2 class="text-xl font-semibold mb-4">Bank Connector Status</h2>
            {{ $this->table }}
        </div>
    </div>
    
    <x-filament::modal id="run-reconciliation">
        <x-slot name="heading">
            Run Daily Reconciliation
        </x-slot>
        
        <p>This will perform a full balance reconciliation for all accounts. This process may take several minutes.</p>
        
        <x-slot name="footer">
            <x-filament::button
                wire:click="runReconciliation"
                color="primary">
                Start Reconciliation
            </x-filament::button>
            
            <x-filament::button
                wire:click="$dispatch('close-modal', { id: 'run-reconciliation' })"
                color="gray">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>