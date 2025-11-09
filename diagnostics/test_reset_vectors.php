<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function checkResetVector(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();
    $cpu = $nes->getCPU();

    // Read reset vector from $FFFC-$FFFD
    $resetLow = $mapper->cpuRead(0xFFFC);
    $resetHigh = $mapper->cpuRead(0xFFFD);
    $resetVector = $resetLow | ($resetHigh << 8);

    printf("  Reset vector: $%04X  (bytes: $%02X $%02X)\n", $resetVector, $resetLow, $resetHigh);
    printf("  CPU PC after reset: $%04X\n", $cpu->pc);

    // Read first 10 bytes at reset vector
    echo "  First 10 bytes at reset vector:\n    ";
    for ($i = 0; $i < 10; $i++) {
        printf("$%04X: %02X   ", $resetVector + $i, $mapper->cpuRead($resetVector + $i));
    }
    echo "\n\n";

    // Also check the raw ROM data for the reset vector location
    echo "  Check $FFFC-$FFFD mapping:\n";
    for ($addr = 0xFFFC; $addr <= 0xFFFF; $addr++) {
        $value = $mapper->cpuRead($addr);
        printf("    $%04X => $%02X\n", $addr, $value);
    }

    echo "\n";
}

checkResetVector(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB - WORKS)');
checkResetVector(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB - BROKEN)');
checkResetVector(__DIR__ . '/roms/joust.nes', 'Joust (16KB - WORKS)');
checkResetVector(__DIR__ . '/roms/tetris.nes', 'Tetris (32KB - BROKEN)');

echo "Done!\n";
