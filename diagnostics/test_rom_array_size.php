<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function checkROMArraySize(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $mapper = $nes->getMapper();

    // Use reflection to access private $prgRom array
    $reflection = new ReflectionClass($mapper);
    $prgRomProperty = $reflection->getProperty('prgRom');
    $prgRomProperty->setAccessible(true);
    $prgRom = $prgRomProperty->getValue($mapper);

    $mirroredProperty = $reflection->getProperty('prgRomMirrored');
    $mirroredProperty->setAccessible(true);
    $mirrored = $mirroredProperty->getValue($mapper);

    printf("  PRG-ROM array size: %d bytes (%d KB)\n", count($prgRom), count($prgRom) / 1024);
    printf("  prgRomMirrored: %s\n", $mirrored ? 'true' : 'false');

    // Test reads at various addresses
    printf("  Read $8000: $%02X\n", $mapper->cpuRead(0x8000));
    printf("  Read $C000: $%02X\n", $mapper->cpuRead(0xC000));
    printf("  Read $FFFC: $%02X\n", $mapper->cpuRead(0xFFFC));
    printf("  Read $FFFD: $%02X\n", $mapper->cpuRead(0xFFFD));

    // Check array bounds
    printf("  Array index for $8000: %d\n", 0x8000 - 0x8000);
    printf("  Array index for $C000: %d\n", 0xC000 - 0x8000);
    printf("  Array index for $FFFF: %d\n", 0xFFFF - 0x8000);

    // Check if out of bounds
    $indexC000 = 0xC000 - 0x8000;
    $indexFFFF = 0xFFFF - 0x8000;
    printf("  Index $C000 (%d) in bounds? %s\n", $indexC000, $indexC000 < count($prgRom) ? 'YES' : 'NO (would return 0x00)');
    printf("  Index $FFFF (%d) in bounds? %s\n", $indexFFFF, $indexFFFF < count($prgRom) ? 'YES' : 'NO (would return 0x00)');

    echo "\n";
}

checkROMArraySize(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB - WORKS)');
checkROMArraySize(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB - BROKEN)');

echo "Done!\n";
