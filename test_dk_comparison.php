<?php

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Donkey Kong Comparison Test ===\n\n";

// Test 1: Old method (10 frames + forceRendering + 1 frame)
echo "Test 1: Reset method (10 frames + forceRendering + 1 frame)\n";
$nes1 = NES::fromROM(__DIR__ . '/roms/donkeykong.nes');

for ($i = 0; $i < 10; $i++) {
    $nes1->runFrame();
}

$bus = $nes1->getBus();
$bus->write(0x2001, 0x1E); // forceRendering
$nes1->runFrame();

$frameBuffer = $nes1->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "After reset (11 frames): " . count($uniqueColors) . " colors\n\n";

// Now run 240 more frames
echo "Running 240 more frames...\n";
for ($i = 0; $i < 240; $i++) {
    $nes1->runFrame();
}

$frameBuffer = $nes1->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}
echo "After 251 total frames: " . count($uniqueColors) . " colors\n";

if (count($uniqueColors) != 5) {
    echo "✓ Screen changed! Colors: " . count($uniqueColors) . "\n";
} else {
    echo "✗ Screen stuck at 5 colors\n";

    // Let's check CPU state
    $cpu = $nes1->getCPU();
    echo "\nCPU State:\n";
    echo "  PC: 0x" . dechex($cpu->pc) . "\n";
    echo "  SP: 0x" . dechex($cpu->sp) . "\n";
    echo "  A: 0x" . dechex($cpu->accumulator) . "\n";
}
