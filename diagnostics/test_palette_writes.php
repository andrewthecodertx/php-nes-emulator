<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function testPaletteWrites(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $bus = $nes->getBus();
    $ppu = $nes->getPPU();

    // Manually write to palette
    echo "Manual palette write test:\n";

    // Set PPUADDR to $3F00 (palette RAM)
    $bus->write(0x2006, 0x3F); // High byte
    $bus->write(0x2006, 0x00); // Low byte

    // Write some colors
    $bus->write(0x2007, 0x0F); // Black
    $bus->write(0x2007, 0x30); // White
    $bus->write(0x2007, 0x16); // Red
    $bus->write(0x2007, 0x27); // Green

    // Read back palette
    $bus->write(0x2006, 0x3F); // High byte
    $bus->write(0x2006, 0x00); // Low byte
    $bus->read(0x2007); // Dummy read

    echo "Palette readback:\n  ";
    for ($i = 0; $i < 4; $i++) {
        $value = $bus->read(0x2007);
        printf("$%02X ", $value);
    }
    echo "\n";

    // Now run some frames and check if game writes to palette
    echo "\nRunning 10 frames...\n";
    for ($i = 0; $i < 10; $i++) {
        $nes->runFrame();
    }

    // Read palette again
    $bus->write(0x2006, 0x3F);
    $bus->write(0x2006, 0x00);
    $bus->read(0x2007); // Dummy

    echo "Palette after 10 frames:\n  ";
    $nonZero = 0;
    for ($i = 0; $i < 32; $i++) {
        $value = $bus->read(0x2007);
        if ($value !== 0x00) $nonZero++;
        printf("%02X ", $value);
        if (($i + 1) % 16 === 0) echo "\n  ";
    }
    printf("\nNon-zero palette entries: %d\n", $nonZero);

    echo "\n";
}

testPaletteWrites(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (WORKS)');
testPaletteWrites(__DIR__ . '/roms/supermario.nes', 'Super Mario (BROKEN)');

echo "Done!\n";
