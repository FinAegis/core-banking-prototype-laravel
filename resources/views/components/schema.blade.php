@props(['type' => 'organization', 'data' => []])

@php
$schema = match($type) {
    'organization' => \App\Helpers\SchemaHelper::organization(),
    'website' => \App\Helpers\SchemaHelper::website(),
    'software' => \App\Helpers\SchemaHelper::softwareApplication(),
    'gcu' => \App\Helpers\SchemaHelper::gcuProduct(),
    'faq' => \App\Helpers\SchemaHelper::faq($data),
    'breadcrumb' => \App\Helpers\SchemaHelper::breadcrumb($data),
    'service' => \App\Helpers\SchemaHelper::service($data['name'] ?? '', $data['description'] ?? '', $data['category'] ?? ''),
    'article' => \App\Helpers\SchemaHelper::article($data),
    default => ''
};
@endphp

{!! $schema !!}