<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function readPalette(NES $nes): array
{
    $bus = $nes->getBus();

    // Set PPUADDR to palette
    $bus->write(0x2006, 0x3F);
    $bus->write(0x2006, 0x00);
    $bus->read(0x2007); // Dummy read

    $palette = [];
    for ($i = 0; $i < 32; $i++) {
        $palette[] = $bus->read(0x2007);
    }

    return $palette;
}

echo "Testing Super Mario Bros - CORRECT palette reading\n";
echo "===================================================\n\n";

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');

for ($frame = 0; $frame < 10; $frame++) {
    $paletteBefore = readPalette($nes);

    $nes->runFrame();

    $paletteAfter = readPalette($nes);

    $nonZero = count(array_filter($paletteAfter, fn($v) => $v !== 0));

    printf("Frame %d: %d non-zero palette entries\n", $frame, $nonZero);

    if ($nonZero > 0) {
        echo "  Palette: ";
        foreach ($paletteAfter as $i => $val) {
            if ($val !== 0) {
                printf("%02X@%02X ", $val, $i);
            }
        }
        echo "\n";
    }
}

echo "\nDone!\n";
