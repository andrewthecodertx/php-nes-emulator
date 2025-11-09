<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function testROMAfterFix(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $bus = $nes->getBus();

    // Run 10 frames
    for ($frame = 0; $frame < 10; $frame++) {
        $nes->runFrame();
    }

    // Check palette state
    $bus->write(0x2006, 0x3F);
    $bus->write(0x2006, 0x00);
    $bus->read(0x2007); // Dummy read

    $nonZero = 0;
    $paletteData = [];
    for ($i = 0; $i < 32; $i++) {
        $val = $bus->read(0x2007);
        $paletteData[] = $val;
        if ($val !== 0) $nonZero++;
    }

    printf("  After 10 frames: %d non-zero palette entries\n", $nonZero);
    
    if ($nonZero > 0) {
        echo "  First 8 palette entries: ";
        for ($i = 0; $i < 8; $i++) {
            printf("%02X ", $paletteData[$i]);
        }
        echo "\n";
    }

    echo "\n";
}

testROMAfterFix(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB)');
testROMAfterFix(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB)');
testROMAfterFix(__DIR__ . '/roms/joust.nes', 'Joust (16KB)');
testROMAfterFix(__DIR__ . '/roms/tetris.nes', 'Tetris (32KB)');

echo "Done!\n";
