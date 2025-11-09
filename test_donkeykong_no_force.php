<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Testing Donkey Kong Without forceRendering ===\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');

// Run 10 frames without forceRendering
echo "Running 10 initial frames...\n";
for ($i = 0; $i < 10; $i++) {
    $nes->runFrame();
}

// Check colors
$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "After 10 frames (no forceRendering): " . count($uniqueColors) . " colors\n\n";

// Run more frames
echo "Running 240 more frames (4 seconds)...\n";
for ($i = 0; $i < 240; $i++) {
    $nes->runFrame();
}

// Check colors again
$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "After 250 total frames: " . count($uniqueColors) . " colors\n";

if (count($uniqueColors) > 5) {
    echo "✓ Screen changed! Demo likely started.\n";
} else {
    echo "✗ Screen stuck at " . count($uniqueColors) . " colors\n";
}
