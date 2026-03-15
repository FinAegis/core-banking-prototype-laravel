<?php

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

// Generate OG images for social sharing.
// Uses ImageMagick CLI (convert) for high-quality rendering.
//
// Usage: php scripts/generate-og-image.php
// Requires: ImageMagick (apt install imagemagick)

$publicPath = __DIR__ . '/../public/images';

if (! file_exists($publicPath)) {
    mkdir($publicPath, 0755, true);
}

$brand = 'Zelta';
$headline = 'Agentic Payments';
$sub1 = 'Get your personal card to spend anywhere.';
$sub2 = 'Get your agent a card to spend anywhere.';
$pill = 'Stablecoin-Powered';
$url = 'zelta.app';

// OG Default (1200x630)
$cmd = <<<'CMD'
convert -size 1200x630 xc:none \
  \( -size 1200x630 xc:'#a8f0c4' -size 1200x630 xc:'#c8a8f0' +append -resize 1200x630! -blur 0x80 \) -composite \
  \( -size 1040x470 xc:white -stroke '#0a0a0a' -strokewidth 3 -fill white -draw "roundrectangle 0,0 1039,469 16,16" \) -gravity center -composite \
  \( -size 56x56 xc:'#a8f0c4' -stroke '#0a0a0a' -strokewidth 3 -fill '#a8f0c4' -draw "roundrectangle 0,0 55,55 10,10" -font Helvetica-Bold -pointsize 32 -fill '#0a0a0a' -gravity center -annotate +0+0 "Z" \) -gravity NorthWest -geometry +110+102 -composite \
  -font Helvetica-Bold -pointsize 30 -fill '#0a0a0a' -gravity NorthWest -annotate +180+116 "%BRAND%" \
  -font Helvetica-Bold -pointsize 60 -fill '#0a0a0a' -annotate +110+210 "%HEADLINE%" \
  -font DejaVu-Sans -pointsize 26 -fill '#444444' -annotate +110+300 "%SUB1%" -annotate +110+340 "%SUB2%" \
  \( -size 260x44 xc:'#ccff00' -stroke '#0a0a0a' -strokewidth 3 -fill '#ccff00' -draw "roundrectangle 0,0 259,43 22,22" -font Helvetica-Bold -pointsize 17 -fill '#0a0a0a' -gravity center -annotate +0+0 "%PILL%" \) -gravity NorthWest -geometry +110+385 -composite \
  \( -size 230x150 xc:'#c8a8f0' -stroke '#0a0a0a' -strokewidth 3 -fill '#c8a8f0' -draw "roundrectangle 0,0 229,149 14,14" \( -size 38x28 xc:'#f59e0b' -stroke '#0a0a0a' -strokewidth 2 -fill '#f59e0b' -draw "roundrectangle 0,0 37,27 5,5" \) -gravity NorthWest -geometry +24+36 -composite -font DejaVu-Sans -pointsize 16 -fill '#0a0a0a' -gravity SouthWest -annotate +24+24 "**** **** **** 4242" \) -gravity NorthWest -geometry +840+170 -composite \
  \( -size 200x130 xc:'#a8c8f0' -stroke '#0a0a0a' -strokewidth 3 -fill '#a8c8f0' -draw "roundrectangle 0,0 199,129 12,12" -font Helvetica-Bold -pointsize 12 -fill '#0a0a0a' -gravity NorthWest -annotate +20+20 "AI AGENT" \( -size 30x22 xc:'#10b981' -stroke '#0a0a0a' -strokewidth 2 -fill '#10b981' -draw "roundrectangle 0,0 29,21 4,4" \) -gravity NorthWest -geometry +20+42 -composite -font DejaVu-Sans -pointsize 14 -fill '#0a0a0a' -gravity SouthWest -annotate +20+20 "**** **** 7890" \) -gravity NorthWest -geometry +870+350 -composite \
  -font DejaVu-Sans -pointsize 15 -fill '#888888' -gravity NorthWest -annotate +110+475 "%URL%" \
  %OUTPUT%
CMD;

$ogCmd = str_replace(
    ['%BRAND%', '%HEADLINE%', '%SUB1%', '%SUB2%', '%PILL%', '%URL%', '%OUTPUT%'],
    [$brand, $headline, $sub1, $sub2, $pill, $url, escapeshellarg($publicPath . '/og-default.png')],
    $cmd
);

exec($ogCmd, $output, $exitCode);
echo $exitCode === 0 ? "Created: og-default.png\n" : "Failed: og-default.png\n";

// Twitter (1024x512) — simplified version
$twitterCmd = <<<'CMD'
convert -size 1024x512 xc:none \
  \( -size 1024x512 xc:'#a8f0c4' -size 1024x512 xc:'#c8a8f0' +append -resize 1024x512! -blur 0x60 \) -composite \
  \( -size 920x400 xc:white -stroke '#0a0a0a' -strokewidth 3 -fill white -draw "roundrectangle 0,0 919,399 14,14" \) -gravity center -composite \
  \( -size 48x48 xc:'#a8f0c4' -stroke '#0a0a0a' -strokewidth 3 -fill '#a8f0c4' -draw "roundrectangle 0,0 47,47 9,9" -font Helvetica-Bold -pointsize 28 -fill '#0a0a0a' -gravity center -annotate +0+0 "Z" \) -gravity NorthWest -geometry +76+74 -composite \
  -font Helvetica-Bold -pointsize 26 -fill '#0a0a0a' -gravity NorthWest -annotate +138+86 "%BRAND%" \
  -font Helvetica-Bold -pointsize 50 -fill '#0a0a0a' -annotate +76+170 "%HEADLINE%" \
  -font DejaVu-Sans -pointsize 22 -fill '#444444' -annotate +76+250 "%SUB1%" -annotate +76+282 "%SUB2%" \
  \( -size 230x38 xc:'#ccff00' -stroke '#0a0a0a' -strokewidth 3 -fill '#ccff00' -draw "roundrectangle 0,0 229,37 19,19" -font Helvetica-Bold -pointsize 15 -fill '#0a0a0a' -gravity center -annotate +0+0 "%PILL%" \) -gravity NorthWest -geometry +76+320 -composite \
  -font DejaVu-Sans -pointsize 13 -fill '#888888' -gravity NorthWest -annotate +76+396 "%URL%" \
  %OUTPUT%
CMD;

$twitterCmd = str_replace(
    ['%BRAND%', '%HEADLINE%', '%SUB1%', '%SUB2%', '%PILL%', '%URL%', '%OUTPUT%'],
    [$brand, $headline, $sub1, $sub2, $pill, $url, escapeshellarg($publicPath . '/og-twitter.png')],
    $twitterCmd
);

exec($twitterCmd, $output2, $exitCode2);
echo $exitCode2 === 0 ? "Created: og-twitter.png\n" : "Failed: og-twitter.png\n";

echo "\nOG images generated successfully!\n";
