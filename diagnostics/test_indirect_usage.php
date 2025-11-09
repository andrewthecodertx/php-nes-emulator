<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function checkIndirectUsage(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 10 frames
    for ($frame = 0; $frame < 10; $frame++) {
        $nes->runFrame();
    }

    $instructions = $monitor->getInstructions();

    // Count JMP indirect instructions (opcode 0x6C)
    $jmpIndirectCount = 0;
    foreach ($instructions as $inst) {
        if ($inst['instruction'] === 0x6C) {
            $jmpIndirectCount++;
        }
    }

    printf("  Total instructions: %d\n", count($instructions));
    printf("  JMP indirect ($6C) count: %d\n", $jmpIndirectCount);

    echo "\n";
}

checkIndirectUsage(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong');
checkIndirectUsage(__DIR__ . '/roms/supermario.nes', 'Super Mario');
checkIndirectUsage(__DIR__ . '/roms/tetris.nes', 'Tetris');

echo "Done!\n";
