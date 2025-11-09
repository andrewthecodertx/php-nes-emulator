<?php

require __DIR__ . '/../../vendor/autoload.php';

use andrewthecoder\nes\NES;

echo "=== Comparing Rendering Between ROMs ===\n\n";

$roms = [
    'donkeykong.nes' => 'Donkey Kong (WORKS)',
    'nestest.nes' => 'NESTest (WORKS)',
    'supermario.nes' => 'Super Mario (BROKEN)',
    'tetris.nes' => 'Tetris (BROKEN)',
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

    $ppu = $nes->getPPU();
    $bus = $nes->getBus();

    // Check rendering WITHOUT forcing
    $reflection = new ReflectionClass($ppu);
    $maskProp = $reflection->getProperty('mask');
    $maskProp->setAccessible(true);
    $mask = $maskProp->getValue($ppu);

    echo "Natural state (no forcing):\n";
    echo "  PPUMASK: $" . sprintf("%02X", $mask->get()) . "\n";
    echo "  Rendering enabled: " . ($mask->isRenderingEnabled() ? 'YES' : 'NO') . "\n";

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

    echo "  Frame buffer: $nonBlack non-black pixels\n";
    echo "  Unique colors: " . count($uniqueColors) . "\n";

    if (count($uniqueColors) <= 5) {
        echo "  Colors:\n";
        foreach ($uniqueColors as $colorKey => $count) {
            list($r, $g, $b) = explode(',', $colorKey);
            printf("    RGB(%3d,%3d,%3d): %6d pixels\n", $r, $g, $b, $count);
        }
    }

    echo "\n";
}
