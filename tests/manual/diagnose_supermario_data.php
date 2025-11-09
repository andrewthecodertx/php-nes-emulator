<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Diagnosing Super Mario Bros Data ===\n\n";

$romPath = __DIR__ . '/../../roms/supermario.nes';

if (!file_exists($romPath)) {
    echo "Error: supermario.nes not found\n";
    exit(1);
}

$nes = NES::fromROM($romPath);
$ppu = $nes->getPPU();
$bus = $nes->getBus();

// Run 11 frames like the viewer
for ($i = 0; $i < 11; $i++) {
    $nes->runFrame();
    if ($i == 10) {
        $bus->write(0x2001, 0x1E); // Force rendering
    }
}

// Access PPU internal state
$ppuReflection = new ReflectionClass($ppu);

// Check nametable
$nametableProp = $ppuReflection->getProperty('nametable');
$nametableProp->setAccessible(true);
$nametable = $nametableProp->getValue($ppu);

$nonZeroNametable = count(array_filter($nametable, fn($x) => $x !== 0));
echo "Nametable:\n";
echo "  Size: " . count($nametable) . " bytes\n";
echo "  Non-zero tiles: $nonZeroNametable\n";

if ($nonZeroNametable > 0) {
    echo "  First 64 bytes: ";
    for ($i = 0; $i < 64; $i++) {
        echo sprintf("%02X ", $nametable[$i] ?? 0);
        if (($i + 1) % 16 === 0) echo "\n                  ";
    }
    echo "\n";
}

// Check palette
$paletteRamProp = $ppuReflection->getProperty('paletteRam');
$paletteRamProp->setAccessible(true);
$paletteRam = $paletteRamProp->getValue($ppu);

$nonZeroPalette = count(array_filter($paletteRam, fn($x) => $x !== 0));
echo "\nPalette RAM:\n";
echo "  Size: " . count($paletteRam) . " bytes\n";
echo "  Non-zero entries: $nonZeroPalette\n";
echo "  First 16 bytes: ";
for ($i = 0; $i < 16; $i++) {
    echo sprintf("%02X ", $paletteRam[$i] ?? 0);
}
echo "\n";

// Check CHR-ROM via mapper
$cartridge = $nes->getBus()->getMapper();
$chrData = [];
for ($addr = 0x0000; $addr < 0x0100; $addr++) {
    $chrData[] = $cartridge->ppuRead($addr);
}
$nonZeroCHR = count(array_filter($chrData, fn($x) => $x !== 0));

echo "\nCHR-ROM (first 256 bytes):\n";
echo "  Non-zero bytes: $nonZeroCHR / 256\n";

if ($nonZeroCHR > 0) {
    echo "  First 64 bytes: ";
    for ($i = 0; $i < 64; $i++) {
        echo sprintf("%02X ", $chrData[$i]);
        if (($i + 1) % 16 === 0) echo "\n                  ";
    }
    echo "\n";
}

// Check PPU control/mask registers
$controlProp = $ppuReflection->getProperty('control');
$controlProp->setAccessible(true);
$control = $controlProp->getValue($ppu);

$maskProp = $ppuReflection->getProperty('mask');
$maskProp->setAccessible(true);
$mask = $maskProp->getValue($ppu);

echo "\nPPU Registers:\n";
echo "  PPUCTRL: $" . sprintf("%02X", $control->get()) . "\n";
echo "  PPUMASK: $" . sprintf("%02X", $mask->get()) . "\n";
echo "  Rendering enabled: " . ($mask->isRenderingEnabled() ? 'YES' : 'NO') . "\n";
echo "  Render background: " . ($mask->renderBackground() ? 'YES' : 'NO') . "\n";
echo "  Render sprites: " . ($mask->renderSprites() ? 'YES' : 'NO') . "\n";

// Check frame buffer
$frame = $ppu->getFrameBuffer();
$colorCounts = [];
foreach ($frame as $pixel) {
    $key = implode(',', $pixel);
    $colorCounts[$key] = ($colorCounts[$key] ?? 0) + 1;
}

echo "\nFrame Buffer:\n";
echo "  Total pixels: " . count($frame) . "\n";
echo "  Unique colors: " . count($colorCounts) . "\n";

if (count($colorCounts) <= 10) {
    echo "  Colors:\n";
    arsort($colorCounts);
    foreach ($colorCounts as $colorKey => $pixels) {
        list($r, $g, $b) = explode(',', $colorKey);
        printf("    RGB(%3d,%3d,%3d): %6d pixels (%.1f%%)\n",
            $r, $g, $b, $pixels, ($pixels / 61440) * 100);
    }
}

echo "\n";
