<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->hasInfolist())
            {{ $this->infolist }}
        @endif

        <div>
            {{ $this->table }}
        </div>
    </div>

    @push('scripts')
    <script>
        // Listen for payment verification events if using websockets
        if (typeof Echo !== 'undefined') {
            Echo.channel('cgo-payments')
                .listen('PaymentVerified', (e) => {
                    Livewire.dispatch('payment-verified', { investment: e.investment });
                });
        }
    </script>
    @endpush
</x-filament-panels::page>