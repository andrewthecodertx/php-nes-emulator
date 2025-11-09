<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Test Super Mario Bros palette initialization
 * Now using runFrame() to properly advance both CPU and PPU
 */

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$ppu = $nes->getPPU();
$bus = $nes->getBus();

echo "Testing Super Mario Bros palette initialization\n";
echo "===============================================\n\n";

// Run 10 frames
for ($frame = 0; $frame < 10; $frame++) {
    echo "Frame $frame:\n";

    // Check palette before frame
    $paletteNonZero = 0;
    for ($addr = 0x3F00; $addr < 0x3F20; $addr++) {
        $value = $bus->read($addr);
        if ($value !== 0x00) {
            $paletteNonZero++;
        }
    }
    echo "  Palette non-zero entries before: $paletteNonZero\n";

    // Check PPUCTRL and PPUMASK
    $ppuctrl = $bus->read(0x2000);
    $ppumask = $bus->read(0x2001);
    printf("  PPUCTRL: %02X, PPUMASK: %02X\n", $ppuctrl, $ppumask);

    // Run frame
    $nes->runFrame();

    // Check palette after frame
    $paletteNonZero = 0;
    $paletteEntries = [];
    for ($addr = 0x3F00; $addr < 0x3F20; $addr++) {
        $value = $bus->read($addr);
        if ($value !== 0x00) {
            $paletteNonZero++;
            $paletteEntries[] = sprintf("%04X=%02X", $addr, $value);
        }
    }
    echo "  Palette non-zero entries after: $paletteNonZero\n";
    if ($paletteNonZero > 0) {
        echo "  Palette entries: " . implode(", ", $paletteEntries) . "\n";
    }

    // Check PPU state
    echo "  PPU state: scanline={$ppu->getScanline()}, cycle={$ppu->getCycle()}, frameCount={$ppu->getFrameCount()}\n";

    echo "\n";
}

// Force rendering and check result
echo "Forcing rendering (PPUMASK = 0x1E)...\n";
$bus->write(0x2001, 0x1E);
$nes->runFrame();

// Count unique colors in frame buffer
$frameBuffer = $nes->getFrameBuffer();
$uniqueColors = [];
foreach ($frameBuffer as $pixel) {
    $color = implode(',', $pixel);
    $uniqueColors[$color] = true;
}

echo "Frame buffer has " . count($uniqueColors) . " unique colors\n";

// Check if it's not all black
$hasColor = false;
foreach ($frameBuffer as $pixel) {
    if ($pixel[0] !== 0 || $pixel[1] !== 0 || $pixel[2] !== 0) {
        $hasColor = true;
        break;
    }
}

echo "Has non-black pixels: " . ($hasColor ? "YES" : "NO") . "\n";

echo "\nDone!\n";
