<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\Cartridge\Cartridge;

function testPRGArray(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $cartridge = Cartridge::fromFile($romPath);
    $prgRom = $cartridge->getPrgRom();

    $size = count($prgRom);
    printf("PRG-ROM array size: %d bytes\n", $size);

    // Check last 16 bytes (where reset vector should be)
    $lastOffset = $size - 16;
    echo "Last 16 bytes of PRG-ROM array (offsets):\n";
    printf("  Offset $%04X-%04X: ", $lastOffset, $size - 1);
    for ($i = $lastOffset; $i < $size; $i++) {
        printf("%02X ", $prgRom[$i]);
    }
    echo "\n";

    // For 32KB ROM, reset vector is at offset 0x7FFC-0x7FFD
    if ($size >= 32768) {
        $resetVectorOffset = 0x7FFC;
        $resetLow = $prgRom[$resetVectorOffset] ?? 0xFF;
        $resetHigh = $prgRom[$resetVectorOffset + 1] ?? 0xFF;
        printf("Reset vector at offset $%04X-$%04X: $%02X $%02X (= $%04X)\n",
            $resetVectorOffset,
            $resetVectorOffset + 1,
            $resetLow,
            $resetHigh,
            $resetLow | ($resetHigh << 8)
        );
    }

    echo "\n";
}

testPRGArray(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB)');
testPRGArray(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB)');

echo "Done!\n";
