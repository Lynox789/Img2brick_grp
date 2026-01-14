<?php
// This version uses the color of the pixel for the tenon so that it integrates perfectly.

ini_set('display_errors', 0);
error_reporting(0);

// Backup function in case of error
function errorImage($msg) {
    $im = imagecreate(300, 50);
    $bg = imagecolorallocate($im, 255, 200, 200);
    $text_color = imagecolorallocate($im, 255, 0, 0);
    imagestring($im, 3, 5, 15, "Erreur: $msg", $text_color);
    header("Content-Type: image/png");
    imagepng($im);
    imagedestroy($im);
    exit;
}

session_start();

if (!isset($_GET['id'])) { errorImage("Pas d'ID"); }

$propId = intval($_GET['id']);
// Check this path carefully. If your images are in a subfolder, adapt.
$filename = __DIR__ . "/uploads/preview_" . $propId . ".png";

if (!file_exists($filename)) { errorImage("Image introuvable"); }

// Loading the source image
$source = imagecreatefrompng($filename);
if (!$source) { errorImage("Fichier corrompu"); }

$sw = imagesx($source);
$sh = imagesy($source);

$brickSize = 40;
$destW = $sw * $brickSize;
$destH = $sh * $brickSize;

$dest = imagecreatetruecolor($destW, $destH);
imagealphablending($dest, true);
imagesavealpha($dest, true);

$gridBorderColor = imagecolorallocatealpha($dest, 0, 0, 0, 80);

$castShadowColor = imagecolorallocatealpha($dest, 0, 0, 0, 95); 

$bevelShadowColor = imagecolorallocatealpha($dest, 0, 0, 0, 105);

$bevelLightColor = imagecolorallocatealpha($dest, 255, 255, 255, 100);

// Geometry parameters
$studSize = $brickSize * 0.65;
$offsetShadow = 3; // Offset of the cast shadow
$offsetRelief = 2; // Shift for the 3D effect of the button

// Pixel by pixel drawing loop
for ($y = 0; $y < $sh; $y++) {
    for ($x = 0; $x < $sw; $x++) {
        
        $rgbIndex = imagecolorat($source, $x, $y);
        $rgba = imagecolorsforindex($source, $rgbIndex);
        
        // We reallocate this color for the destination image
        $pixelColor = imagecolorallocate($dest, $rgba['red'], $rgba['green'], $rgba['blue']);
        
        // Coordinates of the top-left corner of the brick
        $dx = $x * $brickSize;
        $dy = $y * $brickSize;
        $centerX = $dx + ($brickSize / 2);
        $centerY = $dy + ($brickSize / 2);
        
        // Fill in the square with the color of the pixel
        imagefilledrectangle($dest, $dx, $dy, $dx + $brickSize, $dy + $brickSize, $pixelColor);

        imagerectangle($dest, $dx, $dy, $dx + $brickSize - 1, $dy + $brickSize - 1, $gridBorderColor);

        // We draw a black circle shifted towards the bottom-right
        imagefilledellipse($dest, 
            $centerX + $offsetShadow, $centerY + $offsetShadow, 
            $studSize, $studSize, 
            $castShadowColor
        );

        // Drawing the shadow
        imagefilledellipse($dest, 
            $centerX + 1, $centerY + 1, 
            $studSize, $studSize, 
            $bevelShadowColor
        );
        
        
        imagefilledellipse($dest, 
            $centerX - 1, $centerY - 1, 
            $studSize, $studSize, 
            $bevelLightColor
        );
        // We redraw a circle with the pixel color
        imagefilledellipse($dest, 
            $centerX - 0.5, $centerY - 0.5, // Slightly shifted towards the light
            $studSize - 2, $studSize - 2, 
            $pixelColor
        );
    }
}

// submitting the image
header("Content-Type: image/png");
imagepng($dest);

// cleaning
imagedestroy($source);
imagedestroy($dest);
?>