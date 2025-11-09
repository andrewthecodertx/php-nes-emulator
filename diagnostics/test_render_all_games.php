<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Test rendering for all games
 */

function testGameRendering(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $bus = $nes->getBus();

    // Run 120 frames
    for ($i = 0; $i < 120; $i++) {
        $nes->runFrame();
    }

    // Force rendering
    $bus->write(0x2001, 0x1E);

    // Run one more frame
    $nes->runFrame();

    // Analyze frame buffer
    $frameBuffer = $nes->getFrameBuffer();
    $uniqueColors = [];
    foreach ($frameBuffer as $pixel) {
        $color = sprintf("%d,%d,%d", $pixel[0], $pixel[1], $pixel[2]);
        $uniqueColors[$color] = ($uniqueColors[$color] ?? 0) + 1;
    }

    echo "Unique colors: " . count($uniqueColors) . "\n";

    // Show top 5 colors
    arsort($uniqueColors);
    $count = 0;
    echo "Top 5 colors:\n";
    foreach ($uniqueColors as $color => $pixelCount) {
        echo "  RGB($color): $pixelCount pixels\n";
        if (++$count >= 5) break;
    }

    echo "\n";
}

testGameRendering(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testGameRendering(__DIR__ . '/roms/supermario.nes', 'Super Mario Bros');
testGameRendering(__DIR__ . '/roms/tetris.nes', 'Tetris');
testGameRendering(__DIR__ . '/roms/joust.nes', 'Joust');

echo "Done!\n";
