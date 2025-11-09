<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function testJmpIndirectUsage(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 5000 instructions
    for ($i = 0; $i < 5000; $i++) {
        $cpu->step();
    }

    $instructions = $monitor->getInstructions();

    // Find JMP indirect instructions (opcode 0x6C)
    $jmpIndirectCount = 0;
    $jmpAddresses = [];
    
    foreach ($instructions as $inst) {
        if ($inst['instruction'] === 0x6C) {
            $jmpIndirectCount++;
            // Get the indirect address from the next two bytes
            $jmpAddresses[] = sprintf("$%04X", $inst['pc']);
        }
    }

    printf("  JMP indirect count: %d\n", $jmpIndirectCount);
    if ($jmpIndirectCount > 0) {
        echo "  Found at PCs:\n";
        foreach (array_slice($jmpAddresses, 0, 10) as $addr) {
            echo "    $addr\n";
        }
    }

    echo "\n";
}

testJmpIndirectUsage(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
testJmpIndirectUsage(__DIR__ . '/roms/supermario.nes', 'Super Mario');

echo "Done!\n";
