<?php

// Output directory
$publicPath = __DIR__ . '/../public';

// SVG content for the favicon
$svgContent = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none">
    <!-- Background Circle -->
    <circle cx="256" cy="256" r="240" fill="url(#gradient)"/>
    
    <!-- Letter F -->
    <path d="M140 120h140v60h-80v60h70v60h-70v92h-60V120z" fill="white"/>
    
    <!-- Letter A (stylized as triangle for Aegis/shield) -->
    <path d="M312 392V240l60-120h60l60 120v152h-60v-92h-60v92h-60zm60-152h60l-30-60-30 60z" fill="white"/>
    
    <!-- Gradient Definition -->
    <defs>
        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#6366F1"/>
            <stop offset="100%" style="stop-color:#9333EA"/>
        </linearGradient>
    </defs>
</svg>
SVG;

// Save the SVG file
file_put_contents($publicPath . '/favicon.svg', $svgContent);
echo "Created: favicon.svg\n";

// Create a simple PNG favicon using GD library
function createFavicon($size, $filename) {
    global $publicPath;
    
    // Create image
    $image = imagecreatetruecolor($size, $size);
    
    // Enable alpha blending
    imagesavealpha($image, true);
    
    // Create gradient background (simplified to two-tone)
    $purple1 = imagecolorallocate($image, 99, 102, 241); // #6366F1
    $purple2 = imagecolorallocate($image, 147, 51, 234); // #9333EA
    $white = imagecolorallocate($image, 255, 255, 255);
    
    // Fill with gradient effect (diagonal)
    for ($i = 0; $i < $size; $i++) {
        $color = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $r = (int)(99 + ($i / $size) * (147 - 99));
        $g = (int)(102 + ($i / $size) * (51 - 102));
        $b = (int)(241 + ($i / $size) * (234 - 241));
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $i, $size, $i, $lineColor);
    }
    
    // Draw circular background
    $center = $size / 2;
    $radius = $size * 0.47;
    imagefilledellipse($image, $center, $center, $radius * 2, $radius * 2, $purple2);
    
    // Scale factors for different sizes
    $scale = $size / 512;
    
    // Draw "F" - simplified
    $fWidth = 140 * $scale;
    $fX = 140 * $scale;
    $fY = 120 * $scale;
    
    // F vertical bar
    imagefilledrectangle($image, $fX, $fY, $fX + 60 * $scale, $fY + 272 * $scale, $white);
    
    // F top horizontal
    imagefilledrectangle($image, $fX, $fY, $fX + 140 * $scale, $fY + 60 * $scale, $white);
    
    // F middle horizontal
    imagefilledrectangle($image, $fX, $fY + 120 * $scale, $fX + 70 * $scale, $fY + 180 * $scale, $white);
    
    // Draw "A" - simplified as triangle
    $aX = 372 * $scale;
    $aY = 240 * $scale;
    $aPoints = [
        $aX, 392 * $scale,           // Bottom left
        $aX + 60 * $scale, 240 * $scale,  // Top
        $aX + 120 * $scale, 392 * $scale, // Bottom right
        $aX + 90 * $scale, 392 * $scale,  // Inner bottom right
        $aX + 60 * $scale, 300 * $scale,  // Inner top
        $aX + 30 * $scale, 392 * $scale,  // Inner bottom left
    ];
    imagefilledpolygon($image, $aPoints, 6, $white);
    
    // Save the image
    imagepng($image, $publicPath . '/' . $filename);
    imagedestroy($image);
    
    echo "Created: $filename\n";
}

// Generate different sizes
$sizes = [
    // Standard favicon sizes
    16 => 'favicon-16x16.png',
    32 => 'favicon-32x32.png',
    48 => 'favicon-48x48.png',
    64 => 'favicon-64x64.png',
    
    // Apple Touch Icons
    120 => 'apple-touch-icon-120x120.png',
    152 => 'apple-touch-icon-152x152.png',
    180 => 'apple-touch-icon-180x180.png',
    
    // Android Chrome
    192 => 'android-chrome-192x192.png',
    512 => 'android-chrome-512x512.png',
    
    // Microsoft Tiles
    144 => 'mstile-144x144.png',
    150 => 'mstile-150x150.png',
];

foreach ($sizes as $size => $filename) {
    createFavicon($size, $filename);
}

// Create default apple-touch-icon
copy($publicPath . '/apple-touch-icon-180x180.png', $publicPath . '/apple-touch-icon.png');
echo "Created: apple-touch-icon.png\n";

// Create ICO file (simplified - just copy 32x32 as ico)
copy($publicPath . '/favicon-32x32.png', $publicPath . '/favicon.ico');
echo "Created: favicon.ico\n";

// Create manifest.json for PWA
$manifest = [
    'name' => 'FinAegis Core Banking',
    'short_name' => 'FinAegis',
    'description' => 'Modern Core Banking Platform',
    'start_url' => '/',
    'display' => 'standalone',
    'theme_color' => '#6366F1',
    'background_color' => '#ffffff',
    'icons' => [
        [
            'src' => '/android-chrome-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => '/android-chrome-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

file_put_contents($publicPath . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Created: manifest.json\n";

// Create browserconfig.xml for Microsoft
$browserConfig = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<browserconfig>
    <msapplication>
        <tile>
            <square150x150logo src="/mstile-150x150.png"/>
            <TileColor>#6366F1</TileColor>
        </tile>
    </msapplication>
</browserconfig>
XML;

file_put_contents($publicPath . '/browserconfig.xml', $browserConfig);
echo "Created: browserconfig.xml\n";

echo "\nFavicon generation complete!\n";
echo "Don't forget to update your HTML head section with the appropriate meta tags.\n";