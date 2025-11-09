<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

/**
 * Check if CHR-ROM data is properly loaded and accessible
 */

function checkCHRROM(string $romPath): void
{
    echo "=== Checking CHR-ROM for $romPath ===\n";

    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();
    $bus = $nes->getBus();

    // Read first 128 bytes of CHR-ROM (pattern table 0)
    echo "First 128 bytes of CHR-ROM (Pattern Table 0):\n";
    $allZeros = true;
    for ($addr = 0x0000; $addr < 0x0080; $addr++) {
        $value = $mapper->ppuRead($addr);
        if ($value !== 0x00) {
            $allZeros = false;
        }
        if ($addr % 16 === 0) {
            printf("%04X: ", $addr);
        }
        printf("%02X ", $value);
        if ($addr % 16 === 15) {
            echo "\n";
        }
    }

    if ($allZeros) {
        echo "\nWARNING: All CHR-ROM bytes are zero!\n";
    } else {
        echo "\nCHR-ROM has non-zero data\n";
    }

    // Check nametable
    echo "\nFirst 128 bytes of Nametable 0:\n";
    $allZeros = true;
    for ($addr = 0x2000; $addr < 0x2080; $addr++) {
        $value = $bus->read($addr);
        if ($value !== 0x00) {
            $allZeros = false;
        }
        if ($addr % 16 === 0) {
            printf("%04X: ", $addr);
        }
        printf("%02X ", $value);
        if ($addr % 16 === 15) {
            echo "\n";
        }
    }

    if ($allZeros) {
        echo "\nNametable is all zeros (expected before game initializes)\n";
    } else {
        echo "\nNametable has non-zero data\n";
    }

    echo "\n";
}

checkCHRROM(__DIR__ . '/roms/supermario.nes');
checkCHRROM(__DIR__ . '/roms/donkeykong.nes');

echo "Done!\n";
