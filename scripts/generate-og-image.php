<?php

// Output directory
$publicPath = __DIR__ . '/../public/images';

// Create images directory if it doesn't exist
if (!file_exists($publicPath)) {
    mkdir($publicPath, 0755, true);
}

// Create Open Graph image (1200x630)
$width = 1200;
$height = 630;

// Create image
$image = imagecreatetruecolor($width, $height);

// Define colors
$bgGradient1 = imagecolorallocate($image, 99, 102, 241); // #6366F1
$bgGradient2 = imagecolorallocate($image, 147, 51, 234); // #9333EA
$white = imagecolorallocate($image, 255, 255, 255);
$darkGray = imagecolorallocate($image, 31, 41, 55); // #1F2937

// Create gradient background
for ($i = 0; $i < $height; $i++) {
    $ratio = $i / $height;
    $r = (int)(99 + $ratio * (147 - 99));
    $g = (int)(102 + $ratio * (51 - 102));
    $b = (int)(241 + $ratio * (234 - 241));
    $lineColor = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $i, $width, $i, $lineColor);
}

// Add semi-transparent overlay for better text contrast
$overlay = imagecreatetruecolor($width, $height);
$black = imagecolorallocate($overlay, 0, 0, 0);
imagefilledrectangle($overlay, 0, 0, $width, $height, $black);
imagecopymerge($image, $overlay, 0, 0, 0, 0, $width, $height, 20);
imagedestroy($overlay);

// Add logo/brand text
$fontSize = 72;
$font = 5; // Built-in font
$text = "FinAegis";

// Calculate text position for centering
$textWidth = imagefontwidth($font) * strlen($text) * 8;
$x = ($width - $textWidth) / 2;
$y = 200;

// Draw main brand text with larger size (simulated)
for ($i = 0; $i < 8; $i++) {
    for ($j = 0; $j < 8; $j++) {
        imagestring($image, 5, $x + $i, $y + $j, $text, $white);
    }
}

// Add tagline
$tagline = "The Enterprise Financial Platform";
$taglineSize = 3;
$taglineWidth = imagefontwidth($taglineSize) * strlen($tagline);
$taglineX = ($width - $taglineWidth) / 2;
$taglineY = 320;

imagestring($image, $taglineSize, $taglineX, $taglineY, $tagline, $white);

// Add feature text
$feature1 = "Powering the Future of Banking";
$feature2 = "Democratic Governance | Real Bank Integration";
$featureSize = 2;

$feature1Width = imagefontwidth($featureSize) * strlen($feature1);
$feature1X = ($width - $feature1Width) / 2;
imagestring($image, $featureSize, $feature1X, 420, $feature1, $white);

$feature2Width = imagefontwidth($featureSize) * strlen($feature2);
$feature2X = ($width - $feature2Width) / 2;
imagestring($image, $featureSize, $feature2X, 450, $feature2, $white);

// Save the image
imagepng($image, $publicPath . '/og-default.png');
imagedestroy($image);

echo "Created: og-default.png\n";

// Create a smaller version for Twitter (1024x512)
$twitterWidth = 1024;
$twitterHeight = 512;

$twitterImage = imagecreatetruecolor($twitterWidth, $twitterHeight);

// Create gradient background for Twitter
for ($i = 0; $i < $twitterHeight; $i++) {
    $ratio = $i / $twitterHeight;
    $r = (int)(99 + $ratio * (147 - 99));
    $g = (int)(102 + $ratio * (51 - 102));
    $b = (int)(241 + $ratio * (234 - 241));
    $lineColor = imagecolorallocate($twitterImage, $r, $g, $b);
    imageline($twitterImage, 0, $i, $twitterWidth, $i, $lineColor);
}

// Add overlay
$twitterOverlay = imagecreatetruecolor($twitterWidth, $twitterHeight);
$black = imagecolorallocate($twitterOverlay, 0, 0, 0);
imagefilledrectangle($twitterOverlay, 0, 0, $twitterWidth, $twitterHeight, $black);
imagecopymerge($twitterImage, $twitterOverlay, 0, 0, 0, 0, $twitterWidth, $twitterHeight, 20);
imagedestroy($twitterOverlay);

// Add text to Twitter image
$twitterTextX = ($twitterWidth - $textWidth) / 2;
for ($i = 0; $i < 8; $i++) {
    for ($j = 0; $j < 8; $j++) {
        imagestring($twitterImage, 5, $twitterTextX + $i, 150 + $j, $text, $white);
    }
}

$twitterTaglineX = ($twitterWidth - $taglineWidth) / 2;
imagestring($twitterImage, $taglineSize, $twitterTaglineX, 260, $tagline, $white);

$twitterFeature1X = ($twitterWidth - $feature1Width) / 2;
imagestring($twitterImage, $featureSize, $twitterFeature1X, 350, $feature1, $white);

// Save Twitter image
imagepng($twitterImage, $publicPath . '/og-twitter.png');
imagedestroy($twitterImage);

echo "Created: og-twitter.png\n";

echo "\nOpen Graph images generated successfully!\n";