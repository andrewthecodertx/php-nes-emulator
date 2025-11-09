<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Testing Forced Rendering ===\n\n";

$roms = [
    'donkeykong.nes' => 'Donkey Kong',
    'supermario.nes' => 'Super Mario',
    'tetris.nes' => 'Tetris',
];

foreach ($roms as $romFile => $name) {
    $path = __DIR__ . '/../../roms/' . $romFile;

    if (!file_exists($path)) {
        continue;
    }

    echo "Testing: $name\n";
    echo str_repeat('=', 60) . "\n";

    $nes = NES::fromROM($path);

    // Run 10 frames
    for ($i = 0; $i < 10; $i++) {
        $nes->runFrame();
    }

    // FORCE RENDERING (like the viewer does)
    $bus = $nes->getBus();
    $bus->write(0x2001, 0x1E);

    // Run one more frame
    $nes->runFrame();

    $ppu = $nes->getPPU();
    $frame = $ppu->getFrameBuffer();

    $uniqueColors = [];
    $nonBlack = 0;

    foreach ($frame as $pixel) {
        $key = implode(',', $pixel);
        $uniqueColors[$key] = ($uniqueColors[$key] ?? 0) + 1;
        if ($pixel[0] !== 0 || $pixel[1] !== 0 || $pixel[2] !== 0) {
            $nonBlack++;
        }
    }

    echo "After forcing PPUMASK=$1E:\n";
    echo "  Non-black pixels: $nonBlack / 61440\n";
    echo "  Unique colors: " . count($uniqueColors) . "\n";

    if (count($uniqueColors) <= 10) {
        echo "  Top colors:\n";
        arsort($uniqueColors);
        $count = 0;
        foreach ($uniqueColors as $colorKey => $pixels) {
            list($r, $g, $b) = explode(',', $colorKey);
            printf("    RGB(%3d,%3d,%3d): %6d pixels (%.1f%%)\n",
                $r, $g, $b, $pixels, ($pixels / 61440) * 100);
            if (++$count >= 5) break;
        }
    }

    echo "\n";
}
