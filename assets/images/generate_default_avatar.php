<?php
// Set the content type to PNG image
header('Content-Type: image/png');

// Get the username from the query string, default to 'U'
$username = $_GET['name'] ?? 'U';
$size = $_GET['size'] ?? 200;

// Get the first letter of the username
$initial = strtoupper(substr($username, 0, 1));

// Create a new image
$image = imagecreatetruecolor($size, $size);

// Define colors
$bgColor = imagecolorallocate($image, 25, 118, 210); // Material Blue
$textColor = imagecolorallocate($image, 255, 255, 255); // White

// Fill the background
imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);

// Set up the font
$fontSize = $size * 0.4;
$font = realpath(__DIR__ . '/arial.ttf');

// If font file doesn't exist, use built-in font
if (!$font) {
    // Calculate text size and position for built-in font
    $textWidth = imagefontwidth(5) * strlen($initial);
    $textHeight = imagefontheight(5);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2 + $textHeight;
    
    // Add the text
    imagestring($image, 5, $x, $y - $textHeight/2, $initial, $textColor);
} else {
    // Get the bounding box of the text
    $bbox = imagettfbbox($fontSize, 0, $font, $initial);
    $x = ($size - ($bbox[2] - $bbox[0])) / 2;
    $y = ($size - ($bbox[1] - $bbox[7])) / 2;
    
    // Add the text
    imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $initial);
}

// Output the image
imagepng($image);
imagedestroy($image); 