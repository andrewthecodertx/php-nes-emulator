<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use andrewthecoder\nes\NES;
use andrewthecoder\MOS6502\CPUMonitor;

function analyzeExecutionFlow(string $romPath, string $label): void
{
    echo "=== $label ===\n";

    $nes = NES::fromROM($romPath);
    $cpu = $nes->getCPU();
    $ppu = $nes->getPPU();

    $monitor = new CPUMonitor();
    $cpu->setMonitor($monitor);

    // Run 10 frames
    for ($frame = 0; $frame < 10; $frame++) {
        $nes->runFrame();
    }

    $instructions = $monitor->getInstructions();

    // Get unique PC addresses
    $pcAddresses = array_column($instructions, 'pc');
    $uniquePCs = array_unique($pcAddresses);
    
    printf("  Total instructions: %d\n", count($instructions));
    printf("  Unique PC addresses: %d\n", count($uniquePCs));
    printf("  PC range: $%04X - $%04X\n", min($uniquePCs), max($uniquePCs));
    printf("  PPU frame count: %d\n", $ppu->getFrameCount());

    // Check which memory regions are being executed
    $regions = [
        '$8000-$9FFF' => 0,
        '$A000-$BFFF' => 0,
        '$C000-$DFFF' => 0,
        '$E000-$FFFF' => 0,
    ];

    foreach ($pcAddresses as $pc) {
        if ($pc >= 0x8000 && $pc < 0xA000) $regions['$8000-$9FFF']++;
        elseif ($pc >= 0xA000 && $pc < 0xC000) $regions['$A000-$BFFF']++;
        elseif ($pc >= 0xC000 && $pc < 0xE000) $regions['$C000-$DFFF']++;
        elseif ($pc >= 0xE000) $regions['$E000-$FFFF']++;
    }

    echo "  Execution by region:\n";
    foreach ($regions as $region => $count) {
        if ($count > 0) {
            printf("    %s: %d instructions (%.1f%%)\n", 
                $region, $count, ($count / count($pcAddresses)) * 100);
        }
    }

    echo "\n";
}

analyzeExecutionFlow(__DIR__ . '/roms/donkeykong.nes', 'Donkey Kong (16KB - WORKS)');
analyzeExecutionFlow(__DIR__ . '/roms/supermario.nes', 'Super Mario (32KB - BROKEN)');
analyzeExecutionFlow(__DIR__ . '/roms/joust.nes', 'Joust (16KB - WORKS)');
analyzeExecutionFlow(__DIR__ . '/roms/tetris.nes', 'Tetris (32KB - BROKEN)');

echo "Done!\n";
