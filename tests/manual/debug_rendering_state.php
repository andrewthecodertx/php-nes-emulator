<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Debugging ROM Rendering States ===\n\n";

$roms = [
    'donkeykong.nes' => 'Donkey Kong (WORKS)',
    'nestest.nes' => 'NESTest (WORKS)',
    'supermario.nes' => 'Super Mario Bros (BROKEN)',
    'tetris.nes' => 'Tetris (BROKEN)',
    'joust.nes' => 'Joust (BROKEN)',
];

foreach ($roms as $romFile => $name) {
    $path = __DIR__ . '/../../roms/' . $romFile;

    if (!file_exists($path)) {
        echo "⊘ $name - File not found\n\n";
        continue;
    }

    echo "Testing: $name\n";
    echo str_repeat('-', 60) . "\n";

    $nes = NES::fromROM($path);

    // Run several frames
    for ($i = 0; $i < 10; $i++) {
        $nes->runFrame();
    }

    $bus = $nes->getBus();
    $ppu = $nes->getPPU();

    // Check PPU state using reflection
    $reflection = new ReflectionClass($ppu);

    // Get PPUMASK
    $maskProp = $reflection->getProperty('mask');
    $maskProp->setAccessible(true);
    $mask = $maskProp->getValue($ppu);

    // Get cached flags
    $cachedRenderBgProp = $reflection->getProperty('cachedRenderBackground');
    $cachedRenderBgProp->setAccessible(true);
    $cachedRenderBg = $cachedRenderBgProp->getValue($ppu);

    $cachedRenderEnabledProp = $reflection->getProperty('cachedRenderingEnabled');
    $cachedRenderEnabledProp->setAccessible(true);
    $cachedRenderEnabled = $cachedRenderEnabledProp->getValue($ppu);

    echo "PPU State:\n";
    echo "  Rendering enabled: " . ($mask->isRenderingEnabled() ? 'YES' : 'NO') . "\n";
    echo "  Render background: " . ($mask->renderBackground() ? 'YES' : 'NO') . "\n";
    echo "  Render sprites: " . ($mask->renderSprites() ? 'YES' : 'NO') . "\n";
    echo "  Cached render bg: " . ($cachedRenderBg ? 'YES' : 'NO') . "\n";
    echo "  Cached render enabled: " . ($cachedRenderEnabled ? 'YES' : 'NO') . "\n";

    // Check if PPUMASK was ever written
    echo "\nPPUMASK register value: $" . sprintf("%02X", $mask->get()) . "\n";

    // Count non-black pixels in frame
    $frame = $ppu->getFrameBuffer();
    $nonBlack = 0;
    $uniqueColors = [];

    foreach ($frame as $pixel) {
        if ($pixel[0] !== 0 || $pixel[1] !== 0 || $pixel[2] !== 0) {
            $nonBlack++;
            $colorKey = implode(',', $pixel);
            $uniqueColors[$colorKey] = true;
        }
    }

    echo "Frame buffer:\n";
    echo "  Non-black pixels: $nonBlack / 61440 (" . round(($nonBlack / 61440) * 100, 1) . "%)\n";
    echo "  Unique colors: " . count($uniqueColors) . "\n";

    // Check nametable content
    $nametableData = [];
    for ($i = 0; $i < 32; $i++) {
        $tile = $ppu->ppuRead(0x2000 + $i);
        $nametableData[$tile] = ($nametableData[$tile] ?? 0) + 1;
    }

    echo "Nametable (first 32 tiles):\n";
    echo "  Unique tile IDs: " . count($nametableData) . "\n";
    if (count($nametableData) <= 5) {
        echo "  Tiles: ";
        foreach (array_keys($nametableData) as $tileId) {
            echo sprintf("$%02X ", $tileId);
        }
        echo "\n";
    }

    echo "\n";
}
