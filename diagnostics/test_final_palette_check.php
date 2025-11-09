<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function checkFinalPalette(string $romPath, string $label, int $frames): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $bus = $nes->getBus();

    // Run frames WITHOUT any interference
    for ($i = 0; $i < $frames; $i++) {
        $nes->runFrame();
    }

    // NOW read palette
    $bus->write(0x2006, 0x3F);
    $bus->write(0x2006, 0x00);
    $bus->read(0x2007); // Dummy

    $palette = [];
    for ($i = 0; $i < 32; $i++) {
        $palette[] = $bus->read(0x2007);
    }

    $nonZero = count(array_filter($palette, fn($v) => $v !== 0));
    printf("After %d frames: %d non-zero palette entries\n", $frames, $nonZero);

    if ($nonZero > 0) {
        echo "Palette: ";
        for ($i = 0; $i < 32; $i++) {
            printf("%02X ", $palette[$i]);
            if ($i === 15) echo "\n         ";
        }
        echo "\n";
    }

    echo "\n";
}

checkFinalPalette(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)', 10);
checkFinalPalette(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)', 10);
checkFinalPalette(__DIR__ . '/roms/joust.nes', 'Joust (WORKS)', 10);

echo "Done!\n";
