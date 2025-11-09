<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

$nes = NES::fromROM(__DIR__ . '/roms/supermario.nes');
$mapper = $nes->getMapper();

echo "Disassembling Super Mario hot loops\n";
echo "====================================\n\n";

echo "Loop 1: $800A-$8012\n";
for ($addr = 0x8000; $addr <= 0x8020; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("$%04X: %02X\n", $addr, $byte);
}

echo "\nLoop 2: $90D4-$90E1\n";
for ($addr = 0x90D0; $addr <= 0x90E5; $addr++) {
    $byte = $mapper->cpuRead($addr);
    printf("$%04X: %02X\n", $addr, $byte);
}

echo "\nDone!\n";
