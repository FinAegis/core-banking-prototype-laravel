@extends('layouts.public')

@section('title', 'Privacy Policy - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Privacy Policy - ' . config('brand.name', 'Zelta') . '',
        'description' => config('brand.name', 'Zelta') . ' privacy policy. Learn how we collect, use, and protect your personal data.',
        'keywords' => config('brand.name', 'Zelta') . ' privacy policy, data protection, GDPR, personal data',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Privacy Policy', 'url' => url('/privacy-policy')]
    ]" />
@endsection

@section('content')
    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <div class="text-center">
                @include('partials.breadcrumb', ['items' => [['name' => 'Privacy Policy', 'url' => url('/privacy-policy')]]])
                <h1 class="font-display text-3xl md:text-4xl lg:text-5xl font-extrabold text-white tracking-tight mb-4">Privacy <span class="text-gradient">Policy</span></h1>
                <p class="text-slate-400 max-w-xl mx-auto">
                    How we collect, use, and protect your personal data.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Content -->
    <section class="py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-slate prose-legal prose-headings:font-bold prose-a:text-blue-600 prose-a:no-underline hover:prose-a:underline max-w-none">
                {!! $policy !!}
            </div>
        </div>
    </section>
@endsection
