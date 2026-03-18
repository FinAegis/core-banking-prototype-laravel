@php
    $brandPath = config('brand.favicon_path', '');
    $prefix = $brandPath ? "/brand/{$brandPath}" : '';
    $themeColor = config('brand.theme_color', '#0c1222');
@endphp

<!-- Favicon and Touch Icons -->
<link rel="icon" type="image/x-icon" href="{{ $prefix }}/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="{{ $prefix }}/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $prefix }}/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="{{ $prefix }}/favicon-48x48.png">
<link rel="icon" type="image/svg+xml" href="{{ $prefix }}/favicon.svg">

<!-- Apple Touch Icons -->
<link rel="apple-touch-icon" href="{{ $prefix }}/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $prefix }}/apple-touch-icon.png">

<!-- Android Chrome Icons -->
<link rel="icon" type="image/png" sizes="192x192" href="{{ $prefix }}/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="{{ $prefix }}/android-chrome-512x512.png">

<!-- Microsoft Tiles -->
<meta name="msapplication-TileColor" content="{{ $themeColor }}">

<!-- Theme Color -->
<meta name="theme-color" content="{{ $themeColor }}">

<!-- Safari Pinned Tab -->
<link rel="mask-icon" href="{{ $prefix }}/favicon.svg" color="{{ $themeColor }}">
