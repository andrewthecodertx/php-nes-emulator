<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\nes\Cartridge\Cartridge;

function testPRGROM(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $cartridge = Cartridge::fromFile($romPath);
    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();

    $prgSize = $cartridge->getPrgRomSize();
    $prgRom = $cartridge->getPrgRom();

    printf("PRG-ROM size from header: %d bytes (%d KB)\n", $prgSize, $prgSize / 1024);
    printf("PRG-ROM array count: %d bytes\n", count($prgRom));

    // Test reading at different addresses
    $testAddresses = [
        0x8000, // Start of PRG-ROM
        0xC000, // Start of second 16KB bank
        0xFFFC, // Reset vector low
        0xFFFD, // Reset vector high
    ];

    echo "Test reads:\n";
    foreach ($testAddresses as $addr) {
        $value = $mapper->cpuRead($addr);
        printf("  Read $%04X = $%02X\n", $addr, $value);
    }

    // Read reset vector
    $resetLow = $mapper->cpuRead(0xFFFC);
    $resetHigh = $mapper->cpuRead(0xFFFD);
    $resetVector = $resetLow | ($resetHigh << 8);
    printf("Reset vector: $%04X\n", $resetVector);

    // Read first 16 bytes at reset vector
    echo "First 16 bytes at reset vector:\n  ";
    for ($i = 0; $i < 16; $i++) {
        $value = $mapper->cpuRead($resetVector + $i);
        printf("%02X ", $value);
    }
    echo "\n\n";
}

testPRGROM(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB - WORKS)');
testPRGROM(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB - BROKEN)');
testPRGROM(__DIR__ . '/roms/joust.nes', 'Joust (16KB - WORKS)');

echo "Done!\n";
