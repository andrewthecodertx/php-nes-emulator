<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Check reset vectors and initial PC for all games
 */

function testResetVector(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $bus = $nes->getBus();
    $mapper = $nes->getMapper();

    // Read reset vector from $FFFC-$FFFD
    $resetLow = $mapper->cpuRead(0xFFFC);
    $resetHigh = $mapper->cpuRead(0xFFFD);
    $resetVector = $resetLow | ($resetHigh << 8);

    printf("Reset vector at FFFC-FFFD: %04X\n", $resetVector);
    printf("CPU PC after reset: %04X\n", $cpu->pc);

    // Read first few instructions at reset vector
    echo "First 32 bytes at reset vector:\n";
    for ($addr = $resetVector; $addr < $resetVector + 32; $addr++) {
        if (($addr - $resetVector) % 16 === 0) {
            printf("%04X: ", $addr);
        }
        $byte = $mapper->cpuRead($addr);
        printf("%02X ", $byte);
        if (($addr - $resetVector) % 16 === 15) {
            echo "\n";
        }
    }

    // Check mirroring flag
    echo "\n";
    $cartridge = \andrewthecoder\nes\Cartridge\Cartridge::fromFile($romPath);
    $prgSize = $cartridge->getPrgRomSize();
    printf("PRG-ROM size: %d KB\n", $prgSize / 1024);

    echo "\n";
}

testResetVector(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB PRG - WORKS)');
testResetVector(__DIR__ . '/roms/supermario.nes', 'Super Mario Bros (32KB PRG - BROKEN)');

echo "Done!\n";
