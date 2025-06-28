<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{ $head ?? '' }}
</head>
<body class="antialiased">
    <x-alpha-banner />
    
    <!-- Spacer for fixed banner -->
    <div class="h-12"></div>
    
    <x-main-navigation />
    
    {{ $slot }}
</body>
</html>