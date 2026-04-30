@php($brandName = config('brand.name', 'Zelta'))
<span {{ $attributes->merge(['class' => 'inline-block font-bold tracking-tight text-slate-900 dark:text-white']) }}>
    {{ $brandName }}
</span>
