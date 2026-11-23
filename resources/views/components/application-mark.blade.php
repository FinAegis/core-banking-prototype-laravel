@php($brandName = config('brand.name', 'Zelta'))
<div {{ $attributes }}>
    <span class="text-2xl font-bold">
        @if(strtolower((string) $brandName) === 'finaegis')
            <span class="text-indigo-600">Fin</span><span class="text-purple-600">Aegis</span>
        @else
            <span class="text-slate-900 dark:text-white">{{ $brandName }}</span>
        @endif
    </span>
</div>
