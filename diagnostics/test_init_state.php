<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;

function testInitState(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $mapper = $nes->getMapper();

    echo "After NES::fromROM():\n";
    printf("  CPU PC: \$%04X\n", $cpu->pc);

    // Read reset vector
    $resetLow = $mapper->cpuRead(0xFFFC);
    $resetHigh = $mapper->cpuRead(0xFFFD);
    $resetVector = $resetLow | ($resetHigh << 8);
    printf("  Reset vector: \$%04X\n", $resetVector);

    // Step once
    $cpu->step();
    printf("After first step:\n");
    printf("  CPU PC: \$%04X\n", $cpu->pc);

    // Run a full frame
    $nes->runFrame();
    printf("After first frame:\n");
    printf("  CPU PC: \$%04X\n", $cpu->pc);

    echo "\n";
}

testInitState(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testInitState(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Done!\n";
