<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Properly read nametable data through PPUADDR/PPUDATA
 */

function readNametableThroughPPU(NES $nes, int $startAddr, int $count): array
{
    $bus = $nes->getBus();
    $data = [];

    // Set PPU address
    $bus->write(0x2006, ($startAddr >> 8) & 0xFF); // High byte
    $bus->write(0x2006, $startAddr & 0xFF);        // Low byte

    // Read data through PPUDATA
    // First read is buffered, so throw it away
    $bus->read(0x2007);

    // Now read actual data
    for ($i = 0; $i < $count; $i++) {
        $data[] = $bus->read(0x2007);
    }

    return $data;
}

function testNametable(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);

    // Run 120 frames
    for ($i = 0; $i < 120; $i++) {
        $nes->runFrame();
    }

    // Read first 128 bytes of nametable 0
    $nametableData = readNametableThroughPPU($nes, 0x2000, 128);

    echo "First 128 bytes of Nametable 0 (via PPUDATA):\n";
    for ($i = 0; $i < 128; $i++) {
        if ($i % 16 === 0) {
            printf("%04X: ", 0x2000 + $i);
        }
        printf("%02X ", $nametableData[$i]);
        if ($i % 16 === 15) {
            echo "\n";
        }
    }

    // Check if nametable is all zeros
    $nonZeroCount = 0;
    foreach ($nametableData as $byte) {
        if ($byte !== 0x00) {
            $nonZeroCount++;
        }
    }

    echo "Non-zero bytes in nametable: $nonZeroCount\n";

    // Read first 128 bytes of CHR pattern table 0
    $patternData = readNametableThroughPPU($nes, 0x0000, 128);

    echo "\nFirst 128 bytes of Pattern Table 0 (via PPUDATA):\n";
    for ($i = 0; $i < 128; $i++) {
        if ($i % 16 === 0) {
            printf("%04X: ", 0x0000 + $i);
        }
        printf("%02X ", $patternData[$i]);
        if ($i % 16 === 15) {
            echo "\n";
        }
    }

    $nonZeroCount = 0;
    foreach ($patternData as $byte) {
        if ($byte !== 0x00) {
            $nonZeroCount++;
        }
    }

    echo "Non-zero bytes in pattern table: $nonZeroCount\n\n";
}

testNametable(__DIR__ . '/roms/supermario.nes', 'Super Mario Bros');
testNametable(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');

echo "Done!\n";
