<?php

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

// Generate Zelta favicons and PWA assets from favicon.svg.
// Uses ImageMagick CLI (convert) for high-quality SVG→PNG rendering.
//
// Usage: php scripts/generate-favicons-simple.php
// Requires: ImageMagick (apt install imagemagick)

$publicPath = __DIR__ . '/../public';
$svgPath = $publicPath . '/favicon.svg';

if (! file_exists($svgPath)) {
    echo "Error: favicon.svg not found at {$svgPath}\n";
    exit(1);
}

// Generate different sizes
$sizes = [
    // Standard favicon sizes
    ['size' => 16,  'file' => 'favicon-16x16.png'],
    ['size' => 32,  'file' => 'favicon-32x32.png'],
    ['size' => 48,  'file' => 'favicon-48x48.png'],
    ['size' => 64,  'file' => 'favicon-64x64.png'],

    // Apple Touch Icons
    ['size' => 120, 'file' => 'apple-touch-icon-120x120.png'],
    ['size' => 152, 'file' => 'apple-touch-icon-152x152.png'],
    ['size' => 180, 'file' => 'apple-touch-icon-180x180.png'],
    ['size' => 180, 'file' => 'apple-touch-icon.png'],

    // Android Chrome
    ['size' => 192, 'file' => 'android-chrome-192x192.png'],
    ['size' => 512, 'file' => 'android-chrome-512x512.png'],

    // Microsoft Tiles
    ['size' => 144, 'file' => 'mstile-144x144.png'],
    ['size' => 150, 'file' => 'mstile-150x150.png'],
];

foreach ($sizes as $item) {
    $output = $publicPath . '/' . $item['file'];
    $cmd = sprintf(
        'convert -background none -density 512 %s -resize %dx%d %s',
        escapeshellarg($svgPath),
        $item['size'],
        $item['size'],
        escapeshellarg($output)
    );
    exec($cmd, $out, $exitCode);
    echo $exitCode === 0 ? "Created: {$item['file']}\n" : "Failed: {$item['file']}\n";
}

// Create ICO from 32x32
$icoCmd = sprintf(
    'convert -background none -density 512 %s -resize 32x32 %s',
    escapeshellarg($svgPath),
    escapeshellarg($publicPath . '/favicon.ico')
);
exec($icoCmd);
echo "Created: favicon.ico\n";

// Create manifest.json
$manifest = [
    'name'             => 'Zelta — Agentic Payments',
    'short_name'       => 'Zelta',
    'description'      => 'Get your personal card to spend anywhere. Get your agent a card to spend anywhere. Stablecoin-powered, non-custodial.',
    'start_url'        => '/',
    'display'          => 'standalone',
    'theme_color'      => '#0a0a0a',
    'background_color' => '#ffffff',
    'icons'            => [
        [
            'src'     => '/android-chrome-192x192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src'     => '/android-chrome-512x512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

file_put_contents($publicPath . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Created: manifest.json\n";

// Create browserconfig.xml
$browserConfig = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<browserconfig>
    <msapplication>
        <tile>
            <square150x150logo src="/mstile-150x150.png"/>
            <TileColor>#0a0a0a</TileColor>
        </tile>
    </msapplication>
</browserconfig>
XML;

file_put_contents($publicPath . '/browserconfig.xml', $browserConfig);
echo "Created: browserconfig.xml\n";

echo "\nFavicon generation complete!\n";
