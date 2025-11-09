<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Test Super Mario Bros rendering after palette initialization
 */

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$ppu = $nes->getPPU();
$bus = $nes->getBus();

echo "Testing Super Mario Bros rendering\n";
echo "===================================\n\n";

// Run 120 frames (initialization period)
echo "Running 120 frames...\n";
for ($i = 0; $i < 120; $i++) {
    $nes->runFrame();
}

echo "After 120 frames:\n";

// Check palette
echo "Palette RAM:\n";
for ($addr = 0x3F00; $addr < 0x3F20; $addr++) {
    $value = $bus->read($addr);
    printf("  %04X: %02X\n", $addr, $value);
}

// Check PPUCTRL and PPUMASK
$ppuctrl = $bus->read(0x2000);
$ppumask = $bus->read(0x2001);
printf("\nPPUCTRL: %02X, PPUMASK: %02X\n", $ppuctrl, $ppumask);

// Force rendering
echo "\nForcing rendering (PPUMASK = 0x1E)...\n";
$bus->write(0x2001, 0x1E);

// Run one more frame
$nes->runFrame();

// Check frame buffer
$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
$colorCounts = [];

foreach ($frameBuffer as $pixel) {
    $color = sprintf("%02X,%02X,%02X", $pixel[0], $pixel[1], $pixel[2]);
    $uniqueColors[$color] = true;
    $colorCounts[$color] = ($colorCounts[$color] ?? 0) + 1;
}

echo "\nFrame buffer analysis:\n";
echo "Unique colors: " . count($uniqueColors) . "\n";

// Show top 10 colors
arsort($colorCounts);
echo "Top 10 colors (R,G,B: count):\n";
$count = 0;
foreach ($colorCounts as $color => $pixelCount) {
    echo "  $color: $pixelCount pixels\n";
    if (++$count >= 10) break;
}

// Check if background is rendering
echo "\nChecking first few pixels:\n";
for ($i = 0; $i < 10; $i++) {
    $pixel = $frameBuffer[$i];
    printf("  Pixel %d: RGB(%d,%d,%d)\n", $i, $pixel[0], $pixel[1], $pixel[2]);
}

echo "\nDone!\n";
