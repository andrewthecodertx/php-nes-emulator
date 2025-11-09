<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Cartridge\Cartridge;

echo "Testing Darkman ROM\n";
echo "===================\n\n";

// Load cartridge to check mapper
$cartridge = Cartridge::fromFile(__DIR__ . '/roms/darkman.nes');
printf("Mapper: %d\n", $cartridge->getMapperNumber());
printf("PRG-ROM size: %d KB\n", $cartridge->getPrgRomSize() / 1024);
printf("CHR-ROM size: %d KB\n", $cartridge->getChrRomSize() / 1024);
printf("Mirroring: %s\n", $cartridge->isVerticalMirroring() ? 'Vertical' : 'Horizontal');

echo "\nRunning emulation...\n";

try {
    $nes = NES::fromROM(__DIR__ . '/roms/darkman.nes');
    $bus = $nes->getBus();

    // Run 120 frames
    for ($i = 0; $i < 120; $i++) {
        $nes->runFrame();
        if ($i % 20 === 0) {
            echo "  Frame $i...\n";
        }
    }

    // Force rendering
    $bus->write(0x2001, 0x1E);
    $nes->runFrame();

    echo "\nFrame rendering complete!\n";

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

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nDone!\n";
